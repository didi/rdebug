# 录制流量

## 一、思路

注入 so 到 php-fpm，异步录制流量并存储，尽可能的减少对程序执行时间的影响。

注入 so 的方式，在 macOS 系统下通过 `DYLD_INSERT_LIBRARIES`，Linux 系统下通过 `LD_PRELOAD` 来实现。

简单示例：

```
# macOS
$ DYLD_INSERT_LIBRARIES="/usr/local/var/koala/koala-libc.so:/usr/lib/libcurl.dylib" DYLD_FORCE_FLAT_NAMESPACE="y" LC_CTYPE="C" KOALA_SO=/usr/local/var/koala/koala-recorder.so KOALA_RECORD_TO_DIR=/usr/local/var/koala /usr/local/sbin/php-fpm

# or, Linux
$ LD_PRELOAD="/usr/local/var/koala/koala-libc.so /usr/lib64/libcurl.so.4" LC_CTYPE="C" KOALA_SO=/usr/local/var/koala/koala-recorder.so KOALA_RECORD_TO_DIR=/usr/local/var/koala /usr/local/sbin/php-fpm
```

示例见 [PHP DEMO](./../../example/php/README.md)。

在滴滴内部，每个模块会有 1-2 台机器，进行线上环境录制。录制已经在生产环境使用。

## 二、环境变量

录制使用的环境变量有：

`KOALA_SO`: 值是 koala-recorder.so 文件的路径，用于 koala-libc.so 读取此环境变量，来动态加载 so。

`KOALA_RECORD_TO_DIR`: 值是存储录制流量的目录，录制好的流量，将以文件的形式，存储在这个目录下。

`KOALA_RECORD_TO_ES`: 与 `KOALA_RECORD_TO_DIR` 相对，两者取其一，这个环境变量指定存储 ES 的 Url，即把录制好的流量，通过这个 Url 写入到 ES 里。

具体代码实现，可查看 `koala/cmd/recorder/main.go`。

## 三、自定义录制

Koala 是一个库，`koala/cmd/recorder/main.go` 是在 Koala 基础上实现的一个简单版本的录制。

可参考 `koala/cmd/recorder/main.go` 代码，实现自定义的 recorder。

譬如，对录制的流量进行过滤(只录制指定文件、指定端口的流量等)、控制不同接口的录制频率、使用其他存储方式等。

## 四、录制支持

目前录制支持 HTTP、Redis、MySQL、Apcu、UDP、Thrift 等协议。

HTTP、Redis、MySQL、UDP、Thrift 属于网络流量录制，与之类似的，都可以录制。

Apcu 录制，需要对 Apcu 接口进行封装来实现录制，见示例代码 [DEMO](./../../example/php/apcu.php)。

## 五、注意事项

- 划分多个请求的流量

通常 php-fpm 是 master & worker 模式，worker 是单进程单线程，串行处理请求。

Nginx 和 php-fpm 之间使用 fastcgi 协议。

所以，请求之间可以通过 fastcgi 开始请求的协议来划分请求。

下面是一个 fastcgi 协议的示例，设置 sut.InboundRequestPrefix 为 fastcgi 开始请求的协议前两个字节，来辅助判断是否来新请求。

```golang
// 文件 `koala/cmd/recorder/main.go`
protocol := envarg.GetenvFromC("KOALA_INBOUND_PROTOCOL")
if protocol == "fastcgi" || protocol == "" {
    sut.InboundRequestPrefix = []byte{1, 1}
}
```

如果来新请求，就结束上一次请求的录制。

所以，在录制的时候，第一个请求访问后，并没有发现有流量被录制下来。

当第二个请求过来时，才会结束第一个请求的录制，并存储录下来的流量。

如果是存储到文件的话，第二个请求访问时，才会看到第一个请求的录制文件。


