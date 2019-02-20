// +build !koala_go

package internal

import (
	"syscall"
	"net"
)

func SetCurrentGoRoutineIsKoala() {
}

func SetDelegatedFromGoRoutineId(goid int64) {
}

func GetCurrentGoRoutineIsKoala() bool {
	return false
}

func GetCurrentGoRoutineId() int64 {
	return 0
}

func RegisterOnConnect(callback func(fd int, sa syscall.Sockaddr)) {
}

func RegisterOnAccept(callback func(serverSocketFD int, clientSocketFD int, sa syscall.Sockaddr)) {
}

func RegisterOnRecv(callback func(fd int, net string, raddr net.Addr, span []byte)) {
}

func RegisterOnSend(callback func(fd int, net string, raddr net.Addr, span []byte)) {
}

func RegisterOnClose(callback func(fd int)) {
}

func RegisterOnGoRoutineExit(callback func(goid int64)) {
}
