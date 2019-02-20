package sut

import (
	"testing"
	"github.com/didi/rdebug/koala/recording"
	"github.com/stretchr/testify/require"
)

func Test_send_incomplete(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{TraceHeader: []byte{0xaa}}
	header := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
	}
	buf := []byte{1, 2, 3}
	extraHeader, bodySize := sock.beforeSend(session, len(buf))
	should.Equal(header, extraHeader)
	should.Equal(3, bodySize)
	sock.afterSend(session, 3, 0)
	buf = []byte{1, 2, 3}
	extraHeader, bodySize = sock.beforeSend(session, len(buf))
	should.Equal(header[3:], extraHeader)
	should.Equal(3, bodySize)
}

func Test_send_complete(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{TraceHeader: []byte{0xaa}}
	header := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
	}
	buf := []byte{1, 2, 3}
	extraHeader, bodySize := sock.beforeSend(session, len(buf))
	should.Equal(header, extraHeader)
	should.Equal(3, bodySize)
	sock.afterSend(session, 10, 3)
	header = []byte{
		0xde, 0xad, // magic same trace
		0x00, 0x03, // body size
	}
	buf = []byte{1, 2, 3}
	extraHeader, bodySize = sock.beforeSend(session, len(buf))
	should.Equal(header, extraHeader)
	should.Equal(3, bodySize)
}

func Test_send_buf_increased(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{TraceHeader: []byte{0xaa}}
	header := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
	}
	buf := []byte{1, 2, 3}
	extraHeader, bodySize := sock.beforeSend(session, len(buf))
	should.Equal(header, extraHeader)
	should.Equal(3, bodySize)
	sock.afterSend(session, 7, 0)
	buf = []byte{1, 2, 3, 4, 5}
	extraHeader, bodySize = sock.beforeSend(session, len(buf))
	should.Equal(header[7:], extraHeader)
	should.Equal(3, bodySize) // sent buf limited to 3, as stated in the header previously
}

func Test_send_change_trace(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{TraceHeader: []byte{0xaa}}
	header := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
	}
	buf := []byte{1, 2, 3}
	extraHeader, bodySize := sock.beforeSend(session, len(buf))
	should.Equal(header, extraHeader)
	should.Equal(3, bodySize)
	sock.afterSend(session, 10, 3)
	session.TraceHeader = []byte{0xbb}
	header = []byte{
		0xbe, 0xef,       // magic change trace
		0x00, 0x01, 0xbb, // trace header size + trace header
		0x00, 0x03,       // body size
	}
	buf = []byte{1, 2, 3}
	extraHeader, bodySize = sock.beforeSend(session, len(buf))
	should.Equal(header, extraHeader)
	should.Equal(3, bodySize)
}

func Test_recv_complete(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{}
	span := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
		0xaa, 0xbb, 0xcc,             // body
	}
	should.Equal([]byte{0xaa, 0xbb, 0xcc}, sock.onRecv(session, span))
	should.Equal([]byte{0xaa}, session.TraceHeader)
}

func Test_recv_incomplete_trace_header_size(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{}
	span := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00,
	}
	should.Nil(sock.onRecv(session, span))
	should.Nil(session.TraceHeader)
	span = []byte{
		0x01, 0xaa,       // trace header size + trace header
		0x00, 0x03,       // body size
		0xaa, 0xbb, 0xcc, // body
	}
	should.Equal([]byte{0xaa, 0xbb, 0xcc}, sock.onRecv(session, span))
	should.Equal([]byte{0xaa}, session.TraceHeader)
}

func Test_recv_incomplete_trace_header(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{}
	span := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x02, 0xaa,
	}
	should.Nil(sock.onRecv(session, span))
	should.Nil(session.TraceHeader)
	span = []byte{
		0xbb,             // trace header size + trace header
		0x00, 0x03,       // body size
		0xaa, 0xbb, 0xcc, // body
	}
	should.Equal([]byte{0xaa, 0xbb, 0xcc}, sock.onRecv(session, span))
	should.Equal([]byte{0xaa, 0xbb}, session.TraceHeader)
}

func Test_recv_incomplete_body_size(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{}
	span := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00,
	}
	should.Nil(sock.onRecv(session, span))
	should.Equal([]byte{0xaa}, session.TraceHeader)
	span = []byte{
		0x03,             // body size
		0xaa, 0xbb, 0xcc, // body
	}
	should.Equal([]byte{0xaa, 0xbb, 0xcc}, sock.onRecv(session, span))
}

func Test_recv_incomplete_body(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{}
	span := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
	}
	should.Equal([]byte{}, sock.onRecv(session, span))
	should.Equal([]byte{0xaa}, session.TraceHeader)
	span = []byte{
		0xaa, // body
	}
	should.Equal([]byte{0xaa}, sock.onRecv(session, span))
	span = []byte{
		0xbb, // body
	}
	should.Equal([]byte{0xbb}, sock.onRecv(session, span))
	span = []byte{
		0xcc, // body
	}
	should.Equal([]byte{0xcc}, sock.onRecv(session, span))
	span = []byte{
		0xde, 0xad,       // magic same trace
		0x00, 0x03,       // body size
		0xaa, 0xbb, 0xcc, // body
	}
	should.Equal([]byte{0xaa, 0xbb, 0xcc}, sock.onRecv(session, span))
}

func Test_recv_two_traces(t *testing.T) {
	should := require.New(t)
	sock := &socket{}
	session := &recording.Session{}
	span := []byte{
		0xde, 0xad, 0xbe, 0xef, 0x01, // magic init
		0x00, 0x01, 0xaa,             // trace header size + trace header
		0x00, 0x03,                   // body size
		0xaa, 0xbb, 0xcc,             // body
		0xde, 0xad,                  // magic same trace
		0x00, 0x03,                   // body size
		0xcc, 0xbb, 0xaa,             // body
	}
	should.Equal([]byte{0xaa, 0xbb, 0xcc, 0xcc, 0xbb, 0xaa}, sock.onRecv(session, span))
	should.Equal([]byte{0xaa}, session.TraceHeader)
}
