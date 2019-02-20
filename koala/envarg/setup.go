package envarg

import (
	"github.com/v2pro/plz/countlog"
	"os"
)

func SetupLogging() {
	logWriter := countlog.NewAsyncLogWriter(
		LogLevel(),
		countlog.NewFileLogOutput(LogFile()))
	switch LogFormat() {
	case "HumanReadableFormat":
		logWriter.LogFormatter = &countlog.HumanReadableFormat{
			ContextPropertyNames: []string{"threadID", "outboundSrc"},
			StringLengthCap:      1024,
		}
	case "CompactFormat":
		logWriter.LogFormatter = &countlog.CompactFormat{StringLengthCap: 512}
	default:
		os.Stderr.WriteString("unknown LogFormat: " + LogFormat() + "\n")
		os.Stderr.Sync()
		logWriter.LogFormatter = &countlog.CompactFormat{}
	}
	logWriter.EventWhitelist["event!replaying.talks_scored"] = true
	//logWriter.EventWhitelist["event!sut.opening_file"] = true
	logWriter.Start()
	countlog.LogWriters = append(countlog.LogWriters, logWriter)
}
