package lexer

import (
	"bufio"
	"fmt"
	"net/http"
	"strings"
	"testing"

	"github.com/stretchr/testify/require"
)

func TestHTTPLexer_Match(t *testing.T) {
	should := require.New(t)
	lexer := HTTPLexer{}

	should.True(lexer.Match([]byte("GET /hello HTTP/1.0")))
	should.False(lexer.Match([]byte("GET /hello HTTP/1.5")))
	should.False(lexer.Match([]byte("GET /helloHTTP/1.0")))
	should.False(lexer.Match([]byte("GETT /hello HTTP/1.0")))
	should.False(lexer.Match([]byte("/hello HTTP/1.0")))
}

func TestHTTPLexer_Lex(t *testing.T) {
	req := `POST /v1/geo/Fence?abc=foo HTTP/1.1
Host: 10.69.2.4:8000
Accept: */*
didi-header-rid: 6
Content-Type: application/x-www-form-urlencoded

flat=22&feature=0&sign=&version=1.0.0`

	request, err := http.ReadRequest(bufio.NewReader(strings.NewReader(req)))
	if err != nil {
		fmt.Println(err)
	}
	fmt.Println(request.Method)
}

func TestHTTPLexer_Lex2Vector(t *testing.T) {
	lexer := HTTPLexer{}
	req := `POST /v1/geo/Fence?abc=111 HTTP/1.1
Host: 100.69.238.44:8000
Accept: */*
didi-header-rid: 6
Content-Type: application/x-www-form-urlencoded

flat=22&feature=0&sign=&version=1.0.0`

	dft := Default()
	vec, _ := dft.Lex2Vector([]byte(req))
	fmt.Println(vec)
	vec, _ = lexer.Lex2Vector([]byte(req))
	fmt.Println(vec)
}
