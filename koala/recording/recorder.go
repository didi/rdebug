package recording

type Recorder interface {
	Record(session *Session)
}

var Recorders = []Recorder{}

var ShouldRecordAction = func(action Action) bool {
	return true
}
