<?php

// DEV only
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once 'GroepsadminClient.class.php';
require_once 'vendor/autoload.php';
    use Katzgrau\KLogger\Logger;
    use Psr\Log\LogLevel;
require_once 'vendor/PhpSpreadsheet/src/Bootstrap.php';
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
require_once 'util.php';

const FILTERS = ['Leden', 'Leiding', 'Oudleiding'];

$logger = new Logger(
    __DIR__.'/logs', // log destination
    LogLevel::DEBUG, // level to be logged
    ['filename' => 'ledenlijst.log'] // extra options
);

$_SERVER['PHP_AUTH_USER'] = 'mvdssel';
$_SERVER['PHP_AUTH_PW'] = 'Groepsleiding 16-17';

if (isset($_SERVER['PHP_AUTH_USER'])) {
    try {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        $client = new GroepsadminClient($user, $pass, $logger);

        if($client->isLoggedIn()) {
            updateLedenlijst($client, $logger);
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
    $objWriter->save('ledenlijst.xlsx');

    write('Success!');
    write('<a href="/">Download ready</a>');
}
