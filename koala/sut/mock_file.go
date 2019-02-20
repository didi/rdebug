package sut

import (
	"crypto/sha1"
	"encoding/base32"
	"io/ioutil"
	"github.com/v2pro/plz/countlog"
	"os"
	"github.com/didi/rdebug/koala/envarg"
	"sync"
)

var mockedFiles = map[string]bool{}
var mockedFilesMutex = &sync.Mutex{}

func init() {
	if envarg.IsReplaying() {
		if _, err := os.Stat("/tmp/koala-mocked-files"); err != nil {
			// dir not created yet, create
			err = os.Mkdir("/tmp/koala-mocked-files", 0777)
			countlog.Error("event!sut.failed to create mocked dir", "err", err)
		}
	}
}

func mockFile(content []byte) string {
	mockedFile := "/tmp/koala-mocked-files/" + hash(content)
	if isMocked(mockedFile) {
		return mockedFile
	}
	setMocked(mockedFile)
	if _, err := os.Stat(mockedFile); err == nil {
		return mockedFile
	}
	err := ioutil.WriteFile(mockedFile + ".tmp", content, 0666)
	if err != nil {
		countlog.Error("event!sut.failed to write mock file",
			"mockedFile", mockedFile, "err", err)
		return ""
	}
	err = os.Rename(mockedFile + ".tmp", mockedFile)
	if err != nil {
		countlog.Error("event!sut.failed to rename mock file tmp",
			"mockedFile", mockedFile, "err", err)
		return ""
	}
	return mockedFile
}

func hash(content []byte) string {
	h := sha1.New()
	h.Write(content)
	return "g" + base32.StdEncoding.EncodeToString(h.Sum(nil))
}

func isMocked(mockedFile string) bool {
	mockedFilesMutex.Lock()
	defer mockedFilesMutex.Unlock()
	return mockedFiles[mockedFile]
}

func setMocked(mockedFile string) {
	mockedFilesMutex.Lock()
	defer mockedFilesMutex.Unlock()
	mockedFiles[mockedFile] = true
}
