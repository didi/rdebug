package outbound

import (
	"bytes"
	"context"
	"github.com/v2pro/plz/countlog"
)

var http100req = []byte("Expect: 100-continue")
var http100resp = []byte("HTTP/1.1 100 Continue\r\n\r\n")

func simulateHttp(ctx context.Context, request []byte) []byte {
	if bytes.Contains(request, http100req) {
		return simulateHttp100(ctx, request)
	}
	return nil
}

func simulateHttp100(ctx context.Context, request []byte) []byte {
	countlog.Debug("event!outbound.simulated_http",
		"ctx", ctx,
		"requestKeyword", "100-continue",
		"content", request)
	return http100resp
}
