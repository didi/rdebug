#!/bin/bash

# Build midi.phar

set -e
set -x

# Install box for phar
# composer global require kherge/box --prefer-source

# Install depends
source install.sh

config='box.json'
output='midi.phar'

case $1 in
    "midi-diplugin" )
        config='box-diplugin.json'
        output='midi-diplugin.phar'
        ;;
esac

~/.composer/vendor/bin/box build -c $config -v
mv $output ../../output/bin/$output

## clean
rm -rf vendor
rm composer.json composer.lock