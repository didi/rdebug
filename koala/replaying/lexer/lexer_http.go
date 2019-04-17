// simple protocol lexer, parse and use to add vector's weight
// keep balance between preciseness and speed

package lexer

import (
	"bytes"
	"regexp"
	"strings"
)

var httpReqRegex = regexp.MustCompile(`^(GET|POST|PUT|DELETE|HEAD|OPTIONS|TRACE|CONNECT) (.*) HTTP/1\.[10]`)

type HTTPLexer struct {
	DefaultLexer
}

func (h *HTTPLexer) Name() string {
	return "HTTP_LEXER"
}

func (h *HTTPLexer) Match(req []byte) bool {
	return httpReqRegex.Match(req)
}

func (h *HTTPLexer) Lex(text []byte) ([]string, error) {
	return h.DefaultLexer.Lex(text)
}

// request line(uri & path) have more weight: weight * headerLines
func (h *HTTPLexer) Lex2Vector(text []byte) (map[string]float64, error) {
	headerLines := 0
	valuableTokens := []string{}

	reqLine := text
	idx := bytes.IndexByte(reqLine, '\n')
	if idx > 0 {
		header := reqLine[idx+1:]
		reqLine = reqLine[:idx]
		for len(header) > 0 {
			i := bytes.IndexByte(header, '\n')
			if i < 3 {
				break
			}
			headerLines++
			header = header[i+1:]
		}
	}

	strReqLine := strings.TrimSpace(string(reqLine))
	_, uri, _, ok := parseRequestLine(strReqLine)
	if ok {
		valuableTokens = append(valuableTokens, uri)
		for _, path := range strings.Split(uri, "/") {
			if len(path) > 0 {
				valuableTokens = append(valuableTokens, path)
			}
		}
	} else {
		valuableTokens = append(valuableTokens, strReqLine)
	}

	vector, _ := h.DefaultLexer.Lex2Vector(text)
	for _, token := range valuableTokens {
		addWeight := float64(len(token)*(1+headerLines))
		if _, ok := vector[token]; ok {
			vector[token] += addWeight
		} else {
			vector[token] = addWeight
		}
	}

	return vector, nil
}

func NewHTTPLexer() *HTTPLexer {
	return &HTTPLexer{DefaultLexer{ReadableChunkSize:128}}
}

func parseRequestLine(line string) (method, requestURI, proto string, ok bool) {
	s1 := strings.Index(line, " ")
	s2 := strings.Index(line[s1+1:], " ")
	if s1 < 0 || s2 < 0 {
		return
	}
	s2 += s1 + 1

	uri := line[s1+1 : s2]
	t2 := strings.Index(uri, "?")
	if t2 > 0 {
		uri = uri[:t2]
	}
	return line[:s1], uri, line[s2+1:], true
}