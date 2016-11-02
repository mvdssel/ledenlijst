<?php

// DEV only
// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

require_once 'GroepsadminClient.class.php';
require_once 'vendor/autoload.php';
    use Katzgrau\KLogger\Logger;
    use Psr\Log\LogLevel;
require_once 'util.php';

$logger = new Logger(
    __DIR__.'/logs', // log destination
    LogLevel::DEBUG, // level to be logged
    ['filename' => 'ledenlijst.log'] // extra options
);

if (isset($_SERVER['PHP_AUTH_USER'])) {
    try {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        $client = new GroepsadminClient($user, $pass, $logger);

        if($client->isLoggedIn()) {
            downloadLedenlijst($logger);
        }
        else {
            authFailed('Error: Authentication failed');
        }
    } catch (Exception $e) {
        $logger->error($e);
        errorOccurred('Error: Uncaught exception occurred');
    }
} else {
    authFailed('Error: Received no credentials');
}


function downloadLedenlijst($logger) {
    $logger->debug('Starting download');

    $file = 'ledenlijst.xlsx';
    $download = 'ledenlijst ' . date('Y-m-d') . '.xlsx';
    // $download = 'ledenlijst ' . date('Y-m-d H.i.s') . '.xlsx';

    ignore_user_abort(true);
    set_time_limit(0); // disable the time limit for this script

    if ($fd = fopen ($file, "r")) {
        $fsize = filesize($file);

        header("Content-type: application/xlsx");
        header("Content-Disposition: attachment; filename=\"${download}\"");
        header("Content-length: $fsize");

        // while(!feof($fd)) {
        //     $buffer = fread($fd, 2048);
        //     echo $buffer;
        // }
    }
    else {
        throw new Exception("Could not open <$file> at {$_SERVER['DOCUMENT_ROOT']}");
    }

    fclose ($fd);

    $logger->debug('Download successful');
}
