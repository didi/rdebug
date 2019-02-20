package sut

import (
	"net"
	"syscall"
	"github.com/v2pro/plz/countlog"
	"time"
)

func bindFDToLocalAddr(socketFD int) (*net.TCPAddr, error) {
	localAddr, err := syscall.Getsockname(int(socketFD))
	if err != nil {
		return nil, err
	}
	localInet4Addr := localAddr.(*syscall.SockaddrInet4)
	if localInet4Addr.Port != 0 && localInet4Addr.Addr != [4]byte{} {
		return &net.TCPAddr{
			IP:   localInet4Addr.Addr[:],
			Port: localInet4Addr.Port,
		}, nil
	}
	err = syscall.Bind(socketFD, &syscall.SockaddrInet4{
		Addr: [4]byte{127, 0, 0, 1},
		Port: 0,
	})
	if err != nil {
		return nil, err
	}
	localAddr, err = syscall.Getsockname(int(socketFD))
	if err != nil {
		return nil, err
	}
	localInet4Addr = localAddr.(*syscall.SockaddrInet4)
	return &net.TCPAddr{
		IP:   localInet4Addr.Addr[:],
		Port: localInet4Addr.Port,
	}, nil
}

func (thread *Thread) lookupSocket(socketFD SocketFD) *socket {
	sock := thread.socks[socketFD]
	if sock != nil {
		return sock
	}
	sock = getGlobalSock(socketFD)
	if sock == nil {
		return nil
	}
	remoteAddr, err := syscall.Getpeername(int(socketFD))
	if err != nil {
		countlog.Error("event!failed to get peer name", "err", err, "socketFD", socketFD)
		return nil
	}
	remoteAddr4, _ := remoteAddr.(*syscall.SockaddrInet4)
	// if remote address changed, the fd must be closed and reused
	if remoteAddr4 != nil && (remoteAddr4.Port != sock.addr.Port ||
		remoteAddr4.Addr[0] != sock.addr.IP[0] ||
		remoteAddr4.Addr[1] != sock.addr.IP[1] ||
		remoteAddr4.Addr[2] != sock.addr.IP[2] ||
		remoteAddr4.Addr[3] != sock.addr.IP[3]) {
		sock = &socket{
			socketFD: socketFD,
			isServer: false,
			addr: net.TCPAddr{
				Port: remoteAddr4.Port,
				IP:   net.IP(remoteAddr4.Addr[:]),
			},
			lastAccessedAt: time.Now(),
		}
		setGlobalSock(socketFD, sock)
	}
	remoteAddr6, _ := remoteAddr.(*syscall.SockaddrInet6)
	if remoteAddr6 != nil && (remoteAddr6.Port != sock.addr.Port ||
		remoteAddr6.Addr[0] != sock.addr.IP[0] ||
		remoteAddr6.Addr[1] != sock.addr.IP[1] ||
		remoteAddr6.Addr[2] != sock.addr.IP[2] ||
		remoteAddr6.Addr[3] != sock.addr.IP[3] ||
		remoteAddr6.Addr[4] != sock.addr.IP[4] ||
		remoteAddr6.Addr[5] != sock.addr.IP[5]) {
		sock = &socket{
			socketFD: socketFD,
			isServer: false,
			addr: net.TCPAddr{
				Port: remoteAddr6.Port,
				IP:   net.IP(remoteAddr6.Addr[:]),
			},
			lastAccessedAt: time.Now(),
		}
		setGlobalSock(socketFD, sock)
	}
	thread.socks[socketFD] = sock
	return sock
}
