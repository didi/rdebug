package lexer

var defaultLexer = Default()
var Lexers []ProtocolLexer

func Lex(text []byte) []string {
	for _, lexer := range Lexers  {
		if !lexer.Match(text) {
			continue
		}
		ret, err := lexer.Lex(text)
		if err == nil {
			return ret
		}
	}
	ret, _ := defaultLexer.Lex(text)
	return ret
}

func Lex2Vector(text []byte) map[string]float64 {
	for _, lexer := range Lexers  {
		if !lexer.Match(text) {
			continue
		}
		ret, err := lexer.Lex2Vector(text)
		if err == nil {
			return ret
		}
	}
	ret, _ := defaultLexer.Lex2Vector(text)
	return ret
}
