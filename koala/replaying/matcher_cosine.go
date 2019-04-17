package replaying

import (
	"fmt"
	"math"
	"sort"
	"sync"
	"time"

	"github.com/didi/rdebug/koala/recording"
	"github.com/didi/rdebug/koala/replaying/lexer"
	"github.com/v2pro/plz/countlog"
)

var globalVectors = map[string][]map[string]float64{}
var globalVectorsMutex = &sync.Mutex{}

type CosineMatcher struct {
	Threshold float64
}

func (c *CosineMatcher) Match(connContext *ConnMatchContext, request []byte, replayingSession *ReplayingSession) (
	int, float64, *recording.CallOutbound) {

	//if len(request) == 0 {
	//	return matchEmptyRequest(connContext, request, replayingSession)
	//}

	maxScore := float64(0)
	maxScoreIndex := -1
	maxScoreIdxs := make(map[int]string, 3)
	scores := make([]float64, len(replayingSession.CallOutbounds))
	reqVector := lexer.Lex2Vector(request)
	outboundContexts := getReplayingSessionVectors(replayingSession)
	for i, callOutbound := range replayingSession.CallOutbounds {
		scores[i] = CosineSimilarity(outboundContexts[i], reqVector)
		if scores[i] > maxScore {
			maxScore = scores[i]
			maxScoreIndex = i
			maxScoreIdxs = map[int]string{maxScoreIndex: callOutbound.GetIdentifier(),}
		} else if scores[i] == maxScore {
			maxScoreIdxs[i] = callOutbound.GetIdentifier()
		}
	}
	if len(maxScoreIdxs) > 1 {
		maxScoreIndex = getMatchedIndexOfMultiMaxScore(connContext, maxScoreIdxs, replayingSession.outsLastMatchedIdx, maxScoreIndex)
	}

	countlog.Trace("event!replaying.similarity_talks_scored",
		"ConnContext", connContext.String(),
		"replaySession.outsLastMatchedIdx", replayingSession.outsLastMatchedIdx,
		"maxScoreIndex", func() interface{} {
			return fmt.Sprintf("%v", maxScoreIdxs)
		},
		"maxScoreIndex", maxScoreIndex,
		"maxScore", maxScore,
		"scores", func() interface{} {
			return fmt.Sprintf("%v", scores)
		})

	if maxScore < c.Threshold {
		connContext.LastMatchedIndex = -1
		return -1, 0, nil
	}

	if maxScoreIndex > replayingSession.outsLastMatchedIdx {
		replayingSession.outsLastMatchedIdx = maxScoreIndex
	}
	connContext.LastMatchedIndex = maxScoreIndex
	connContext.UpdateCounter(replayingSession.CallOutbounds[maxScoreIndex])

	return maxScoreIndex, scores[maxScoreIndex], replayingSession.CallOutbounds[maxScoreIndex]
}

func (c *CosineMatcher) RShutdown(replayingSession *ReplayingSession) bool {
	globalVectorsMutex.Lock()
	defer globalVectorsMutex.Unlock()

	delete(globalVectors, replayingSession.SessionId)
	return true
}

func CosineSimilarity(a, b map[string]float64) (sim float64) {
	prod, aSquareSum, bSquareSum := 0.0, 0.0, 0.0

	for aTerm, aWeight := range a {
		if bWeight, ok := b[aTerm]; ok {
			prod += aWeight * bWeight
		}
		aSquareSum += aWeight * aWeight
	}
	for _, bWeight := range b {
		bSquareSum += bWeight * bWeight
	}

	if aSquareSum == 0 || bSquareSum == 0 {
		return 0
	}

	return prod / (math.Sqrt(aSquareSum) * math.Sqrt(bSquareSum))
}

func getReplayingSessionVectors(replayingSession *ReplayingSession) []map[string]float64 {
	globalVectorsMutex.Lock()
	defer globalVectorsMutex.Unlock()

	vectors := globalVectors[replayingSession.SessionId]
	if vectors == nil {
		begin := time.Now()
		vectors = make([]map[string]float64, len(replayingSession.CallOutbounds))
		for i, callOutbound := range replayingSession.CallOutbounds {
			vectors[i] = lexer.Lex2Vector(callOutbound.Request)
		}
		globalVectors[replayingSession.SessionId] = vectors
		countlog.Trace("event!replaying.build_vector", "spendTime", time.Since(begin))
	}

	return vectors
}

func getStartMatchIndex(connLastMatchedIndex int, lastMaxScoreIndex int) int {
	if connLastMatchedIndex != -1 {
		return connLastMatchedIndex + 1
	} else if lastMaxScoreIndex != -1 {
		return lastMaxScoreIndex + 1
	}
	return -1
}

func getMatchedIndexOfMultiMaxScore(connCtx *ConnMatchContext, maxScoreIdxs map[int]string,
	outsLastMatchedIdx int, curMaxIndex int) int {

	var maxIdxs []int
	for idx, _ := range maxScoreIdxs {
		maxIdxs = append(maxIdxs, idx)
	}
	sort.Ints(maxIdxs)

	maxCount := 0
	var maxCounterIdx []int
	counter := connCtx.MatchedCounter
	for _, idx := range maxIdxs {
		identifier := maxScoreIdxs[idx]
		if count, ok := counter[identifier]; ok {
			if count > maxCount {
				maxCount = count
				maxCounterIdx = []int{idx}
			} else if count == maxCount {
				maxCounterIdx = append(maxCounterIdx, idx)
			}
		}
	}
	if len(maxCounterIdx) == 1 {
		return maxCounterIdx[0]
	}

	fixStartIdx := getStartMatchIndex(connCtx.LastMatchedIndex, outsLastMatchedIdx)
	if len(maxCounterIdx) > 1 {
		for _, idx := range maxCounterIdx {
			if idx >= fixStartIdx {
				return idx
			}
		}
	}

	for _, idx := range maxIdxs {
		if idx >= fixStartIdx {
			return idx
		}
	}

	return curMaxIndex
}

//func matchEmptyRequest(connContext *ConnMatchContext, request []byte, replayingSession *ReplayingSession) (
//	int, float64, *recording.CallOutbound) {
//
//	if len(request) != 0 {
//		return -1, 0, nil
//	}
//
//	emptyIdxs := map[int]string{}
//	for i, callOutbound := range replayingSession.CallOutbounds {
//		if len(callOutbound.Request) == 0 {
//			emptyIdxs[i] = callOutbound.GetIdentifier()
//		}
//	}
//	if len(emptyIdxs) == 0 {
//		return -1, 0, nil
//	}
//
//	maxScoreIndex := getMatchedIndexOfMultiMaxScore(connContext, emptyIdxs, replayingSession.outsLastMatchedIdx, -1)
//	if maxScoreIndex == -1 {
//		return -1, 0, nil
//	}
//	return maxScoreIndex, 1, replayingSession.CallOutbounds[maxScoreIndex]
//}

