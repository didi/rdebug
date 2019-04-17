#!/bin/bash
set -e
set -x

RDEBUG=$(cd ../`dirname $0` && pwd -P)

case $1 in
    "recorder" )
        # record to file, only for testing purpose
        export GOPATH=/tmp/build-golang
        export CGO_CFLAGS="-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK"
        export CGO_CPPFLAGS="-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK"
        export CGO_CXXFLAGS="-std=c++11 -Wno-ignored-attributes"
        exec go build -tags="koala_recorder" -buildmode=c-shared -o $RDEBUG/output/libs/koala-recorder.so github.com/didi/rdebug/koala/cmd/recorder
        ;;
    "vendor" )
        if [ ! -d /tmp/build-golang/src/github.com/didi/rdebug ]; then
            mkdir -p /tmp/build-golang/src/github.com/didi
            ln -s $RDEBUG /tmp/build-golang/src/github.com/didi/rdebug
        fi
        export GOPATH=/tmp/build-golang
        if [ ! -f "$GOPATH/bin/glide" ]; then
            go get github.com/Masterminds/glide
        fi
        cd /tmp/build-golang/src/github.com/didi/rdebug/koala
        exec $GOPATH/bin/glide i
        ;;
esac

# build replayer by default
export CGO_CFLAGS="-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK -DKOALA_LIBC_TIME_HOOK -DKOALA_LIBC_PATH_HOOK"
export CGO_CPPFLAGS="-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK -DKOALA_LIBC_TIME_HOOK -DKOALA_LIBC_PATH_HOOK"
export CGO_CXXFLAGS="-std=c++11 -Wno-ignored-attributes"
go build -tags="koala_replayer" -buildmode=c-shared -o $RDEBUG/output/libs/koala-replayer.so github.com/didi/rdebug/koala/cmd/replayer
cp $RDEBUG/output/libs/koala-replayer.so $RDEBUG/php/midi/res/replayer/koala-replayer.so