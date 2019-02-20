package recording

import (
	"context"
	"encoding/json"
	"fmt"
	"net"
	"time"

	"github.com/v2pro/plz/countlog"
)

type Session struct {
	Context             string
	ThreadId            int32
	SessionId           string
	TraceHeader         TraceHeader
	TraceId             []byte
	SpanId              []byte
	NextSessionId       string
	CallFromInbound     *CallFromInbound
	ReturnInbound       *ReturnInbound
	Actions             []Action
	currentAppendFiles  map[string]*AppendFile `json:"-"`
	currentCallOutbound *CallOutbound          `json:"-"`
}

func NewSession(threadId int32) *Session {
	return &Session{
		ThreadId:  threadId,
		SessionId: fmt.Sprintf("%d-%d", time.Now().UnixNano(), threadId),
	}
}

func (session *Session) MarshalJSON() ([]byte, error) {
	return json.Marshal(struct {
		Session
		TraceId json.RawMessage
		SpanId  json.RawMessage
	}{
		Session: *session,
		TraceId: EncodeAnyByteArray(session.TraceId),
		SpanId:  EncodeAnyByteArray(session.SpanId),
	})
}

func (session *Session) newAction(actionType string) action {
	occurredAt := time.Now().UnixNano()
	return action{
		ActionIndex: len(session.Actions),
		OccurredAt:  occurredAt,
		ActionType:  actionType,
	}
}

func (session *Session) AppendFile(ctx context.Context, content []byte, fileName string) {
	if session == nil {
		return
	}
	if session.currentAppendFiles == nil {
		session.currentAppendFiles = map[string]*AppendFile{}
	}
	appendFile := session.currentAppendFiles[fileName]
	if appendFile == nil {
		appendFile = &AppendFile{
			action:   session.newAction("AppendFile"),
			FileName: fileName,
		}
		session.currentAppendFiles[fileName] = appendFile
		session.addAction(appendFile)
	}
	appendFile.Content = append(appendFile.Content, content...)
}

func (session *Session) ReadStorage(ctx context.Context, span []byte) {
	if session == nil {
		return
	}
	session.addAction(&ReadStorage{
		action:  session.newAction("ReadStorage"),
		Content: append([]byte(nil), span...),
	})
}

func (session *Session) RecvFromInbound(ctx context.Context, span []byte, peer net.TCPAddr, unix net.UnixAddr) {
	if session == nil {
		return
	}
	if session.CallFromInbound == nil {
		session.CallFromInbound = &CallFromInbound{
			action:   session.newAction("CallFromInbound"),
			Peer:     peer,
			UnixAddr: unix,
		}
	}
	session.CallFromInbound.Request = append(session.CallFromInbound.Request, span...)
}

func (session *Session) SendToInbound(ctx context.Context, span []byte, peer net.TCPAddr) {
	if session == nil {
		return
	}
	if session.ReturnInbound == nil {
		session.ReturnInbound = &ReturnInbound{
			action: session.newAction("ReturnInbound"),
		}
		session.addAction(session.ReturnInbound)
	}
	session.ReturnInbound.Response = append(session.ReturnInbound.Response, span...)
}

func (session *Session) RecvFromOutbound(ctx context.Context, span []byte, peer net.TCPAddr, local *net.TCPAddr, socketFD int) {
	if session == nil {
		return
	}
	if session.currentCallOutbound == nil {
		session.newCallOutbound(peer, local, socketFD)
	}
	if (session.currentCallOutbound.Peer.String() != peer.String()) ||
		(session.currentCallOutbound.SocketFD != socketFD) {
		session.newCallOutbound(peer, local, socketFD)
	}
	if session.currentCallOutbound.ResponseTime == 0 {
		session.currentCallOutbound.ResponseTime = time.Now().UnixNano()
	}
	session.currentCallOutbound.Response = append(session.currentCallOutbound.Response, span...)
}

func (session *Session) SendToOutbound(ctx context.Context, span []byte, peer net.TCPAddr, local *net.TCPAddr, socketFD int) {
	if session == nil {
		return
	}
	session.BeforeSendToOutbound(ctx, peer, local, socketFD)
	session.currentCallOutbound.Request = append(session.currentCallOutbound.Request, span...)
}

func (session *Session) BeforeSendToOutbound(ctx context.Context, peer net.TCPAddr, local *net.TCPAddr, socketFD int) {
	if session == nil {
		return
	}
	if (session.currentCallOutbound == nil) ||
		(session.currentCallOutbound.Peer.String() != peer.String()) ||
		(session.currentCallOutbound.SocketFD != socketFD) ||
		(len(session.currentCallOutbound.Response) > 0) {
		session.newCallOutbound(peer, local, socketFD)
	} else if session.currentCallOutbound != nil && session.currentCallOutbound.ResponseTime > 0 {
		// last request get a bad response, e.g., timeout
		session.newCallOutbound(peer, local, socketFD)
	}
}

func (session *Session) newCallOutbound(peer net.TCPAddr, local *net.TCPAddr, socketFD int) {
	cspanId := []byte(nil)
	session.currentCallOutbound = &CallOutbound{
		action:   session.newAction("CallOutbound"),
		Peer:     peer,
		Local:    local,
		SocketFD: socketFD,
		CSpanId:  cspanId,
	}
	session.addAction(session.currentCallOutbound)
}

func (session *Session) SendUDP(ctx context.Context, span []byte, peer net.UDPAddr) {
	if session == nil {
		return
	}
	session.addAction(&SendUDP{
		action:  session.newAction("SendUDP"),
		Peer:    peer,
		Content: append([]byte(nil), span...),
	})
}

func (session *Session) HasResponded() bool {
	if session == nil {
		return false
	}
	if session.ReturnInbound == nil {
		return false
	}
	return true
}

func (session *Session) addAction(action Action) {
	if !ShouldRecordAction(action) {
		return
	}
	session.Actions = append(session.Actions, action)
}

func (session *Session) GetTraceHeader() TraceHeader {
	if session.TraceHeader == nil {
		traceId, _ := newID().MarshalText()
		session.TraceHeader = session.TraceHeader.Set(TraceHeaderKeyTraceId, traceId)
		session.TraceId = traceId
		countlog.Trace("event!recording.generated_trace_header",
			"threadID", session.ThreadId,
			"sessionId", session.SessionId,
			"traceId", traceId,
		)
	}
	return session.TraceHeader
}

func (session *Session) Shutdown(ctx context.Context, newSession *Session) {
	if session == nil {
		return
	}
	session.Summary(newSession)
	session.NextSessionId = newSession.SessionId
	if session.CallFromInbound == nil {
		return
	}
	if len(session.CallFromInbound.Request) == 0 {
		return
	}
	for _, recorder := range Recorders {
		recorder.Record(session)
	}
	countlog.Debug("event!recording.session_recorded",
		"ctx", ctx,
		"session", session,
	)
}

func (session *Session) Summary(newSession *Session) {
	reqLen := 0
	respLen := 0
	if session.CallFromInbound != nil {
		reqLen = len(session.CallFromInbound.Request)
	}
	if session.ReturnInbound != nil {
		respLen = len(session.ReturnInbound.Response)
	}
	countlog.Trace("event!recording.shutdown_recording_session",
		"threadID", session.ThreadId,
		"sessionId", session.SessionId,
		"nextSessionId", newSession.SessionId,
		"callFromInboundBytes",
		reqLen,
		"returnInboundBytes",
		respLen,
		"actionsCount",
		len(session.Actions))
}
