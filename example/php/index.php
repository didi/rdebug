<?php

function curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
}

echo "Hello, Midi!\n";
$url = 'http://php.net/get-involved.php';
echo substr(curl($url), 0, 100);
