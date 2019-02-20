<?php
error_reporting(E_ALL & ~E_NOTICE);

ini_set('MAX_EXECUTION_TIME', -1);
ini_set("memory_limit", "-1");

define('ROOT_PATH', dirname(__DIR__));
define('DR', DIRECTORY_SEPARATOR);

// check php
if ((int)phpversion()[0] < 7) {
    fwrite(STDERR, 'Midi requires PHP version 7 or greater.');
    exit(1);
}

// check autoload
function includeIfExists($file)
{
    return file_exists($file) ? include $file : false;
}

if ((!$loader = includeIfExists(__DIR__ . '/../vendor/autoload.php')) && (!$loader = includeIfExists(__DIR__ . '/../../../autoload.php'))) {
    echo 'You must set up the project dependencies using `composer install`' . PHP_EOL .
        'See https://getcomposer.org/download/ for instructions on installing Composer' . PHP_EOL;
    exit(1);
}

return $loader;
