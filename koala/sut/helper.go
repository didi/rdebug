package sut

import (
	"github.com/v2pro/plz/countlog"
	"bytes"
)

var helperThreadShutdown = "to-koala!thread-shutdown"
var helperCallFunction = "to-koala!call-function"
var helperReturnFunction = "to-koala!return-function"
var helperReadStorage = "to-koala!read-storage"
var helperSetDelegatedFromThreadId = "to-koala!set-delegated-from-thread-id"
var helperGetTraceHeader = "to-koala!get-trace-header"
var helperGetTraceHeaderKey = "to-koala!get-trace-header-key"
var helperSetTraceHeaderKey = "to-koala!set-trace-header-key"

func SendToKoala(threadID ThreadID, span []byte, flags SendToFlags) {
	helperInfo := span
	countlog.Trace("event!sut.send_to_koala",
		"threadID", threadID,
		"flags", flags,
		"content", helperInfo)
	newlinePos := bytes.IndexByte(helperInfo, '\n')
	if newlinePos == -1 {
		return
	}
	body := helperInfo[newlinePos+1:]
	switch string(helperInfo[:newlinePos]) {
	case helperThreadShutdown:
		if flags != 0 {
			operateVirtualThread(ThreadID(flags), func(thread *Thread) {
				thread.OnShutdown()
			})
		} else {
			OperateThread(threadID, func(thread *Thread) {
				thread.OnShutdown()
			})
		}
	case helperCallFunction:
		OperateThread(threadID, func(thread *Thread) {
			thread.replayingSession.CallFunction(thread, body)
		})
	case helperReturnFunction:
		OperateThread(threadID, func(thread *Thread) {
			thread.replayingSession.ReturnFunction(thread, body)
		})
	case helperReadStorage:
		OperateThread(threadID, func(thread *Thread) {
			thread.recordingSession.ReadStorage(thread, body)
		})
	case helperSetDelegatedFromThreadId:
		realThreadId := threadID
		virtualThreadId := ThreadID(flags)
		mapThreadRelation(realThreadId, virtualThreadId)
	case helperGetTraceHeader:
		OperateThread(threadID, func(thread *Thread) {
			if thread.recordingSession != nil {
				thread.helperResponse = thread.recordingSession.GetTraceHeader()
			}
		})
	case helperGetTraceHeaderKey:
		OperateThread(threadID, func(thread *Thread) {
			if thread.recordingSession != nil {
				key := body
				thread.helperResponse = thread.recordingSession.GetTraceHeader().Get(key)
			}
		})
	case helperSetTraceHeaderKey:
		OperateThread(threadID, func(thread *Thread) {
			if thread.recordingSession != nil {
				newlinePos = bytes.IndexByte(body, '\n')
				if newlinePos == -1 {
					countlog.Error("event!sut.SetTraceHeaderKey expects newline as separator",
						"body", body)
					return
				}
				key := body[:newlinePos]
				value := body[newlinePos+1:]
				thread.recordingSession.TraceHeader = thread.recordingSession.GetTraceHeader().Set(key, value)
			}
		})
	default:
		countlog.Debug("event!sut.unknown_helper",
			"threadID", threadID,
			"helperType", string(helperInfo[:newlinePos]))
	}
}

func RecvFromKoala(threadID ThreadID) []byte {
	thread := getThread(threadID)
	response := thread.helperResponse
	thread.helperResponse = nil
	countlog.Trace("event!sut.recv_from_koala",
		"threadID", threadID,
		"response", response)
	return response
}
