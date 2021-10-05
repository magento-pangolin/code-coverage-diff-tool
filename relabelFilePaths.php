<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main entrypoint. Called at the bottom of this file.
 */
function main($baseCoverageDir, $oldPath, $newPath) {
    readCoverageFromFolder($baseCoverageDir, $oldPath, $newPath);
}

/**
 * Reads each .cov file in a directory and replaces $oldPath with $newPath in the covered files.
 *
 * @param string $coveragePath
 * @param string $oldPath
 * @param string $newPath
 * @return void
 */
function readCoverageFromFolder($coveragePath, $oldPath, $newPath) {
    if (!realpath($coveragePath)) {
        printf("No coverage files found in $coveragePath\n");
        return;
    }
    $fileCount = count(scandir($coveragePath));
    $currentFile = 0;
    printf("Reading coverage...\n");

    foreach (scandir($coveragePath) as $file) {
        printf("Reading ($currentFile/$fileCount)\r");
        $currentFile += 1;
        if (pathinfo($coveragePath . $file)['extension'] !== 'cov') {
            printf("Skipping file $coveragePath . $file\r");
            continue;
        }
        $fileCoverage = readCoverage($coveragePath . DIRECTORY_SEPARATOR . $file);
        $newData = [];
        foreach ($fileCoverage->getData(true)->lineCoverage() as $testFile => $content) {
            $newTestFilePath = str_replace($oldPath, $newPath, $testFile);
            $newData[$newTestFilePath] = $content;
        }
        $newProcessedData = new \SebastianBergmann\CodeCoverage\ProcessedCodeCoverageData();
        $newProcessedData->setLineCoverage($newData);
        $fileCoverage->setData($newProcessedData);
        $writer = new SebastianBergmann\CodeCoverage\Report\PHP();
        $writer->process($fileCoverage, $coveragePath . $file);
    }
    printf("All .cov file paths changed in $coveragePath\n");
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
    printf("This script requires 3 parameters to run:\nInputDirectory OldPath NewPath");
} else {
    main(filter_var($argv[1], FILTER_SANITIZE_STRING),filter_var($argv[2], FILTER_SANITIZE_STRING),filter_var($argv[3], FILTER_SANITIZE_STRING));
}
