<?php

// DEV only
// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

require_once 'util.php';
require_once 'GroepsadminClient.class.php';
require_once 'vendor/PhpSpreadsheet/src/Bootstrap.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

const FILTERS = ['Leden', 'Leiding', 'Oudleiding'];

if (isset($_SERVER['PHP_AUTH_USER'])) {
    try {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];
        $destination = $_SERVER['DOCUMENT_ROOT'];

        file_logging($user, 'update');

        updateLedenlijst($user, $pass, $destination);
    } catch (Exception $e) {
        // print_r($e);
        authFailed('Error: Authentication failed or Error occurred');
        exit(1);
    }
} else {
    authFailed('Error: Received no Basic Authentitication credentials');
}

function updateLedenlijst($user, $pass, $destination) {
    // Prevents problems with flushing output streams
    header('Content-type: text/html; charset=utf-8');

    // Get all filter values
    logging("Logging in as $user");
    $client = new GroepsadminClient($user, $pass);
    $filterValues = $client->downloadFilters(FILTERS);

    // Init the XLS reader & object
    $objReader = IOFactory::createReader('CSV');
    $objReader->setDelimiter(';');
    $objPHPExcel = new Spreadsheet();

    // Add each of the filter values to different worksheets
    $sheetIndex = 0;
    foreach ($filterValues as $filter => &$value) {
        logging("Parsing filter $filter");

        // Write filter values to temp file
        $file = tempnam(sys_get_temp_dir(), 'ledenlijst_');
        $handle = fopen($file, "w");
        fwrite($handle, $value);

        // Add temp file contents to active worksheet
        $objReader->setSheetIndex($sheetIndex);
        $objReader->loadIntoExisting($file, $objPHPExcel); 

        // Update sheet title and add filters
        $activeSheet = $objPHPExcel->getActiveSheet();
        $activeSheet->setTitle($filter); 
        $dimension = $activeSheet->calculateWorksheetDimension();
        $activeSheet->setAutoFilter($dimension);

        // Loop clean up
        fclose($handle);
        unlink($file);
        $sheetIndex++;
    }

    // Write the XLS
    logging("Writing to filesystem");
    $objPHPExcel->setActiveSheetIndex(0);
    $objWriter = new Xlsx($objPHPExcel);
    $objWriter->save("$destination/ledenlijst.xlsx");

    logging("Success!");
    echo '<a href="/">Download ready</a>';
}
