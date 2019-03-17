#!/bin/bash
set -x
set -e

mkdir -p /opt/bitnami/nginx/logs
mkdir -p /usr/local/var/run
mkdir -p /tmp/save-recorded-sessions
mkdir -p /tmp/koala-mocked-files

exec /usr/bin/supervisord -n -c /usr/local/var/koala/supervisor.conf
