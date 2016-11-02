<?php

// DEV only
// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

require_once 'GroepsadminClient.class.php';
require_once 'util.php';

define(SOURCE_FILE, __DIR__.'/../ledenlijst.resources/ledenlijst.xlsx');

if (isset($_SERVER['PHP_AUTH_USER'])) {
    try {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        $client = new GroepsadminClient($user, $pass, $logger);

        if($client->isLoggedIn()) {
            $logger->info("$user\tDownloading ledenlijst");
            downloadLedenlijst($logger);
        }
        else {
            authFailed('Authentication failed');
        }
    } catch (Exception $e) {
        errorOccurred('Uncaught exception occurred', $logger, $e);
    }
} else {
    authFailed('Received no credentials');
}


function downloadLedenlijst($logger) {
    $download = 'ledenlijst ' . date('Y-m-d') . '.xlsx';

    ignore_user_abort(true);
    set_time_limit(0); // disable the time limit for this script

    if ($fd = fopen (SOURCE_FILE, "r")) {
        $fsize = filesize(SOURCE_FILE);

        header("Content-type: application/xlsx");
        header("Content-Disposition: attachment; filename=\"${download}\"");
        header("Content-length: $fsize");

        while(!feof($fd)) {
            $buffer = fread($fd, 2048);
            echo $buffer;
        }
    }
    else {
        errorOccurred("Could not open ledenlijst", $logger, 'Could not open xlsx file '.SOURCE_FILE);
    }

    fclose ($fd);

    $logger->debug('Download successful');
}
