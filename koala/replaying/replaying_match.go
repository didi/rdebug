package replaying

import (
	"github.com/didi/rdebug/koala/envarg"
)

var Matcher MatcherIf

func init() {
	if envarg.ReplayingMatchStrategy() == SimMatch {
		threshold := envarg.ReplayingMatchThreshold()
		Matcher = &CosineMatcher{Threshold: threshold}
	} else {
		Matcher = &ChunkMatcher{}
	}
}
