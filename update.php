<?php

// DEV only
// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

require_once 'GroepsadminClient.class.php';
require_once 'vendor/PhpSpreadsheet/src/Bootstrap.php';
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
require_once 'util.php';

define(DESTINATION_FILE, __DIR__.'/../ledenlijst.resources/ledenlijst.xlsx'); // only define allows expressions
const FILTERS = ['Leden', 'Leiding', 'Oudleiding']; // only const allows arrays

if (isset($_SERVER['PHP_AUTH_USER'])) {
    try {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        $client = new GroepsadminClient($user, $pass, $logger);

        if($client->isLoggedIn()) {
            $logger->info("$user\tUpdating ledenlijst");
            updateLedenlijst($client, $logger);
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

function updateLedenlijst($client, $logger) {
    // Prevents problems with flushing output streams
    header('Content-type: text/html; charset=utf-8');

    // Get all filter values
    $filterValues = $client->downloadFilters(FILTERS);

    // Init the XLS reader & object
    $objReader = IOFactory::createReader('CSV');
    $objReader->setDelimiter(';');
    $objPHPExcel = new Spreadsheet();

    // Add each of the filter values to different worksheets
    $sheetIndex = 0;
    foreach ($filterValues as $filter => &$value) {
        $logger->debug("Parsing filter $filter");

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
    $logger->debug("Writing to filesystem");
    $objPHPExcel->setActiveSheetIndex(0);
    $objWriter = new Xlsx($objPHPExcel);
    $objWriter->save(DESTINATION_FILE);

    write('Success!');
    write('<a href="/">Download ready</a>');

    $logger->debug('Update successful');
}
