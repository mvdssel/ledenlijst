<?php

function errorOccurred($msg = '') {
    header('HTTP/1.0 500 Internal Server Error');
    write($msg);
    exit(1);
}

function authFailed($msg = '') {
    header('WWW-Authenticate: Basic realm="Scouts en Gidsen Vlaanderen login"');
    header('HTTP/1.0 401 Unauthorized');
    write($msg);
    exit(1);
}

function write($msg) {
    echo "$msg<br />\r\n";
    flush();
}

function dump($var) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

