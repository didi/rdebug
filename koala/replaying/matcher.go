package replaying

import (
	"fmt"
	"net"

	"github.com/didi/rdebug/koala/recording"
)

const SimMatch = "sim"

type MatcherIf interface {
	Match(connMatchContext *ConnMatchContext, request []byte, replayingSession *ReplayingSession) (int, float64, *recording.CallOutbound)
	RShutdown(replayingSession *ReplayingSession) bool // ReplayingSession shutdown
}

// connect level's matched context
type ConnMatchContext struct {
	ClientAddr *net.TCPAddr
	LastMatchedIndex int
	MatchedCounter map[string]int
}

func NewConnMatchContext(addr *net.TCPAddr, lastMatchedIndex int) *ConnMatchContext {
	return &ConnMatchContext{
		ClientAddr:       addr,
		LastMatchedIndex: lastMatchedIndex,
		MatchedCounter: map[string]int{},
	}
}

func (c *ConnMatchContext) UpdateCounter(callOutbound *recording.CallOutbound) {
	key := callOutbound.GetIdentifier()
	if len(key) == 0 {
		return
	}
	counter := c.MatchedCounter
	if count, ok := counter[key]; ok {
		counter[key] = count + 1
	} else {
		counter[key] = 1
	}
	c.MatchedCounter = counter
}

func (c *ConnMatchContext) String () string {
	return fmt.Sprintf("{ClientAddr=%s, LastMatchedIndex=%d, MatchedCounter=%#v}",
		c.ClientAddr.String(), c.LastMatchedIndex, c.MatchedCounter)
}
