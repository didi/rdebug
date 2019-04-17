package lexer

import (
	"bytes"
	"unicode"
)

type Lexer interface {
	Name() string
	Lex(req []byte) ([]string, error)
	Lex2Vector(text []byte) (map[string]float64, error)
}

type ProtocolLexer interface {
	Lexer
	Match(req []byte) bool
}

type DefaultLexer struct {
	ReadableChunkSize int
}

func (d *DefaultLexer) Name() string {
	return "DEFAULT_LEXER"
}

func (d *DefaultLexer) Match(text []byte) bool {
	return true
}

func (d *DefaultLexer) Lex(text []byte) ([]string, error) {
	var chunks []string

	offset := 0
	for {
		strikeStart, strikeLen := findReadableChunk(text[offset:])
		if strikeStart == -1 {
			break
		}
		for i := offset; i < offset+strikeStart; i += 1 {
			chunks = append(chunks, string(text[i:i+1])) // unreadable char
		}
		if strikeLen > d.ReadableChunkSize {
			strikeLen = d.ReadableChunkSize
		}
		chunks = append(chunks, string(text[offset+strikeStart:offset+strikeStart+strikeLen])) // readable
		offset += strikeStart + strikeLen
	}

	keyLen := len(text)
	for i := offset; i < keyLen; i += 1 {
		chunks = append(chunks, string(text[i:i+1])) // unreadable char
	}

	return chunks, nil
}

func (d *DefaultLexer) Lex2Vector(text []byte) (map[string]float64, error) {
	vector := make(map[string]float64, 32)

	offset := 0
	for {
		strikeStart, strikeLen := findReadableChunk(text[offset:])
		if strikeStart == -1 {
			break
		}
		// unreadable char
		for i := offset; i < offset+strikeStart; i += 1 {
			token := string(text[i:i+1])
			if _, ok := vector[token]; ok {
				vector[token] += 1
			} else {
				vector[token] = 1
			}
		}
		// readable
		if strikeLen > d.ReadableChunkSize {
			strikeLen = d.ReadableChunkSize
		}
		token := string(text[offset+strikeStart:offset+strikeStart+strikeLen])
		if _, ok := vector[token]; ok {
			vector[token] += float64(len(token))
		} else {
			vector[token] = float64(len(token))
		}
		offset += strikeStart + strikeLen
	}

	// unreadable char
	keyLen := len(text)
	for i := offset; i < keyLen; i += 1 {
		token := string(text[i:i+1])
		if _, ok := vector[token]; ok {
			vector[token] += 1
		} else {
			vector[token] = 1
		}
	}

	return vector, nil
}

func Default() *DefaultLexer {
	return &DefaultLexer{ReadableChunkSize: 128}
}

func findReadableChunk(key []byte) (int, int) {
	start := bytes.IndexFunc(key, func(r rune) bool {
		// A-Z a-z . _
		return unicode.IsNumber(r) || unicode.IsLetter(r) || r == 46 || r == 95
	})
	if start == -1 {
		return -1, -1
	}
	end := bytes.IndexFunc(key[start:], func(r rune) bool {
		return !(unicode.IsNumber(r) || unicode.IsLetter(r) || r == 46 || r == 95)
	})
	if end == -1 {
		return start, len(key) - start
	}
	return start, end
}
