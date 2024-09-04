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
function readCoverageFromFolder($coveragePath, $oldPath, $newPath)
{
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

        // rebuild coverage data
        $newData = [];
        foreach ($fileCoverage->getData(true)->lineCoverage() as $testFile => $content) {
            $newTestFilePath = str_replace($oldPath, $newPath, $testFile);
            $newData[$newTestFilePath] = $content;
        }
        $newProcessedData = new SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData();
        $newProcessedData->setLineCoverage($newData);

        // rebuild filter
        $filter = $fileCoverage->filter();
        call_user_func(\Closure::bind(
            function () use ($filter, $oldPath, $newPath) {
                foreach ($filter->files as $fileName => $isFile) {
                    $newFile = str_replace($oldPath, $newPath, $fileName);
                    unset($filter->files[$fileName]);
                    $filter->files[$newFile] = $isFile;
                }
                $filter->isFileCache = [];
            },
            null,
            $filter
        ));

        // grab private driver object
        $grabDriver = Closure::bind(function($fileCoverage) {
            return $fileCoverage->driver;
        }, null, get_class($fileCoverage));
        $driver = $grabDriver($fileCoverage);

        // free up memory before we rebuild the object
        unset($fileCoverage);

        // build new Coverage object as filter can no longer be replaced
        $newCoverage = new SebastianBergmann\CodeCoverage\CodeCoverage($driver, $filter);
        $newCoverage->setData($newProcessedData);

        $writer = new SebastianBergmann\CodeCoverage\Report\PHP();
        $writer->process($newCoverage, $coveragePath . DIRECTORY_SEPARATOR . $file);
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
