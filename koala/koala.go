// public api for go application using koala
package koala

import (
	"github.com/didi/rdebug/koala/internal"
)

// SetDelegatedFromGoRoutineId should be used when this goroutine is doing work for another goroutine,
// for example multiplex protocol, the request is generated in one goroutine, but sent out from another one.
// Tracking the work delegation chain is required to record or replay session.
func SetDelegatedFromGoRoutineId(goid int64) {
	internal.SetDelegatedFromGoRoutineId(goid)
}

// GetCurrentGoRoutineId get goid from the g
func GetCurrentGoRoutineId() int64 {
	return internal.GetCurrentGoRoutineId()
}

func ExcludeCurrentGoRoutineFromRecording() {
	internal.SetCurrentGoRoutineIsKoala()
}
