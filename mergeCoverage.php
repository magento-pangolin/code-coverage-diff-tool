<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
ini_set('memory_limit', '-1');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/sharedFuncs.php';

// Define the dir that contains the cov files to be merged
define('SRC_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR);
// Define the dir that the merged cov files will be written to
define('MERGE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'merge' . DIRECTORY_SEPARATOR);
// Define the number of cov files that will be merged together
define('MERGE_SIZE', 50);

/**
 * The main entrypoint. Called at the bottom of this file.
 */
function main()
{
    if (!file_exists(MERGE_DIR)) {
        mkdir(MERGE_DIR, 0777, true);
    }

    $fileCount = count(scandir(SRC_DIR));
    $currentFile = 0;
    $counter = 0;

    $coverage = [];

    foreach (scandir(SRC_DIR) as $file) {
        printf("\nReading ($currentFile/$fileCount)\n");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }

        print_mem('readCoverageFromFolder: ' . $file);
        $fileCoverage = readCoverage(SRC_DIR . $file);
        print_mem('readCoverageFromFolder: executed readCoverage');
        $coverage = mergeCoverageData($coverage, $fileCoverage);
        print_mem('readCoverageFromFolder: merged coverage');

        if ($currentFile % MERGE_SIZE == 0) {
            writeCovFile(MERGE_DIR . 'mg' . strval($counter) . '.cov', $coverage);
            $coverage = [];
            $counter += 1;
        }
    }

    if ($currentFile % MERGE_SIZE != 0) {
        writeCovFile(MERGE_DIR . 'mg' . strval($counter) . '.cov', $coverage);
    }
}

main();
