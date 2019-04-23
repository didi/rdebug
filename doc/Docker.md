# Try Rdebug With Docker

## Build

```
$ git clone https://github.com/didi/rdebug.git
$ cd rdebug
$ docker build -t local-rdebug-docker .
``` 

## Running

Just Record:

```
$ docker run -v /tmp/recorded:/tmp/save-recorded-sessions -p 9111:9111 --rm local-rdebug-docker

# New Tab
$ curl 127.0.0.1:9111/index.php
$ curl 127.0.0.1:9111/index.php
$ ls /tmp/recorded
```

Or, Record and Replay:

```
# Enter docker by bash and start nginx & php-fpm
$ docker run -v /tmp/recorded:/tmp/save-recorded-sessions -it --rm local-rdebug-docker bash
> nohup sh start.sh &

# Record Session
> curl 127.0.0.1:9111/index.php
> curl 127.0.0.1:9111/index.php

# List recorded session files
> ls /tmp/save-recorded-sessions

# Install midi's composer dependency
> pushd /usr/local/var/midi
> bash install.sh
> popd

# Replay Session
> /usr/local/var/midi/bin/midi run -f /usr/local/var/koala/1548160113499755925-1158745
# Or, your recorded session
> /usr/local/var/midi/bin/midi run -f /tmp/save-recorded-sessions/YOUR_SESSION_FILE
```