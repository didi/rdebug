package recording

import (
	"github.com/v2pro/plz/countlog"
	"context"
)

type AsyncRecorder struct {
	Context      context.Context
	realRecorder Recorder
	recordChan   chan *Session
}

func NewAsyncRecorder(realRecorder Recorder) *AsyncRecorder {
	return &AsyncRecorder{
		recordChan:   make(chan *Session, 100),
		realRecorder: realRecorder,
	}
}

func (recorder *AsyncRecorder) Start() {
	go recorder.backgroundRecord()
}

func (recorder *AsyncRecorder) backgroundRecord() {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Error("event!recording.panic",
				"err", recovered,
				"ctx", recorder.Context,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	for {
		session := <-recorder.recordChan
		countlog.Debug("event!recording.record_session",
			"ctx", recorder.Context,
			"session", session)
		recorder.realRecorder.Record(session)
	}
}

func (recorder *AsyncRecorder) Record(session *Session) {
	select {
	case recorder.recordChan <- session:
	default:
		countlog.Error("event!recording.record_chan_overflow",
			"ctx", recorder.Context)
	}
}
