package replaying

import (
	"net"
	"sync"
	"github.com/v2pro/plz/countlog"
)

var tmp = map[string]*ReplayingSession{}
var tmpMutex = &sync.Mutex{}

func StoreTmp(inboundAddr net.TCPAddr, session *ReplayingSession) {
	tmpMutex.Lock()
	defer tmpMutex.Unlock()
	tmp[inboundAddr.String()] = session
}

func RetrieveTmp(inboundAddr net.TCPAddr) *ReplayingSession {
	tmpMutex.Lock()
	defer tmpMutex.Unlock()
	key := inboundAddr.String()
	session := tmp[key]
	delete(tmp, key)
	return session
}

func AssignLocalAddr() (*net.TCPAddr, error) {
	// golang does not provide api to bind before connect
	// this is a hack to assign 127.0.0.1:0 to pre-determine a local port
	listener, err := net.Listen("tcp", "127.0.0.1:0") // ask for new port
	if err != nil {
		countlog.Error("event!replaying.failed to resolve local tcp addr port", "err", err)
		return nil, err
	}
	localAddr := listener.Addr().(*net.TCPAddr)
	err = listener.Close()
	if err != nil {
		countlog.Error("event!replaying.failed to close", "err", err)
		return nil, err
	}
	return localAddr, nil
}
