package sut

import (
	"context"
	"net"
	"strconv"
	"sync"
	"time"

	"github.com/didi/rdebug/koala/envarg"
	"github.com/didi/rdebug/koala/recording"
	"github.com/v2pro/plz/countlog"
)

type SocketFD int

type FileFD int

type ThreadID int32

type file struct {
	fileFD   FileFD
	fileName string
	flags    int
}

var globalSocks = map[SocketFD]*socket{}
var globalSocksMutex = &sync.Mutex{}
var globalThreads = map[ThreadID]*Thread{} // real thread id => virtual thread
var globalThreadsMutex = &sync.Mutex{}
var globalVirtualThreads = map[ThreadID]*Thread{} // virtual thread id => virtual thread
var globalVirtualThreadsMutex = &sync.Mutex{}

func init() {
	go gcStatesInBackground()
	countlog.RegisterStateExporterByFunc("socks", exportSocks)
	countlog.RegisterStateExporterByFunc("threads", exportThreads)
}

func exportSocks() map[string]interface{} {
	globalSocksMutex.Lock()
	defer globalSocksMutex.Unlock()
	state := map[string]interface{}{}
	for socketFD, sock := range globalSocks {
		state[strconv.Itoa(int(socketFD))] = sock
	}
	return state
}

func exportThreads() map[string]interface{} {
	globalThreadsMutex.Lock()
	defer globalThreadsMutex.Unlock()
	state := map[string]interface{}{}
	for threadID, thread := range globalThreads {
		state[strconv.Itoa(int(threadID))] = thread
	}
	return state
}

func setGlobalSock(socketFD SocketFD, sock *socket) {
	globalSocksMutex.Lock()
	defer globalSocksMutex.Unlock()
	sock.lastAccessedAt = time.Now()
	globalSocks[socketFD] = sock
}

func RemoveGlobalSock(socketFD SocketFD) *socket {
	globalSocksMutex.Lock()
	defer globalSocksMutex.Unlock()
	sock := globalSocks[socketFD]
	if sock != nil {
		delete(globalSocks, socketFD)
	}
	return sock
}

func getGlobalSock(socketFD SocketFD) *socket {
	globalSocksMutex.Lock()
	defer globalSocksMutex.Unlock()
	sock := globalSocks[socketFD]
	if sock != nil {
		sock.lastAccessedAt = time.Now()
	}
	return sock
}

func OperateThread(threadID ThreadID, op func(thread *Thread)) {
	thread := getThread(threadID)
	thread.mutex.Lock()
	defer thread.mutex.Unlock()
	thread.OnAccess()
	thread.lastAccessedAt = time.Now()
	op(thread)
}

func operateVirtualThread(threadID ThreadID, op func(thread *Thread)) {
	thread := getVirtualThread(threadID)
	thread.mutex.Lock()
	defer thread.mutex.Unlock()
	thread.OnAccess()
	thread.lastAccessedAt = time.Now()
	op(thread)
}

func getThread(threadID ThreadID) *Thread {
	globalThreadsMutex.Lock()
	defer globalThreadsMutex.Unlock()
	thread := globalThreads[threadID]
	if thread == nil {
		thread = newThread(threadID)
		globalThreads[threadID] = thread
	}
	return thread
}

func getVirtualThread(threadID ThreadID) *Thread {
	globalVirtualThreadsMutex.Lock()
	defer globalVirtualThreadsMutex.Unlock()
	thread := globalVirtualThreads[threadID]
	if thread == nil {
		thread = newThread(threadID)
		globalVirtualThreads[threadID] = thread
	}
	return thread
}

func mapThreadRelation(realThreadID ThreadID, virtualThreadID ThreadID) {
	virtualThread := getVirtualThread(virtualThreadID)
	globalThreadsMutex.Lock()
	defer globalThreadsMutex.Unlock()
	globalThreads[realThreadID] = virtualThread
}

func newThread(threadID ThreadID) *Thread {
	thread := &Thread{
		Context:        context.WithValue(context.Background(), "threadID", threadID),
		mutex:          &sync.Mutex{},
		threadID:       threadID,
		socks:          map[SocketFD]*socket{},
		files:          map[FileFD]*file{},
		lastAccessedAt: time.Now(),
		ignoreSocks:    map[SocketFD]net.TCPAddr{},
	}
	if envarg.IsRecording() {
		thread.recordingSession = recording.NewSession(int32(threadID))
	}
	return thread
}

func gcStatesInBackground() {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!sut.gc_states_in_background.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	for {
		time.Sleep(time.Second * 10)
		gcStatesOneRound()
	}
}

func gcStatesOneRound() {
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!sut.gc_states_one_round.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	expiredSocksCount := gcGlobalSocks()
	expiredRealThreadsCount := gcGlobalRealThreads()
	expiredVirtualThreadsCount := gcGlobalVirtualThreads()
	countlog.Trace("event!sut.gc_global_states",
		"expiredSocksCount", expiredSocksCount,
		"expiredRealThreadsCount", expiredRealThreadsCount,
		"expiredVirtualThreadsCount", expiredVirtualThreadsCount)
}

func gcGlobalSocks() int {
	globalSocksMutex.Lock()
	defer globalSocksMutex.Unlock()
	now := time.Now()
	newMap := map[SocketFD]*socket{}
	expiredSocksCount := 0
	timeout := 5 * time.Minute
	if envarg.GcGlobalStatusTimeout() > timeout {
		timeout = envarg.GcGlobalStatusTimeout()
	}
	for fd, sock := range globalSocks {
		if now.Sub(sock.lastAccessedAt) < envarg.GcGlobalStatusTimeout() {
			newMap[fd] = sock
		} else {
			expiredSocksCount++
		}
	}
	globalSocks = newMap
	return expiredSocksCount
}

func gcGlobalRealThreads() int {
	globalThreadsMutex.Lock()
	defer globalThreadsMutex.Unlock()
	now := time.Now()
	newMap := map[ThreadID]*Thread{}
	expiredThreadsCount := 0
	for threadId, thread := range globalThreads {
		if now.Sub(thread.lastAccessedAt) < envarg.GcGlobalStatusTimeout() {
			newMap[threadId] = thread
		} else {
			shutdownThread(thread)
			expiredThreadsCount++
		}
	}
	globalThreads = newMap
	return expiredThreadsCount
}

func gcGlobalVirtualThreads() int {
	globalVirtualThreadsMutex.Lock()
	defer globalVirtualThreadsMutex.Unlock()
	now := time.Now()
	newMap := map[ThreadID]*Thread{}
	expiredThreadsCount := 0
	for threadId, thread := range globalVirtualThreads {
		if now.Sub(thread.lastAccessedAt) < envarg.GcGlobalStatusTimeout() {
			newMap[threadId] = thread
		} else {
			shutdownThread(thread)
			expiredThreadsCount++
		}
	}
	globalVirtualThreads = newMap
	return expiredThreadsCount
}

func shutdownThread(thread *Thread) {
	thread.mutex.Lock()
	defer thread.mutex.Unlock()
	thread.OnShutdown()
}
