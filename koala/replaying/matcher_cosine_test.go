package replaying

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func Test_getMatchedIndexOfMultiMaxScore_one_connect_multi_matched(t *testing.T) {
	connCtx := NewConnMatchContext(nil, 21)
	connCtx.MatchedCounter = map[string]int{"100.90.104.35:3000#65":1}
	maxScoreIdxs := map[int]string {
		21: "100.90.104.35:3000#65",
		23: "100.90.104.35:3000#65",
		55: "100.90.104.35:3000#65",
	}
	should := require.New(t)
	should.Equal(22, getMatchedIndexOfMultiMaxScore(connCtx, maxScoreIdxs, 22, 21))
}

func Test_getMatchedIndexOfMultiMaxScore_one_connect_multi_counter_multi_matched(t *testing.T) {
	connCtx := NewConnMatchContext(nil, -1)
	connCtx.MatchedCounter = map[string]int{"100.90.233.21:3000#60":1, "100.90.103.16:3000#67":1}
	maxScoreIdxs := map[int]string {
		43: "100.90.233.21:3000#60",
		48: "100.90.103.16:3000#67",
	}
	should := require.New(t)
	should.Equal(48, getMatchedIndexOfMultiMaxScore(connCtx, maxScoreIdxs, 47, 43))
}

func Test_getMatchedIndexOfMultiMaxScore_one_connect_multi_counter_multi_matched_2(t *testing.T) {
	connCtx := NewConnMatchContext(nil, 14)
	connCtx.MatchedCounter = map[string]int{"100.70.148.57:3000#60":4}
	maxScoreIdxs := map[int]string {
		18: "100.70.148.57:3000#60",
		67: "100.70.148.57:3000#60",
	}
	should := require.New(t)
	should.Equal(18, getMatchedIndexOfMultiMaxScore(connCtx, maxScoreIdxs, 17, 18))
}

func Test_getMatchedIndexOfMultiMaxScore_new_connect_multi_matched(t *testing.T) {
	connCtx := NewConnMatchContext(nil, -1)
	maxScoreIdxs := map[int]string {
		43: "100.90.233.21:3000#60",
		48: "100.90.103.16:3000#67",
	}
	should := require.New(t)
	should.Equal(48, getMatchedIndexOfMultiMaxScore(connCtx, maxScoreIdxs, 47, 43))
}

func Test_getMatchedIndexOfMultiMaxScore_new_connect_multi_matched_2(t *testing.T) {
	connCtx := NewConnMatchContext(nil, -1)
	maxScoreIdxs := map[int]string {
		5: "100.90.233.21:3000#60",
		0: "100.90.103.16:3000#67",
	}
	should := require.New(t)
	should.Equal(0, getMatchedIndexOfMultiMaxScore(connCtx, maxScoreIdxs, -1, 0))
}
