<?php

function authFailed($msg = '') {
    header('WWW-Authenticate: Basic realm="Scouts en Gidsen Vlaanderen login"');
    header('HTTP/1.0 401 Unauthorized');
    logging($msg);
    exit(1);
}

function logging($msg) {
    echo "$msg<br />\r\n";
    flush();
}

function file_logging($user, $msg) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . sprintf('% -25s', $user) . ' ' . $msg;
    $file = file_put_contents('ledenlijst.log', $log . PHP_EOL , FILE_APPEND);
    fclose($file);
}

function dump($var) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

