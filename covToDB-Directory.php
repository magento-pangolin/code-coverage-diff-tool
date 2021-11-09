<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main entrypoint. Called at the bottom of this file.
 */
function main($baseCoverageDir, $runID, $testType) {
    readCoverageFromFolder($baseCoverageDir, $runID, $testType);
}

/**
 * Reads each .cov file in a directory and inserts records into the db define din db.class.php
 *
 * @param string $coveragePath
 * @param string $runID
 * @return void
 */
function readCoverageFromFolder($coveragePath, $runID, $testType) {
    if (!realpath($coveragePath)) {
        printf("No coverage files found in $coveragePath\n");
        return;
    }
    $fileCount = count(scandir($coveragePath));
    $currentFile = 0;

    foreach (scandir($coveragePath) as $file) {
        printf("Reading ($currentFile/$fileCount)\r");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $output = shell_exec('php '.__DIR__.DIRECTORY_SEPARATOR."covToDB-Single.php {$coveragePath}/{$file} {$runID} {$testType}");
        printf($output."\n");
    }
    $start_time = microtime(TRUE);
    $endtime = microtime(TRUE);
    $runtime = $endtime-$start_time;
    printf("Inserted from all files in {$coveragePath}\n");
    printf("Execution took {$runtime} seconds.\n");
}

/**
 * Reads a single .cov file into memory.
 */
function readCoverage($coveragePath) {
    if (!is_file($coveragePath)) {
        return null;
    }
    $file = include($coveragePath);
    return $file;
}

if (!array_key_exists(3, $argv)) {
    printf("This script requires 3 parameters to run:\nInputDirectory RunID TestType");
} else {
    main(filter_var($argv[1], FILTER_SANITIZE_STRING),filter_var($argv[2], FILTER_SANITIZE_STRING),filter_var($argv[3], FILTER_SANITIZE_STRING));
}
