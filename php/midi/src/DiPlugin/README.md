
# DiDi Plugin

DiDi Plugin used by our self. Here is an example.

We also provide some commands like `search`, which could search session by request uri, response or upstream calls.

---

DiPlugin 是滴滴内部在使用的一个插件。在 Midi 的基础上，针对内部做一些定制化的开发。

因为在滴滴内部，录制的 Session 最终会被存储在 Elastic 里，所以这边提供了 `search` 搜索命令，可以通过 URI、请求和响应的关键词 来搜索等。

DiPlugin 作为插件的一个示例，放在这里供大家参考。因为要开源，所有对代码进行部分修改，并进行脱敏处理。

---

## DiPlugin Private Config

- module-name

- enable-disf
- module-disf-name

- enable-uploader
- uploader-url

- recommend-dsl-url

- prepare-ci-system
- ci-system-path
- ci-system-git