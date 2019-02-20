package gw4go

import (
	"github.com/didi/rdebug/koala/envarg"
	"github.com/didi/rdebug/koala/inbound"
	"github.com/didi/rdebug/koala/internal"
	"github.com/didi/rdebug/koala/outbound"
	"github.com/didi/rdebug/koala/sut"
	"github.com/v2pro/plz/countlog"
	"net"
	"syscall"
)

func Start() {
	setupAcceptHook()
	setupRecvHook()
	setupSendHook()
	setupConnectHook()
	setupCloseHook()
	setupGoRoutineExitHook()
	if envarg.IsReplaying() {
		inbound.Start()
		outbound.Start()
		mode := "replaying"
		if envarg.IsRecording() {
			mode += " & recording"
		}
		countlog.Info("event!main.koala_started",
			"mode", mode)
	} else {
		countlog.Info("event!main.koala_started",
			"mode", "recording")
	}
}

func setupConnectHook() {
	internal.RegisterOnConnect(func(fd int, sa syscall.Sockaddr) {
		gid, isKoala := getGoIDAndIsKoala()
		ipv4Addr, _ := sa.(*syscall.SockaddrInet4)
		if ipv4Addr == nil {
			countlog.Trace("event!discard non-ipv4 addr on connect", "addr", sa)
			return
		}
		if isKoala {
			return
		}
		origIP := make([]byte, 4)
		copy(origIP, ipv4Addr.Addr[:]) // ipv4Addr.Addr will be reused
		origAddr := net.TCPAddr{
			IP:   origIP,
			Port: ipv4Addr.Port,
		}

		sut.OperateThread(gid, func(thread *sut.Thread) {
			thread.OnConnect(
				sut.SocketFD(fd), origAddr,
			)
		})
		if envarg.IsReplaying() {
			countlog.Debug("event!gw4go.rewrite_connect_target",
				"origAddr", origAddr,
				"redirectTo", envarg.OutboundAddr())
			for i := 0; i < 4; i++ {
				ipv4Addr.Addr[i] = envarg.OutboundAddr().IP[i]
			}
			ipv4Addr.Port = envarg.OutboundAddr().Port
		}
	})
}

func setupCloseHook() {
	internal.RegisterOnClose(func(fd int) {
		sut.RemoveGlobalSock(sut.SocketFD(fd))
	})
}

func setupAcceptHook() {
	internal.RegisterOnAccept(func(serverSocketFD int, clientSocketFD int, sa syscall.Sockaddr) {
		gid, isKoala := getGoIDAndIsKoala()
		if isKoala {
			return
		}
		sut.OperateThread(gid, func(thread *sut.Thread) {
			thread.OnAccept(
				sut.SocketFD(serverSocketFD), sut.SocketFD(clientSocketFD), sockaddrToTCP(sa),
			)
		})
	})
}

func sockaddrToTCP(sa syscall.Sockaddr) net.TCPAddr {
	switch sa := sa.(type) {
	case *syscall.SockaddrInet4:
		return net.TCPAddr{IP: sa.Addr[0:], Port: sa.Port}
	case *syscall.SockaddrInet6:
		return net.TCPAddr{IP: sa.Addr[0:], Port: sa.Port, Zone: IP6ZoneToString(int(sa.ZoneId))}
	}
	return net.TCPAddr{}
}

func IP6ZoneToString(zone int) string {
	if zone == 0 {
		return ""
	}
	if ifi, err := net.InterfaceByIndex(zone); err == nil {
		return ifi.Name
	}
	return itod(uint(zone))
}

// Convert i to decimal string.
func itod(i uint) string {
	if i == 0 {
		return "0"
	}

	// Assemble decimal in reverse order.
	var b [32]byte
	bp := len(b)
	for ; i > 0; i /= 10 {
		bp--
		b[bp] = byte(i%10) + '0'
	}

	return string(b[bp:])
}

func setupRecvHook() {
	internal.RegisterOnRecv(func(fd int, network string, raddr net.Addr, span []byte) {
		gid, isKoala := getGoIDAndIsKoala()
		if isKoala {
			return
		}
		switch network {
		case "udp", "udp4", "udp6":
		default:
			sut.OperateThread(gid, func(thread *sut.Thread) {
				thread.OnRecv(sut.SocketFD(fd), span, 0)
			})
		}
	})
}

func setupSendHook() {
	internal.RegisterOnSend(func(fd int, network string, raddr net.Addr, span []byte) {
		gid, isKoala := getGoIDAndIsKoala()
		if isKoala {
			return
		}
		switch network {
		case "udp", "udp4", "udp6":
			udpAddr := raddr.(*net.UDPAddr)
			sut.OperateThread(gid, func(thread *sut.Thread) {
				thread.OnSendTo(sut.SocketFD(fd), span, 0, *udpAddr)
			})
		default:
			sut.OperateThread(gid, func(thread *sut.Thread) {
				thread.OnSend(sut.SocketFD(fd), span, 0, 0)
			})
		}
	})
}

func setupGoRoutineExitHook() {
	internal.RegisterOnGoRoutineExit(func(goid int64) {
		_, isKoala := getGoIDAndIsKoala()
		if isKoala {
			return
		}
		sut.OperateThread(sut.ThreadID(goid), func(thread *sut.Thread) {
			thread.OnShutdown()
		})
	})
}

func getGoIDAndIsKoala() (sut.ThreadID, bool) {
	gid := internal.GetCurrentGoRoutineId()
	isKoala := internal.GetCurrentGoRoutineIsKoala()
	return sut.ThreadID(gid), isKoala
}
