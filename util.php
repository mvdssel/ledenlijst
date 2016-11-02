<?php

require_once 'vendor/autoload.php';
    use Katzgrau\KLogger\Logger;
    use Psr\Log\LogLevel;

$logger = new Logger(
    __DIR__.'/../ledenlijst.resources', // log destination
    LogLevel::DEBUG, // level to be logged
    [
        'filename' => 'ledenlijst.log'
    ]
);

function errorOccurred($msg, $logger, $e = '') {
    if($e === '') $e = $msg;
    $logger->error($e);

    header('HTTP/1.0 500 Internal Server Error');
    write("Error occurred: $msg");
    exit(1);
}

function authFailed($msg = '') {
    header('WWW-Authenticate: Basic realm="Scouts en Gidsen Vlaanderen login"');
    header('HTTP/1.0 401 Unauthorized');
    write("Authentication failed: $msg");
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

