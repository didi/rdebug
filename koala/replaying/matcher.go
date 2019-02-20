package replaying

import (
	"context"
	"github.com/didi/rdebug/koala/envarg"
	"github.com/didi/rdebug/koala/recording"
)

type MatcherIf interface {
	DoMatch(ctx context.Context, connLastMatchedIndex int, request []byte, replayingSession *ReplayingSession) (int, float64, *recording.CallOutbound)
}

type SimMatcher struct {
}

type ChunkMatcher struct {
}

var Matcher MatcherIf
var MatchThreshold float64

func InitMatcher() {
	//init matcher
	if envarg.ReplayingMatchStrategy() == replayingSimHashMatch {
		Matcher = SimMatcher{}
	} else {
		Matcher = ChunkMatcher{}
	}
	
	//init match threshold
	MatchThreshold = envarg.ReplayingMatchThreshold()
}

func (matchSim SimMatcher) DoMatch(ctx context.Context, connLastMatchedIndex int, request []byte, replayingSession *ReplayingSession) (int, float64, *recording.CallOutbound) {
	return replayingSession.similarityMatch(ctx, connLastMatchedIndex, request)
}

func (matchOther ChunkMatcher) DoMatch(ctx context.Context, connLastMatchedIndex int, request []byte, replayingSession *ReplayingSession) (int, float64, *recording.CallOutbound) {
	lastMatchedIndex, mark, matchedTalk := replayingSession.chunkMatch(ctx, connLastMatchedIndex, request)
	if matchedTalk == nil && connLastMatchedIndex >= 0 {
		// rematch from begin and lastMatchedIndex = -1 maybe first chunk has more weight
		// TODO: reduce cutToChunks to once, now is twice
		return replayingSession.chunkMatch(ctx, -1, request)
	}
	return lastMatchedIndex, mark, matchedTalk
}
