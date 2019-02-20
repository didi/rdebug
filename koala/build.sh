#!/bin/bash
set -e
set -x

case $1 in
    "recorder" )
        # record to file, only for testing purpose
        export CGO_CFLAGS="-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK"
        export CGO_CPPFLAGS=$CGO_CFLAGS
        export CGO_CXXFLAGS="-std=c++11 -Wno-ignored-attributes"
        exec go build -tags="koala_recorder" -buildmode=c-shared -o ../output/libs/koala-recorder.so github.com/didi/rdebug/koala/cmd/recorder
        ;;
    "vendor" )
        if [ ! -d /tmp/build-golang/src/github.com/didi/rdebug ]; then
            mkdir -p /tmp/build-golang/src/github.com/didi/rdebug
            ln -s $PWD /tmp/build-golang/src/github.com/didi/rdebug/koala
        fi
        export GOPATH=/tmp/build-golang
        go get github.com/Masterminds/glide
        cd /tmp/build-golang/src/github.com/didi/rdebug/koala
        exec $GOPATH/bin/glide i
        ;;
esac

# build replayer by default
export CGO_CFLAGS="-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK -DKOALA_LIBC_TIME_HOOK -DKOALA_LIBC_PATH_HOOK"
export CGO_CPPFLAGS=$CGO_CFLAGS
export CGO_CXXFLAGS="-std=c++11 -Wno-ignored-attributes"
go build -tags="koala_replayer" -buildmode=c-shared -o ../output/libs/koala-replayer.so github.com/didi/rdebug/koala/cmd/replayer
cp ../output/libs/koala-replayer.so ../php/midi/res/replayer/koala-replayer.so