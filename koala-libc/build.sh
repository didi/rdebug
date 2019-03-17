#!/bin/bash
set -x
set -e
RDEBUG=$(cd ../`dirname $0` && pwd -P)
mkdir -p $RDEBUG/output/libs
gcc -shared -fPIC hook.c -o $RDEBUG/output/libs/koala-libc.so -ldl -std=c99
echo "Finish compiled koala-libc to $RDEBUG/output/libs/koala-libc.so"
