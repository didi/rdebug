package replaying

import (
	"bytes"
	"fmt"
	"math"

	"github.com/didi/rdebug/koala/recording"
	"github.com/v2pro/plz/countlog"
)

var expect100 = []byte("Expect: 100-continue")

type ChunkMatcher struct {
}

func (c *ChunkMatcher) Match(connContext *ConnMatchContext, request []byte, replayingSession *ReplayingSession) (
	int, float64, *recording.CallOutbound) {

	connLastMatchedIndex := connContext.LastMatchedIndex

	lastMatchedIndex, mark, matchedTalk := chunkMatch(connContext, request, replayingSession)
	if matchedTalk == nil && connLastMatchedIndex >= 0 {
		// rematch from begin and lastMatchedIndex = -1 maybe first chunk has more weight
		// TODO: reduce cutToChunks to once, now is twice
		lastMatchedIndex, mark, matchedTalk = chunkMatch(connContext, request, replayingSession)
	}

	connContext.LastMatchedIndex = lastMatchedIndex
	return lastMatchedIndex, mark, matchedTalk
}

func (c *ChunkMatcher) RShutdown(replayingSession *ReplayingSession) bool {
	return true
}

func chunkMatch(ctx *ConnMatchContext, request []byte, replayingSession *ReplayingSession) (
	int, float64, *recording.CallOutbound) {

	unit := 16
	chunks := cutToChunks(request, unit)
	reqCandidates := replayingSession.loadKeys()
	scores := make([]int, len(replayingSession.CallOutbounds))
	reqExpect100 := bytes.Contains(request, expect100)
	for i, callOutbound := range replayingSession.CallOutbounds {
		if reqExpect100 != bytes.Contains(callOutbound.Request, expect100) {
			scores[i] = math.MinInt64
		}
	}

	maxScore := 0
	maxScoreIndex := 0
	lastMatchedIndex := ctx.LastMatchedIndex
	for chunkIndex, chunk := range chunks {
		for j, reqCandidate := range reqCandidates {
			if j <= lastMatchedIndex {
				continue
			}
			if len(reqCandidate) < len(chunk) {
				continue
			}
			pos := bytes.Index(reqCandidate, chunk)
			if pos >= 0 {
				reqCandidates[j] = reqCandidate[pos:]
				if chunkIndex == 0 && lastMatchedIndex == -1 {
					scores[j] += len(chunks) // connect first chunk has more weight +
				} else if chunkIndex == 0 && pos == 0 {
					moreScore := len(chunks) / 2
					if moreScore <= 1 {
						moreScore = 2
					}
					scores[j] += moreScore // first chunk full match has more weight, at lease 2
				} else {
					scores[j]++
				}
				hasBetterScore := scores[j] > maxScore
				if hasBetterScore {
					maxScore = scores[j]
					maxScoreIndex = j
				}
			}
		}
	}

	outboundsLastMatchedIndex := replayingSession.outsLastMatchedIdx
	// 多个 maxScore，优先从上一次成功匹配的 Index 之后开始，取第一个 maxScore，尤其是从 0 开始全部重新匹配时
	for j, score := range scores {
		if score == maxScore && outboundsLastMatchedIndex < j {
			maxScoreIndex = j
			break
		}
	}
	countlog.Trace("event!replaying.talks_scored",
		"connContext", ctx,
		"outboundsLastMatchedIndex", outboundsLastMatchedIndex,
		"maxScoreIndex", maxScoreIndex,
		"maxScore", maxScore,
		"totalScore", len(chunks),
		"scores", func() interface{} {
			return fmt.Sprintf("%v", scores)
		})
	if maxScore == 0 {
		return -1, 0, nil
	}
	mark := float64(maxScore) / float64(len(chunks))
	if lastMatchedIndex != -1 {
		// not starting from beginning, should have minimal score
		if mark < 0.85 {
			return -1, 0, nil
		}
	} else {
		if mark < 0.1 {
			return -1, 0, nil
		}
	}
	if maxScoreIndex > replayingSession.outsLastMatchedIdx {
		replayingSession.outsLastMatchedIdx = maxScoreIndex
	}
	return maxScoreIndex, mark, replayingSession.CallOutbounds[maxScoreIndex]
}

func cutToChunks(key []byte, unit int) [][]byte {
	chunks := [][]byte{}
	if len(key) > 256 {
		offset := 0
		for {
			strikeStart, strikeLen := findReadableChunk(key[offset:])
			if strikeStart == -1 {
				break
			}
			if strikeLen > 8 {
				firstChunkLen := strikeLen
				if firstChunkLen > 16 {
					firstChunkLen = 16
				}
				chunks = append(chunks, key[offset+strikeStart:offset+strikeStart+firstChunkLen])
				key = key[offset+strikeStart+firstChunkLen:]
				break
			}
			offset += strikeStart + strikeLen
		}
	}
	chunkCount := len(key) / unit
	for i := 0; i < chunkCount; i++ {
		chunks = append(chunks, key[i*unit:(i+1)*unit])
	}
	lastChunk := key[chunkCount*unit:]
	if len(lastChunk) > 0 {
		chunks = append(chunks, lastChunk)
	}
	return chunks
}

// findReadableChunk returns: the starting index of the trunk, length of the trunk
func findReadableChunk(key []byte) (int, int) {
	start := bytes.IndexFunc(key, func(r rune) bool {
		return r > 31 && r < 127
	})
	if start == -1 {
		return -1, -1
	}
	end := bytes.IndexFunc(key[start:], func(r rune) bool {
		return r <= 31 || r >= 127
	})
	if end == -1 {
		return start, len(key) - start
	}
	return start, end
}
