# Koala

人生如戏，全凭演技

[Old Version](https://github.com/v2pro/koala)

# Parameters

* KOALA_INBOUND_PROTOCOL: set inbound protocol, default fastcgi
* KOALA_INBOUND_ADDR: ip:port, the address inbound server will bind to, default :2514
* KOALA_SUT_ADDR: ip:port, the address inbound will call, default 127.0.0.1:2515
* KOALA_OUTBOUND_ADDR: ip:port, the address all outgoing traffic will be redirected to, default 127.0.0.1:2516
* KOALA_LOG_FILE: STDOUT/STDERR/filepath, if using filepath, the log will rotate every hour
* KOALA_LOG_LEVEL: TRACE/DEBUG/INFO/ERROR/FATAL
* KOALA_INBOUND_READ_TIMEOUT: a duration string, set the timeout of inbound read response from sut
* KOALA_OUTBOUND_BYPASS_PORT: port, the port of outbound will bypass, eg replay a session with xdebug and pass xdebug remote port
* KOALA_GC_GLOBAL_STATUS_TIMEOUT: a duration string, set the timeout of gc for koala global status, eg thread, socket
* KOALA_REPLAYING_MATCH_STRATEGY: set outbound replaying match strategy, default use chunk match strategy, support `sim` for similarity match
* KOALA_REPLAYING_MATCH_THRESHOLD: set outbound replaying similarity match threshold
* KOALA_WITCH_ADDR: ip:port, witch: a WEB UI to make log and snapshot visible, default :8318
* KOALA_OUTBOUND_BYPASS_ADDR: ip:port or :port, split by comma, the address of outbound will be bypassed when recording or replaying a session, eg service discovery address
* KOALA_RECORD_TO_DIR: a directory, where the recorder result will write to
* KOALA_RECORD_TO_ES: the es url, it will be called when KOALA_RECORD_TO_DIR is null

# Build tags

* koala_go: for go application compiled with koala-go
* koala_replayer: enable replaying mode
* koala_recorder: enable recording mode

koala_replayer and koala_recorder can be enabled at the same time, to benchmark recording with replaying.

# Recording

![recording](https://docs.google.com/drawings/d/1IRmc6LH4tLq9l8ELF2XaGouzqr51Hb-0n2QN25zpiEg/pub?w=669&h=471)

* intercept tcp send/recv
* associate send/recv data to same thread id as "session"
* request => response => request, so we can know when a "talk" (with request/response pair) is complete
* use udp 127.127.127.127:127 to inform recorder with helper information.
* for "system under test" using thread multiplexing (one thread doing more than one thing), 
map real thread id to virtual thread id by helper information.

# Replaying

![replaying](https://docs.google.com/drawings/d/1uTW-4Hedimy4mLGTQtCG5lDLrmYfWXMZm6PfuabRdYY/pub?w=960&h=720)

replaying builds on same mechanism, but much more complex

* "system under test" is a process, "replayer inbound server" and "replayer outbound server" lives in same process. 
They are two tcp servers started by the .so loaded via LD_PRELOAD.
* session to replay is injected into the process via "replayer inbound server" tcp connection
* "replayer inbound server" call the "system under test" via tcp connection, store the "session id <=> inbound socket" mapping.
* "system under test" call external dependencies, which is intercepted to "replayer outbound server", store the "inbound socket <=> outbound socket" mapping
* "replayer outbound server" use its own socket to lookup the mapping, to find which session to replay

# Gateways

koala support two modes by different gateways (https://github.com/v2pro/koala/tree/master/gateway)

* libc: for application built with libc (c/c++/python/java)
* go: need to compile with https://github.com/v2pro/koala-go

![gateway](https://docs.google.com/drawings/d/1vhdY_RTws99Iy0UgKYmTW6vGYUymHarG1zcDsmbwLOQ/pub?w=1214&h=988)

# Real World Scenarios

* Long Connection: reusing connection sequentially is not a issue, just update the mapping
* Multiplexing: one thread handing multiple business processes at the same time, need "helper information"
* One Way Communication: request without response, need "helper information" to cut two requests out
* Greeting: protocol like mysql send greeting before request. use the ip:port or just guess, to decide if greeting is needed.

# build

* ./build.sh -> koala-replayer.so
* ./build.sh recorder -> koala-recorder.so

