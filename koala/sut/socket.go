package sut

import (
	"net"
	"time"
	"github.com/didi/rdebug/koala/recording"
	"bytes"
	"github.com/v2pro/plz/countlog"
	"encoding/binary"
	"encoding/hex"
	"math"
)

type socket struct {
	socketFD       SocketFD
	isServer       bool
	addr           net.TCPAddr
	localAddr      *net.TCPAddr
	unixAddr       net.UnixAddr
	lastAccessedAt time.Time
	tracerState    *tracerState
}

// state machine responds recv/send, so that trace header can be injected into tcp stream
type tracerState struct {
	isTraced           bool
	nextAction         string
	buffered           []byte // the meaning of this field depends on the context
	expectedBufferSize uint16 // the meaning of this field depends on the context
	prevTraceHeader    []byte // only used in send
}

var magicInit = []byte{0xde, 0xad, 0xbe, 0xef, 0x01}
var magicSameTrace = []byte{0xde, 0xad}
var magicChangeTrace = []byte{0xbe, 0xef}

func (sock *socket) ExportState() map[string]interface{} {
	state := map[string]interface{}{
		"IsServer":       sock.isServer,
		"LastAccessedAt": sock.lastAccessedAt,
		"Addr":           sock.addr,
	}
	return state
}

func (sock *socket) canGC(now time.Time) bool {
	if now.Sub(sock.lastAccessedAt) < time.Minute*5 {
		return false
	}
	if sock.tracerState == nil {
		return true
	}
	if len(sock.tracerState.buffered) != 0 {
		return false
	}
	if sock.tracerState.expectedBufferSize != 0 {
		return false
	}
	return true
}

func (sock *socket) beforeSend(session *recording.Session, bodySize int) ([]byte, int) {
	if bodySize > math.MaxUint16 {
		bodySize = math.MaxUint16 // only 2 bytes to represent the body size in the header
	}
	if sock.tracerState == nil {
		sock.tracerState = &tracerState{}
		countlog.Trace("event!sock.beforeSend_init",
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId)
		return sock.beforeSend_addMagicInit(session.GetTraceHeader(), bodySize)
	}
	if sock.tracerState.expectedBufferSize == 0 {
		traceHeader := session.GetTraceHeader()
		if bytes.Equal(sock.tracerState.prevTraceHeader, traceHeader) {
			countlog.Trace("event!sock.beforeSend_sameTrace",
				"socketFD", sock.socketFD,
				"threadID", session.ThreadId)
			return sock.beforeSend_addMagicSameTrace(bodySize)
		}
		countlog.Trace("event!sock.beforeSend_changeTrace",
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId)
		return sock.beforeSend_addMagicChangeTrace(traceHeader, bodySize)
	}
	if int(sock.tracerState.expectedBufferSize) < bodySize {
		// prevent sending more body then the header specified
		countlog.Trace("event!sock.beforeSend_limitSendBuffer",
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId,
			"expectedBufferSize", sock.tracerState.expectedBufferSize,
			"bodySize", bodySize)
		bodySize = int(sock.tracerState.expectedBufferSize)
	}
	if len(sock.tracerState.buffered) != 0 {
		countlog.Trace("event!sock.beforeSend_sendRemainingHeaderAndBody",
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId,
			"expectedBufferSize", sock.tracerState.expectedBufferSize,
			"buffered", sock.tracerState.buffered)
		return sock.tracerState.buffered, bodySize
	}
	countlog.Trace("event!sock.beforeSend_sendRemainingBody",
		"socketFD", sock.socketFD,
		"threadID", session.ThreadId,
		"expectedBufferSize", sock.tracerState.expectedBufferSize)
	return nil, bodySize
}

func (sock *socket) beforeSend_addMagicInit(traceHeader []byte, bodySize int) ([]byte, int) {
	extraHeader := append(magicInit, []byte{
		byte(len(traceHeader) >> 8),
		byte(len(traceHeader)),
	}...)
	extraHeader = append(extraHeader, traceHeader...)
	extraHeader = append(extraHeader, []byte{
		byte(bodySize >> 8),
		byte(bodySize),
	}...)
	sock.tracerState.buffered = extraHeader
	sock.tracerState.expectedBufferSize = uint16(bodySize)
	sock.tracerState.prevTraceHeader = traceHeader
	return extraHeader, bodySize
}

func (sock *socket) beforeSend_addMagicChangeTrace(traceHeader []byte, bodySize int) ([]byte, int) {
	extraHeader := append(magicChangeTrace, []byte{
		byte(len(traceHeader) >> 8),
		byte(len(traceHeader)),
	}...)
	extraHeader = append(extraHeader, traceHeader...)
	extraHeader = append(extraHeader, []byte{
		byte(bodySize >> 8),
		byte(bodySize),
	}...)
	sock.tracerState.buffered = extraHeader
	sock.tracerState.expectedBufferSize = uint16(bodySize)
	sock.tracerState.prevTraceHeader = traceHeader
	return extraHeader, bodySize
}

func (sock *socket) beforeSend_addMagicSameTrace(bodySize int) ([]byte, int) {
	extraHeader := append(magicSameTrace, []byte{
		byte(bodySize >> 8),
		byte(bodySize),
	}...)
	sock.tracerState.buffered = extraHeader
	sock.tracerState.expectedBufferSize = uint16(bodySize)
	return extraHeader, bodySize
}

func (sock *socket) afterSend(session *recording.Session, extraHeaderSentSize int, bodySentSize int) {
	if sock.tracerState == nil {
		return
	}
	if len(sock.tracerState.buffered) != 0 {
		sock.tracerState.buffered = sock.tracerState.buffered[extraHeaderSentSize:]
	}
	remainingBodySize := int(sock.tracerState.expectedBufferSize) - bodySentSize
	if remainingBodySize < 0 {
		countlog.Error("event!sock.afterSend.remaining body size is negative",
			"threadID", session.ThreadId,
			"socketFD", sock.socketFD,
			"expectedBufferSize", sock.tracerState.expectedBufferSize,
			"bodySentSize", bodySentSize,
			"extraHeaderSentSize", extraHeaderSentSize)
		sock.tracerState.expectedBufferSize = 0
	} else {
		sock.tracerState.expectedBufferSize = uint16(remainingBodySize)
	}
}

func (sock *socket) onRecv(session *recording.Session, span []byte) []byte {
	if len(span) == 0 {
		return span
	}
	if sock.tracerState == nil {
		body := sock.onRecv_initial(session, span)
		countlog.Trace("event!sock.onRecv_init",
			"isTraced", sock.tracerState.isTraced,
			"nextAction", sock.tracerState.nextAction,
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId)
		return body
	}
	if !sock.tracerState.isTraced {
		return span
	}
	prevAction := sock.tracerState.nextAction
	var body []byte
	switch sock.tracerState.nextAction {
	case "readTraceHeaderSize":
		body = sock.onRecv_readTraceHeaderSize(session, span)
	case "readTraceHeader":
		body = sock.onRecv_readTraceHeader(session, span)
	case "readBodySize":
		body = sock.onRecv_readBodySize(session, span)
	case "readBody":
		body = sock.onRecv_readBody(session, span)
	case "readMagic":
		body = sock.onRecv_readMagic(session, span)
	default:
		countlog.Error("event!sock.onRecv_unknown_action",
			"nextAction", sock.tracerState.nextAction,
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId)
	}
	countlog.Trace("event!sock.onRecv_dispatch",
		"prevAction", prevAction,
		"nextAction", sock.tracerState.nextAction,
		"socketFD", sock.socketFD,
		"threadID", session.ThreadId)
	return body
}

func (sock *socket) onRecv_initial(session *recording.Session, span []byte) []byte {
	sock.tracerState = &tracerState{}
	if len(span) < 5 {
		sock.tracerState.isTraced = false
		countlog.Trace("event!sock.onRecv_initial.span too small",
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId)
		return span
	}
	if !bytes.Equal(magicInit[:5], span[:5]) {
		sock.tracerState.isTraced = false
		countlog.Trace("event!sock.onRecv_initial.not starts with magic",
			"socketFD", sock.socketFD,
			"threadID", session.ThreadId)
		return span
	}
	sock.tracerState.isTraced = true
	sock.tracerState.nextAction = "readTraceHeaderSize"
	return sock.onRecv_readTraceHeaderSize(session, span[5:])
}

func (sock *socket) onRecv_readTraceHeaderSize(session *recording.Session, span []byte) []byte {
	alreadyRead := len(sock.tracerState.buffered)
	toRead := 2 - alreadyRead
	if len(span) < toRead {
		toRead = len(span)
		sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
		return nil
	}
	sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
	sock.tracerState.expectedBufferSize = binary.BigEndian.Uint16(sock.tracerState.buffered)
	sock.tracerState.buffered = nil
	sock.tracerState.nextAction = "readTraceHeader"
	return sock.onRecv_readTraceHeader(session, span[toRead:])
}

func (sock *socket) onRecv_readTraceHeader(session *recording.Session, span []byte) []byte {
	alreadyRead := len(sock.tracerState.buffered)
	toRead := int(sock.tracerState.expectedBufferSize) - alreadyRead
	if len(span) < toRead {
		toRead = len(span)
		sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
		return nil
	}
	sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
	session.TraceHeader = sock.tracerState.buffered
	var key recording.TraceHeaderKey
	var value recording.TraceHeaderValue
	var nextHeader = session.TraceHeader
	for len(nextHeader) > 0 {
		key, value, nextHeader = nextHeader.Next()
		if bytes.Equal(key, recording.TraceHeaderKeyTraceId) {
			session.TraceId = append([]byte(nil), value...)
		} else if bytes.Equal(key, recording.TraceHeaderKeySpanId) {
			session.SpanId = append([]byte(nil), value...)
		}
	}
	sock.tracerState.expectedBufferSize = 0
	sock.tracerState.buffered = nil
	sock.tracerState.nextAction = "readBodySize"
	return sock.onRecv_readBodySize(session, span[toRead:])
}

func (sock *socket) onRecv_readBodySize(session *recording.Session, span []byte) []byte {
	alreadyRead := len(sock.tracerState.buffered)
	toRead := 2 - alreadyRead
	if len(span) < toRead {
		toRead = len(span)
		sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
		return nil
	}
	sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
	sock.tracerState.expectedBufferSize = binary.BigEndian.Uint16(sock.tracerState.buffered)
	sock.tracerState.buffered = nil
	sock.tracerState.nextAction = "readBody"
	return sock.onRecv_readBody(session, span[toRead:])
}

func (sock *socket) onRecv_readBody(session *recording.Session, span []byte) []byte {
	if len(span) < int(sock.tracerState.expectedBufferSize) {
		sock.tracerState.expectedBufferSize -= uint16(len(span))
		return span
	}
	bodySize := int(sock.tracerState.expectedBufferSize)
	sock.tracerState.expectedBufferSize = 0
	sock.tracerState.buffered = nil
	sock.tracerState.nextAction = "readMagic"
	moreBody := sock.onRecv_readMagic(session, span[bodySize:])
	if moreBody == nil {
		return span[:bodySize]
	}
	copy(span[bodySize:], moreBody)
	bodySize += len(moreBody)
	return span[:bodySize]
}

func (sock *socket) onRecv_readMagic(session *recording.Session, span []byte) []byte {
	alreadyRead := len(sock.tracerState.buffered)
	toRead := 2 - alreadyRead
	if len(span) < toRead {
		toRead = len(span)
		sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
		return nil
	}
	sock.tracerState.buffered = append(sock.tracerState.buffered, span[:toRead]...)
	if bytes.Equal(magicSameTrace, sock.tracerState.buffered) {
		sock.tracerState.expectedBufferSize = 0
		sock.tracerState.buffered = nil
		sock.tracerState.nextAction = "readBodySize"
		return sock.onRecv_readBodySize(session, span[toRead:])

	} else if bytes.Equal(magicChangeTrace, sock.tracerState.buffered) {
		sock.tracerState.expectedBufferSize = 0
		sock.tracerState.buffered = nil
		sock.tracerState.nextAction = "readTraceHeaderSize"
		return sock.onRecv_readTraceHeaderSize(session, span[toRead:])
	} else {
		countlog.Error("event!sock.onRecv_readMagic.unexpected",
			"magic", hex.EncodeToString(sock.tracerState.buffered),
			"socketFD", sock.socketFD)
		return nil
	}
}
