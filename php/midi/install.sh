#!/bin/bash

# when git clone source to local and use this script to install depends
# Install from source, not by composer

set -e
set -x

# composer.json must at root directory
# copy composer.json to current directory, change autoload path to current dir
cp ../../composer.json .
if [[ "$OSTYPE" == "linux-gnu" ]]; then
    sed -i -e "s#php/midi/src#src#g" composer.json
    sed -i -e "s#php/midi/tests#tests#g" composer.json
    sed -i -e "s#php/midi/bin#bin#g" composer.json
    sed -i -e "s#php/midi/res#res#g" composer.json
elif [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' -e "s#php/midi/src#src#g" composer.json
    sed -i '' -e "s#php/midi/tests#tests#g" composer.json
    sed -i '' -e "s#php/midi/bin#bin#g" composer.json
    sed -i '' -e "s#php/midi/res#res#g" composer.json
else
    echo "Not Support $OSTYPE"
    exit 1
fi

# composer install depends
if [ -d "vendor" ]; then
    rm -rf vendor
fi
composer install -o
