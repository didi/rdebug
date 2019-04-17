package outbound

import (
	"context"
	"io"
	"net"
	"time"

	"github.com/didi/rdebug/koala/envarg"
	"github.com/didi/rdebug/koala/internal"
	"github.com/didi/rdebug/koala/recording"
	"github.com/didi/rdebug/koala/replaying"
	"github.com/v2pro/plz/countlog"
)

const fakeIndexNotMatched = -1
const fakeIndexSimulated = -2

func Start() {
	go server()
}

func server() {
	internal.SetCurrentGoRoutineIsKoala()
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!outbound.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	listener, err := net.Listen("tcp", envarg.OutboundAddr().String())
	if err != nil {
		countlog.Error("event!outbound.failed to listen outbound", "err", err)
		return
	}
	countlog.Info("event!outbound.started", "outboundAddr", envarg.OutboundAddr())
	for {
		conn, err := listener.Accept()
		if err != nil {
			countlog.Error("event!outbound.failed to accept outbound", "err", err)
			return
		}
		go handleOutbound(conn.(*net.TCPConn))
	}
}

func handleOutbound(conn *net.TCPConn) {
	internal.SetCurrentGoRoutineIsKoala()
	defer func() {
		recovered := recover()
		if recovered != nil {
			countlog.Fatal("event!outbound.panic", "err", recovered,
				"stacktrace", countlog.ProvideStacktrace)
		}
	}()
	defer conn.Close()
	tcpAddr := conn.RemoteAddr().(*net.TCPAddr)
	countlog.Trace("event!outbound.new_conn", "addr", tcpAddr)
	buf := make([]byte, 1024)
	connMatchedCtx := replaying.NewConnMatchContext(tcpAddr, fakeIndexNotMatched)
	ctx := context.WithValue(context.Background(), "outboundSrc", tcpAddr.String())
	protocol := ""
	beginTime := time.Now()
	for i := 0; i < 1024; i++ {
		guessedProtocol, request := readRequest(ctx, conn, buf, i == 0)
		if guessedProtocol != "" {
			protocol = guessedProtocol
		}
		if len(request) == 0 {
			if protocol == "mysql" && request != nil {
				continue
			}
			countlog.Warn("event!outbound.received empty request", "ctx", ctx, "i", i)
			return
		}
		replayingSession := replaying.RetrieveTmp(*tcpAddr)
		if replayingSession == nil {
			if len(request) == 0 {
				countlog.Warn("event!outbound.read request empty", "ctx", ctx, "i", i)
				return
			}
			if protocol == "mysql" {
				// when mysql connection setup at application startup
				if err := applySimulation(simulateMysql, ctx, request, conn, nil); err != nil {
					return
				}
			}
			countlog.Error("event!outbound.outbound can not find replaying session",
				"ctx", ctx, "addr", tcpAddr, "content", request, "protocol", protocol)
			return
		}
		callOutbound := replaying.NewCallOutbound(*tcpAddr, request)
		//// fix http 100 continue
		//if err := applySimulation(simulateHttp, ctx, request, conn, callOutbound); err != nil {
		//	return
		//}
		//// some mysql connection setup interaction might not recorded
		//if err := applySimulation(simulateMysql, ctx, request, conn, callOutbound); err != nil {
		//	return
		//}
		var matchedTalk *recording.CallOutbound
		var mark float64
		_, mark, matchedTalk = replaying.Matcher.Match(connMatchedCtx, request, replayingSession)
		if callOutbound.MatchedActionIndex != fakeIndexSimulated {
			if matchedTalk == nil {
				callOutbound.MatchedRequest = nil
				callOutbound.MatchedResponse = nil
				callOutbound.MatchedActionIndex = fakeIndexNotMatched
			} else {
				callOutbound.MatchedRequest = matchedTalk.Request
				callOutbound.MatchedResponse = matchedTalk.Response
				callOutbound.MatchedActionIndex = matchedTalk.ActionIndex
			}
			callOutbound.MatchedMark = mark
			if matchedTalk == nil {
				replayingSession.CallOutbound(ctx, callOutbound)
				countlog.Error("event!outbound.failed to find matching talk", "ctx", ctx)
				return
			}
			if _, err := conn.Write(matchedTalk.Response); err != nil {
				countlog.Error("event!outbound.failed to write back response from outbound",
					"ctx", ctx, "err", err)
				return
			}
		} else {
			// set matched id as ActionIndex for simulateHttp|simulateMysql
			if matchedTalk != nil {
				callOutbound.MatchedMark = mark
				callOutbound.MatchedRequest = matchedTalk.Request
				callOutbound.MatchedResponse = matchedTalk.Response
				callOutbound.MatchedActionIndex = matchedTalk.ActionIndex
			}
		}
		replayingSession.CallOutbound(ctx, callOutbound)
		countlog.Debug("event!outbound.response",
			"ctx", ctx,
			"matchedMark", mark,
			"actionId", callOutbound.ActionId,
			"matchedActionIndex", callOutbound.MatchedActionIndex,
			"matchedRequest", callOutbound.MatchedRequest,
			"requestLen", len(request),
			"matchedRequestLen", len(callOutbound.MatchedRequest),
			"matchedResponse", callOutbound.MatchedResponse,
			"matchedResponseLen", len(callOutbound.MatchedResponse),
			"spendTime", time.Since(beginTime))
	}
}

func readRequest(ctx context.Context, conn *net.TCPConn, buf []byte, isFirstPacket bool) (string, []byte) {
	request := []byte{}
	if isFirstPacket {
		conn.SetReadDeadline(time.Now().Add(time.Millisecond * 20))
	} else {
		conn.SetReadDeadline(time.Now().Add(time.Second * 5))
	}
	bytesRead, err := conn.Read(buf)
	if err == io.EOF {
		countlog.Trace("event!outbound.read_request_EOF", "ctx", ctx, "err", err)
		return "", nil
	}
	protocol := ""
	if err != nil {
		countlog.Trace("event!outbound.read_request_first_read_err", "ctx", ctx, "err", err)
		if isFirstPacket {
			countlog.Debug("event!outbound.write_mysql_greeting", "ctx", ctx)
			_, err := conn.Write(mysqlGreeting)
			if err != nil {
				countlog.Error("event!outbound.failed to write mysql greeting", "ctx", ctx, "err", err)
				return "", nil
			}
			protocol = "mysql"
		}
	} else {
		request = append(request, buf[:bytesRead]...)
	}
	for {
		conn.SetReadDeadline(time.Now().Add(time.Millisecond * 3))
		bytesRead, err := conn.Read(buf)
		if err != nil {
			countlog.Trace("event!outbound.read_request_err", "ctx", ctx, "err", err) // i/o timeout
			break
		}
		request = append(request, buf[:bytesRead]...)
	}
	countlog.Debug("event!outbound.finish_read_request", "ctx", ctx, "content", request,
		"isFirstPacket", isFirstPacket, "protocol", protocol)
	return protocol, request
}

func applySimulation(sim func(ctx context.Context, request []byte) []byte, ctx context.Context,
	request []byte, conn net.Conn, callOutbound *replaying.CallOutbound) error {
	resp := sim(ctx, request) // mysql connection setup might not in the recorded session
	if resp != nil {
		if callOutbound != nil {
			callOutbound.MatchedRequest = request
			callOutbound.MatchedActionIndex = fakeIndexSimulated // to be ignored
			callOutbound.MatchedResponse = resp
		}
		_, err := conn.Write(resp)
		if err != nil {
			countlog.Error("event!outbound.failed to write back response from outbound",
				"ctx", ctx, "err", err)
			return err
		}
		return nil
	}
	return nil
}
