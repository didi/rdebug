package main

import (
	_ "github.com/didi/rdebug/koala/gateway/gw4libc"
	"github.com/didi/rdebug/koala/envarg"
	"github.com/v2pro/plz/witch"
)

func init() {
	envarg.SetupLogging()
	addrStr := envarg.GetenvFromC("KOALA_WITCH_ADDR")
	if addrStr == "" {
		addrStr = ":8318"
	}
	witch.Start(addrStr)
}

func main() {
}
