<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
ini_set('memory_limit', '-1');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/sharedFuncs.php';

// Define the dir that contains the base cov files
define('BASE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'merge' . DIRECTORY_SEPARATOR);
// Define the dir that contains the additional cov files that will be compared against
define('ADDITIONAL_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'additional' . DIRECTORY_SEPARATOR);
// Define the dir that will contain the result delta cov and delta test files
define('DELTA_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'delta' . DIRECTORY_SEPARATOR);

/**
 * The main entrypoint. Called at the bottom of this file.
 */
function main()
{
    $baseCoverage = readCoverageFromFolder(BASE_DIR);
    $deltaData = filterDataByFile(ADDITIONAL_DIR, $baseCoverage);
    writeCovFile(DELTA_DIR . 'delta.cov', $deltaData);

    $coverageTests = coverageTests($deltaData);
    writeTestFile(DELTA_DIR . 'delta-tests.txt', $coverageTests);
}

/**
 * Reads each .cov file in a directory and merge line coverage data into an array in memory.
 *
 * @param string $coveragePath
 * @return array
 */
function readCoverageFromFolder(string  $coveragePath): array
{
    $fileCount = count(scandir($coveragePath));
    $currentFile = 0;
    $counter = 0;
    printf("\nReading base coverage...\n");

    $coverage = [];

    foreach (scandir($coveragePath) as $file) {
        printf("\nReading ($currentFile/$fileCount)\n");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }

        print_mem('readCoverageFromFolder: ' . $file);
        $fileCoverage = readCoverage($coveragePath . $file);
        print_mem('readCoverageFromFolder: executed readCoverage');
        $coverage = mergeCoverageData($coverage, $fileCoverage);
        print_mem('readCoverageFromFolder: merged coverage');
    }
    return $coverage;
}

/**
 * Removes array entries from $base data array if they are in the $delta data array.
 *
 * @param string[] $base An array of filename => line numbers => test names.
 * @param string[] $delta An array of filename => line numbers => test names.
 * @return array
 */
function filterData(array $base, array $delta): array
{
    foreach ($delta as $fileName => $lineArrays) {
        if (!isset($base[$fileName])) {
            continue;
        }
        foreach ($lineArrays as $lineNumber => $testNames) {
            if (!empty($testNames)) {
                //Remove line if present in delta
                unset($base[$fileName][$lineNumber]);
            }
        }
    }
    return $base;
}

/**
 * Reads and filters all files in $folder from the $base data array.
 *
 * @param string $folder
 * @param string[] $base
 * @return array
 */
function filterDataByFile(string $folder, array $base): array
{
    $baseData = $base;
    $fileCount = count(scandir($folder));
    printf("\nStarting coverage diff...\n");
    $currentFile = 0;

    foreach (scandir($folder) as $file) {
        $currentFile+= 1;
        printf("\nComparing ($currentFile/$fileCount)\n");
        if (!isset(pathinfo($file)['extension']) || pathinfo($file)['extension'] !== 'cov') {
            continue;
        }

        print_mem('filterDataByFile: ' . $file);
        $fileCoverage = readCoverage($folder . $file);
        print_mem('filterDataByFile: executed readCoverage');
        $baseData = filterData($baseData, $fileCoverage);
        print_mem('filterDataByFile: executed filterData');
    }

    return $baseData;
}

/**
 * Returns coverage test names referenced in coverage data from within a .cov file.
 *
 * @param array $coverageData
 * @return array
 */
function coverageTests(array $coverageData): array
{
    $testReferenceCounts = [];
    $coverageTests = [];

    foreach ($coverageData as $files) {
        foreach ($files as $lines) {
            if (empty($lines)) {
                continue;
            }
            foreach ($lines as $test) {
                if (isset($testReferenceCounts[$test])) {
                    $testReferenceCounts[$test] += 1;
                } else {
                    $testReferenceCounts[$test] = 1;
                }
            }
        }
    }

    // Sort test reference list in descending order so that we can easily pick from the top
    arsort($testReferenceCounts);

    foreach ($coverageData as $files) {
        foreach ($files as $lines) {
            if (empty($lines)) {
                continue;
            }
            $found = false;

            // Do nothing if we found a match in our minimum unique test list
            foreach ($lines as $lineTest) {
                if (in_array($lineTest, $coverageTests)) {
                    $found = true;
                    break;
                }
            }

            // If not found, we need to add a new test from top of the most referenced test list
            if (!$found) {
                foreach (array_keys($testReferenceCounts) as $refTest) {
                    if (in_array($refTest, array_keys($lines))) {
                        $coverageTests[] = $refTest;
                        unset($testReferenceCounts[$refTest]);
                        break;
                    }
                }
            }
        }
    }
    return $coverageTests;
}

main();
