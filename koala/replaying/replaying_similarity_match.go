package replaying

import (
	"context"
	"fmt"
	"sync"
	"time"

	"github.com/didi/rdebug/koala/recording"
	"github.com/didi/rdebug/koala/replaying/similarity"
	"github.com/v2pro/plz/countlog"
)

const replayingSimHashMatch = "sim"

var globalVectors = map[string][]map[string]float64{}
var globalVectorsMutex = &sync.Mutex{}

func (replayingSession *ReplayingSession) similarityMatch(
	ctx context.Context, connLastMatchedIndex int, request []byte) (int, float64, *recording.CallOutbound) {

	maxScore := float64(0)
	maxScoreIndex := -1
	maxScoreCount := 0
	scores := make([]float64, len(replayingSession.CallOutbounds))

	lexer := &similarity.Lexer{}
	reqVector := strSlice2Map(lexer.Scan(request))
	outboundVectors := getReplayingSessionVectors(replayingSession)

	for i, _ := range replayingSession.CallOutbounds {
		scores[i] = similarity.Cosine(outboundVectors[i], reqVector)
		if scores[i] > maxScore {
			maxScore = scores[i]
			maxScoreIndex = i
			maxScoreCount = 1
		} else if scores[i] == maxScore {
			maxScoreCount++
		}
	}

	if maxScoreCount > 1 {
		fixStartIdx := getFixStartIndex(connLastMatchedIndex, replayingSession.lastMaxScoreIndex)
		for i, score := range scores {
			if score == maxScore && i >= fixStartIdx {
				maxScoreIndex = i
				break
			}
		}
		// from fixStartIdx maybe can not find maxScore, so use first matched max score index
	}

	if maxScoreIndex > replayingSession.lastMaxScoreIndex {
		replayingSession.lastMaxScoreIndex = maxScoreIndex
	}

	countlog.Trace("event!replaying.similarity_talks_scored",
		"ctx", ctx,
		"replaySession.lastMaxScoreIndex", replayingSession.lastMaxScoreIndex,
		"connLastMatchedIndex", connLastMatchedIndex,
		"maxScoreIndex", maxScoreIndex,
		"maxScore", maxScore,
		"scores", func() interface{} {
			return fmt.Sprintf("%v", scores)
		})

	if maxScore < MatchThreshold {
		return -1, 0, nil
	}
	return maxScoreIndex, scores[maxScoreIndex], replayingSession.CallOutbounds[maxScoreIndex]
}

func getReplayingSessionVectors(replayingSession *ReplayingSession) []map[string]float64 {
	globalVectorsMutex.Lock()
	defer globalVectorsMutex.Unlock()

	vectors := globalVectors[replayingSession.SessionId]
	if vectors == nil {
		begin := time.Now()
		lexer := similarity.Lexer{}
		vectors = make([]map[string]float64, len(replayingSession.CallOutbounds))
		for i, callOutbound := range replayingSession.CallOutbounds {
			vectors[i] = strSlice2Map(lexer.Scan(callOutbound.Request))
		}
		globalVectors[replayingSession.SessionId] = vectors
		countlog.Trace("event!replaying.build_vector", "spendTime", time.Since(begin))
	}

	return vectors
}

func strSlice2Map(str []string) map[string]float64 {
	ret := make(map[string]float64, len(str))
	for _, v := range str {
		ret[v] = 1
	}
	return ret
}

func getFixStartIndex(connLastMatchedIndex int, lastMaxScoreIndex int) int {
	if connLastMatchedIndex != -1 {
		return connLastMatchedIndex + 1
	} else if lastMaxScoreIndex != -1 {
		return lastMaxScoreIndex + 1
	}
	return -1
}
