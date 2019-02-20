package recording

import (
	"testing"
	"github.com/stretchr/testify/require"
)

func Test_trace_header(t *testing.T) {
	should := require.New(t)
	header := TraceHeader{}
	header = header.Set(TraceHeaderKeySpanId, TraceHeaderValue("world"))
	header = header.Set(TraceHeaderKeyTraceId, TraceHeaderValue("hello"))
	originalHeader := append([]byte(nil), header...)
	var key TraceHeaderKey
	var value TraceHeaderValue
	key, value, header = header.Next()
	should.Equal(TraceHeaderKeyTraceId, key)
	should.Equal(TraceHeaderValue("hello"), value)
	key, value, header = header.Next()
	should.Equal(TraceHeaderKeySpanId, key)
	should.Equal(TraceHeaderValue("world"), value)
	should.Len(header, 0)
	header = TraceHeader(originalHeader).Set(TraceHeaderKeySpanId, TraceHeaderValue("world2"))
	key, value, header = header.Next()
	should.Equal(TraceHeaderKeySpanId, key)
	should.Equal(TraceHeaderValue("world2"), value)
}
