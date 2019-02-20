// +build koala_go

package internal

import (
	"runtime"
	"github.com/v2pro/plz/countlog"
	"syscall"
	"net"
)

func SetCurrentGoRoutineIsKoala() {
	countlog.Trace("event!internal.set_is_koala", "threadID", GetCurrentGoRoutineId())
	runtime.SetCurrentGoRoutineIsKoala()
}

func SetDelegatedFromGoRoutineId(goid int64) {
	countlog.Debug("event!internal.set_delegated_from",
		"from", goid, "to", GetCurrentGoRoutineId())
	runtime.SetDelegatedFromGoRoutineId(goid)
}

func GetCurrentGoRoutineIsKoala() bool {
	return runtime.GetCurrentGoRoutineIsKoala()
}

func GetCurrentGoRoutineId() int64 {
	return runtime.GetCurrentGoRoutineId()
}

func RegisterOnConnect(callback func(fd int, sa syscall.Sockaddr)) {
	syscall.OnConnect = callback
}

func RegisterOnAccept(callback func(serverSocketFD int, clientSocketFD int, sa syscall.Sockaddr)) {
	syscall.OnAccept = callback
}

func RegisterOnRecv(callback func(fd int, net string, raddr net.Addr, span []byte)) {
	net.OnRead = callback
}

func RegisterOnSend(callback func(fd int, net string, raddr net.Addr, span []byte)) {
	net.OnWrite = callback
}

func RegisterOnClose(callback func(fd int)) {
	net.OnClose = callback
}

func RegisterOnGoRoutineExit(callback func(goid int64)) {
	runtime.OnGoRoutineExit = callback
}
