package gw4libc

// #include "span.h"
import "C"
import (
	"math"
	"os"
)

func ch_span_to_bytes(span C.struct_ch_span) []byte {
	buf := (*[math.MaxInt32]byte)(span.Ptr)[:span.Len]
	return buf
}

func ch_span_to_string(span C.struct_ch_span) string {
	buf := (*[math.MaxInt32]byte)(span.Ptr)[:span.Len]
	return string(buf)
}

func ch_span_to_open_flags(span C.struct_ch_span) int {
	buf := (*[math.MaxInt32]byte)(span.Ptr)[:span.Len]
	withPlus := 0
	withoutPlus := 0
	for _, b := range buf {
		switch b {
		case 'r':
			withoutPlus = os.O_RDONLY
			withPlus = os.O_RDWR
		case 'w':
			withoutPlus = os.O_WRONLY | os.O_CREATE | os.O_TRUNC
			withPlus = os.O_RDWR | os.O_CREATE | os.O_TRUNC
		case 'a':
			withoutPlus = os.O_WRONLY | os.O_CREATE | os.O_APPEND
			withPlus = os.O_RDWR | os.O_CREATE | os.O_APPEND
		case 'b':
			// ignore
		case '+':
			return withPlus
		default:
			break
		}
	}
	return withoutPlus
}
