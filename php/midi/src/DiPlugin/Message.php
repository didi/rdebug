<?php declare(strict_types=1);

namespace DiPlugin;

class Message
{
    const RUN_COMMAND_INVALID_PARAMS = '<error>Require uri or sessionId or file or --all option, get usage `midi help run`.</error>';

    // search command
    const SEARCH_COMMAND_NO_SESSION = '<info>Search NO sessions</info>';

    const SEARCH_COUNT_AND_SPENT = '<info>Search %d Sessions, Spent %d ms</info>';

    // doctor command
    const DOCTOR_COMMAND_WELCOME_INFO = <<<EOT
<info>
正在使用 doctor 命令检查环境:

1. 检查系统，只支持 macOS
2. 检查 PHP 扩展（Apcu、redis、Memcached），如项目中未使用，可忽略
3. 检查端口 5514、5515、5516 是否被占用
4. 如在业务模块下执行 doctor，检查 composer install 等
5. 检查是否有 biz-config 代码，目录和业务模块目录同级，如项目中未使用，可忽略
6. 检查系统版本号，当系统版本 > 10.13 时，需要 SIP 关闭

</info>
EOT;
    // TODO PATCH internal wiki url.
    const DOCTOR_COMMAND_MIDI_WIKI = 'Get Help See Internal Wiki.';
}
