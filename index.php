<?php

// DEV only
// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

require_once 'util.php';
require_once 'GroepsadminClient.class.php';

if (isset($_SERVER['PHP_AUTH_USER'])) {
    // Try to login to SeGVl
    try {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        file_logging($user, 'download');

        $client = new GroepsadminClient($user, $pass); // this will throw if user is not Leiding
    } catch (Exception $e) {
        // print_r($e);
        authFailed('Error: Authentication to Scouts en Gidsen Vlaanderen failed');
    }

    // Try to push the ledenlijst.xlsx file
    try {
        downloadLedenlijst();
    } catch (Exception $e) {
        // print_r($e);
        logging('Error: Failed to open ledenlijst');
    }
} else {
    authFailed('Error: Received no Basic Authentitication credentials');
}


function downloadLedenlijst() {
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

        while(!feof($fd)) {
            $buffer = fread($fd, 2048);
            echo $buffer;
        }
    }
    else {
        throw new Exception("Could not open <$file> at {$_SERVER['DOCUMENT_ROOT']}");
    }

    fclose ($fd);
}
