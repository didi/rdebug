package main

import (
	"bytes"
	"context"
	"encoding/json"
	"io/ioutil"
	"net/http"
	"path"

	"github.com/didi/rdebug/koala/envarg"
	_ "github.com/didi/rdebug/koala/gateway/gw4libc"
	"github.com/didi/rdebug/koala/recording"
	"github.com/didi/rdebug/koala/sut"
	"github.com/v2pro/plz/countlog"
	"github.com/v2pro/plz/witch"
)

func init() {
	envarg.SetupLogging()
	addrStr := envarg.GetenvFromC("KOALA_WITCH_ADDR")
	if addrStr == "" {
		addrStr = ":8318"
	}
	witch.Start(addrStr)

	// record last session when next request is coming
	// separate session by request's prefix of begin protocol
	protocol := envarg.GetenvFromC("KOALA_INBOUND_PROTOCOL")
	if protocol == "fastcgi" || protocol == "" {
		sut.InboundRequestPrefix = []byte{1, 1}
	}

	// set record filter
	recording.ShouldRecordAction = shouldRecordAction

	// priority to KOALA_RECORD_TO_DIR
	var recorder recording.Recorder
	dir := envarg.GetenvFromC("KOALA_RECORD_TO_DIR")
	if dir == "" {
		esUrl := envarg.GetenvFromC("KOALA_RECORD_TO_ES")
		if esUrl == "" {
			countlog.Fatal("event!recorder.pleases specify KOALA_RECORD_TO_DIR or KOALA_RECORD_TO_ES")
			return
		}
		// start async recording goroutine
		sessionRecorder := NewAsyncRecorder(esUrl)
		sessionRecorder.Start()
		recorder = sessionRecorder
	} else {
		recorder = &fileRecorder{dir: dir}
	}

	recording.Recorders = append(recording.Recorders, recorder)
}

var ctx = context.TODO()

type fileRecorder struct {
	dir string
}

func (recorder *fileRecorder) Record(session *recording.Session) {
	data, err := json.MarshalIndent(session, "", "  ")
	if err != nil {
		countlog.Error("event!recorder.failed to marshal json", "err", err)
		return
	}
	ioutil.WriteFile(path.Join(recorder.dir, session.SessionId), data, 0666)
}

func NewAsyncRecorder(esUrl string) *recording.AsyncRecorder {
	asyncRecorder := recording.NewAsyncRecorder(&esRecorder{url: esUrl})
	asyncRecorder.Context = ctx
	return asyncRecorder
}

type esRecorder struct {
	url string
}

func (recorder *esRecorder) Record(session *recording.Session) {
	data, err := json.MarshalIndent(session, "", "  ")
	if err != nil {
		countlog.Error("event!recorder.failed to marshal json", "err", err)
		return
	}
	resp, err := http.Post(recorder.url, "application/json", bytes.NewBuffer(data))
	defer resp.Body.Close()
	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		countlog.Fatal("event!recorder.failed to curl es " + recorder.url)
		return
	}
	countlog.Debug("event!recorder.added event", "resp", string(body))
}

func shouldRecordAction(action recording.Action) bool {
	if action == nil {
		return false
	}
	//switch act := action.(type) {
	//case *recording.AppendFile:
	//	if !strings.Contains(act.FileName, "/public.log") {
	//		return false
	//	}
	//case *recording.SendUDP:
	//	if !(act.Peer.IP.String() == "127.0.0.1" && act.Peer.Port == 9891) {
	//		return false
	//	}
	//}
	return true
}

func main() {

}
