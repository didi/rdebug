// +build !koala_recorder
package gw4libc

// #cgo LDFLAGS: -ldl -lm
// #include <stddef.h>
// #include <netinet/in.h>
// #include <sys/types.h>
// #include <sys/socket.h>
// #include <sys/un.h>
// #include "span.h"
// #include "allocated_string.h"
// #include "countlog.h"
// #include "time_hook.h"
// #include "init.h"
import "C"
import (
	"math"
	"net"
	"syscall"
	"unsafe"

	"github.com/didi/rdebug/koala/ch"
	"github.com/didi/rdebug/koala/envarg"
	"github.com/didi/rdebug/koala/gateway/gw4go"
	"github.com/didi/rdebug/koala/sut"
	"github.com/v2pro/plz/countlog"
)

func init() {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.init.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.SetTimeOffset = func(offset int) {
		countlog.Debug("event!main.set_time_offset", "offset", offset)
		C.set_time_offset(C.int(offset))
	}
	gw4go.Start()
	C.go_initialized()
}

//export on_connect
func on_connect(threadID C.pid_t, socketFD C.int, remoteAddr *C.struct_sockaddr_in) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.connect.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	origAddr := net.TCPAddr{
		IP:   ch.Int2ip(sockaddr_in_sin_addr_get(remoteAddr)),
		Port: int(ch.Ntohs(sockaddr_in_sin_port_get(remoteAddr))),
	}

	//IsOutboundBypassAddr: the addr of outbound will be bypassed at recording or replaying, eg: service discovery
	//IsOutboundBypassPort: the port of outbound will bypass at replaying, eg: xdebug
	origAddrStr := origAddr.String()
	if envarg.IsOutboundBypassAddr(origAddrStr) || envarg.IsReplaying() && envarg.IsOutboundBypassPort(origAddr.Port) {
		sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
			thread.IgnoreSocketFD(sut.SocketFD(socketFD), origAddr)
		})
		return
	}
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnConnect(sut.SocketFD(socketFD), origAddr)
	})
	
	if envarg.IsReplaying() && origAddrStr != envarg.OutboundAddr().String() {
		countlog.Trace("event!gw4libc.redirect_connect_target",
			"origAddr", &origAddr, "redirectTo", envarg.OutboundAddr())
		sockaddr_in_sin_addr_set(remoteAddr, ch.Ip2int(envarg.OutboundAddr().IP))
		sockaddr_in_sin_port_set(remoteAddr, ch.Htons(uint16(envarg.OutboundAddr().Port)))
	}
}

//export on_connect_unix
func on_connect_unix(threadID C.pid_t, socketFD C.int, remoteAddr *C.char) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.connect_unix.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	origAddr := net.UnixAddr{
		Name: C.GoString(remoteAddr),
		Net:  "unix",
	}

	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnConnectUnix(sut.SocketFD(socketFD), origAddr)
	})
	//TODO replaying
	if envarg.IsReplaying() {
		countlog.Trace("event!gw4libc.redirect_connect_unix_target",
			"origAddr", origAddr,
			"redirectTo", envarg.OutboundAddr())
		//sockaddr_in_sin_addr_set(remoteAddr, ch.Ip2int(envarg.OutboundAddr().IP))
		//sockaddr_in_sin_port_set(remoteAddr, ch.Htons(uint16(envarg.OutboundAddr().Port)))
	}
}

//export on_bind
func on_bind(threadID C.pid_t, socketFD C.int, addr *C.struct_sockaddr_in) {
}

//export on_bind_unix
func on_bind_unix(threadID C.pid_t, socketFD C.int, addr *C.char) {
}

//export on_accept
func on_accept(threadID C.pid_t, serverSocketFD C.int, clientSocketFD C.int, addr *C.struct_sockaddr_in) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.accept.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	if sockaddr_in_sin_family_get(addr) != syscall.AF_INET {
		panic("expect ipv4 addr")
	}
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnAccept(sut.SocketFD(serverSocketFD), sut.SocketFD(clientSocketFD), net.TCPAddr{
			IP:   ch.Int2ip(sockaddr_in_sin_addr_get(addr)),
			Port: int(ch.Ntohs(sockaddr_in_sin_port_get(addr))),
		})
	})
}

//export on_accept6
func on_accept6(threadID C.pid_t, serverSocketFD C.int, clientSocketFD C.int, addr *C.struct_sockaddr_in6) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.accept.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	if sockaddr_in6_sin_family_get(addr) != syscall.AF_INET6 {
		panic("expect ipv6 addr")
	}
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		ip := sockaddr_in6_sin_addr_get(addr)
		thread.OnAccept(sut.SocketFD(serverSocketFD), sut.SocketFD(clientSocketFD), net.TCPAddr{
			IP:   ip[:],
			Port: int(ch.Ntohs(sockaddr_in6_sin_port_get(addr))),
		})
	})
}

//export on_accept_unix
func on_accept_unix(threadID C.pid_t, serverSocketFD C.int, clientSocketFD C.int, addr *C.char) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.accept.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()

	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		name := C.GoString(addr)
		thread.OnAcceptUnix(sut.SocketFD(serverSocketFD), sut.SocketFD(clientSocketFD), net.UnixAddr{
			Name: name,
			Net:  "unix",
		})
	})
}

//export on_send
func on_send(threadID C.pid_t, socketFD C.int, span C.struct_ch_span, flags C.int, extraHeaderSentSize C.int) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.send.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnSend(sut.SocketFD(socketFD), ch_span_to_bytes(span), sut.SendFlags(flags), int(extraHeaderSentSize))
	})
}

//export on_recv
func on_recv(threadID C.pid_t, socketFD C.int, span C.struct_ch_span, flags C.int) C.struct_ch_span {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.recv.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	var body []byte
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		body = thread.OnRecv(sut.SocketFD(socketFD), ch_span_to_bytes(span), sut.RecvFlags(flags))
	})
	if body == nil {
		return C.struct_ch_span{nil, 0}
	}
	ptr := (*sliceHeader)((unsafe.Pointer)(&body)).Data
	return C.struct_ch_span{ptr, C.size_t(len(body))}
}

// sliceHeader is a safe version of SliceHeader used within this package.
type sliceHeader struct {
	Data unsafe.Pointer
	Len  int
	Cap  int
}

//export on_sendto
func on_sendto(threadID C.pid_t, socketFD C.int, span C.struct_ch_span, flags C.int, addr *C.struct_sockaddr_in) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.sendto.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnSendTo(sut.SocketFD(socketFD), ch_span_to_bytes(span), sut.SendToFlags(flags), net.UDPAddr{
			IP:   ch.Int2ip(sockaddr_in_sin_addr_get(addr)),
			Port: int(ch.Ntohs(sockaddr_in_sin_port_get(addr))),
		})
	})
}

//export recv_from_koala
func recv_from_koala(threadID C.pid_t, span C.struct_ch_span) C.int {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.recv_from_koala.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	response := sut.RecvFromKoala(sut.ThreadID(threadID))
	if response == nil {
		return 0
	}
	return C.int(copy(ch_span_to_bytes(span), response))
}

//export send_to_koala
func send_to_koala(threadID C.pid_t, span C.struct_ch_span, flags C.int) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.send_to_koala.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.SendToKoala(sut.ThreadID(threadID), ch_span_to_bytes(span), sut.SendToFlags(flags))
}

//export on_fopening_file
func on_fopening_file(threadID C.pid_t,
	filename C.struct_ch_span,
	opentype C.struct_ch_span) C.struct_ch_allocated_string {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.fopening_file.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	redirectTo := ""
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		redirectTo = thread.OnOpeningFile(ch_span_to_string(filename), ch_span_to_open_flags(opentype))
	})
	if redirectTo != "" {
		return C.struct_ch_allocated_string{C.CString(redirectTo), C.size_t(len(redirectTo))}
	}
	return C.struct_ch_allocated_string{nil, 0}
}

//export on_fopened_file
func on_fopened_file(threadID C.pid_t,
	fileFD C.int,
	filename C.struct_ch_span,
	opentype C.struct_ch_span) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.fopened_file.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnOpenedFile(sut.FileFD(fileFD), ch_span_to_string(filename), ch_span_to_open_flags(opentype))
	})
}

//export on_opening_file
func on_opening_file(threadID C.pid_t,
	filename C.struct_ch_span,
	flags C.int, mode C.mode_t) C.struct_ch_allocated_string {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.opening_file.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	redirectTo := ""
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		redirectTo = thread.OnOpeningFile(ch_span_to_string(filename), int(flags))
	})
	if redirectTo != "" {
		return C.struct_ch_allocated_string{C.CString(redirectTo), C.size_t(len(redirectTo))}
	}
	return C.struct_ch_allocated_string{nil, 0}
}

//export on_opened_file
func on_opened_file(threadID C.pid_t,
	fileFD C.int,
	filename C.struct_ch_span,
	flags C.int, mode C.mode_t) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.opened_file.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnOpenedFile(sut.FileFD(fileFD), ch_span_to_string(filename), int(flags))
	})
}

//export on_write
func on_write(threadID C.pid_t,
	fileFD C.int,
	span C.struct_ch_span) {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.write.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		thread.OnWrite(sut.FileFD(fileFD), ch_span_to_bytes(span))
	})
}

//export redirect_path
func redirect_path(threadID C.pid_t,
	pathname C.struct_ch_span) C.struct_ch_allocated_string {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!gw4libc.redirect_path.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	redirectTo := ""
	sut.OperateThread(sut.ThreadID(threadID), func(thread *sut.Thread) {
		redirectTo = thread.OnOpeningFile(ch_span_to_string(pathname), 0)
	})
	if redirectTo != "" {
		return C.struct_ch_allocated_string{C.CString(redirectTo), C.size_t(len(redirectTo))}
	}
	return C.struct_ch_allocated_string{nil, 0}
}

//export countlog0
func countlog0(threadID C.pid_t, level C.int, event C.struct_ch_span) {
	countlog.Log(int(level), ch_span_to_string(event),
		"threadID", threadID)
}

//export countlog1
func countlog1(threadID C.pid_t, level C.int, event C.struct_ch_span,
	k1 C.struct_ch_span, v1 C.struct_event_arg) {
	countlog.Log(int(level), ch_span_to_string(event),
		"threadID", threadID, ch_span_to_string(k1), eventArgToEmptyInterface(v1))
}

func eventArgToEmptyInterface(v C.struct_event_arg) interface{} {
	switch v.Type {
	case C.UNSIGNED_LONG:
		return v.Val_ulong
	case C.STRING:
		buf := (*[math.MaxInt32]byte)((unsafe.Pointer)(v.Val_string))[:v.Val_ulong]
		return buf
	default:
		countlog.Warn("event!gw4libc.cast_event_arg_failed", "type", v.Type)
		return nil
	}
}
