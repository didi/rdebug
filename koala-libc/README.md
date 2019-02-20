# koala-libc

[Old Version](https://github.com/v2pro/koala-libc)

If koala-recorder.so is loaded on php-fpm master process, fork() will break golang.
Use koala-libc.so to load koala-recorder.so in the child process to circumvent this problem. 

```
# compile https://github.com/v2pro/koala/tree/master/gateway/gw4libc to ~/koala-recorder.so
# compile https://github.com/v2pro/koala-libc to ~/koala-libc.so
KOALA_SO=~/koala-recorder.so LD_PRELOAD="~/koala-libc.so /usr/lib/x86_64-linux-gnu/libcurl.so.4" /usr/sbin/php-fpm7.0 -F
# ~/koala-libc.so will be loaded in master process
# ~/koala-recorder.so will be loaded in child process, at the first call to accept()
```

# build

* ./build.sh -> koala-libc.so

