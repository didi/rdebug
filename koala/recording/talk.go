package recording

import "net"

type Talk struct {
	Peer         net.TCPAddr
	Request      []byte
	ResponseTime int64
	Response     []byte
}
