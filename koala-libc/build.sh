#!/bin/bash
set -x
set -e
mkdir -p ../output/libs
gcc -shared -fPIC hook.c -o ../output/libs/koala-libc.so -ldl -std=c99
echo "compiled to ../output/libs/koala-libc.so"
