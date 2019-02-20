package similarity

import (
	"bytes"
	"unicode"
)

type Lexer struct {
}

func (s *Lexer) Scan(text []byte) []string {
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
		chunks = append(chunks, string(text[offset+strikeStart:offset+strikeStart+strikeLen])) // readable
		offset += strikeStart + strikeLen
	}

	keyLen := len(text)
	for i := offset; i < keyLen; i += 1 {
		chunks = append(chunks, string(text[i:i+1])) // unreadable char
	}

	return chunks
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
