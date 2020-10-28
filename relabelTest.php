<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main entrypoint. Called at the bottom of this file.
 */
function main($baseCoverageDir, $outputDir, $newName) {
    readCoverageFromFolder($baseCoverageDir, $outputDir, $newName);
}

/**
 * Reads each .cov file in a directory and renames all the tests in the coverage to the given newName.
 *
 * @param string $coveragePath
 * @param string $outputDir
 * @param string $newName
 * @return void
 */
function readCoverageFromFolder($coveragePath, $outputDir, $newName) {
    if (!realpath($coveragePath)) {
        printf("No coverage files found in $coveragePath\n");
        return;
    }
    if (!realpath($outputDir)) {
        printf("Invalid output directory $coveragePath\n");
        return;
    }
    if (empty($newName)) {
        printf("New test name must be non empty\n");
        return;

    }
    $fileCount = count(scandir($coveragePath));
    $currentFile = 0;
    printf("Reading coverage...\n");

    foreach (scandir($coveragePath) as $file) {
        printf("Reading ($currentFile/$fileCount)\r");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $fileCoverage = readCoverage($coveragePath . DIRECTORY_SEPARATOR . $file);
        $newData = [];
        foreach ($fileCoverage->getData(true) as $testFile => $content) {
            $newData[$testFile] = [];
            foreach ($content as $line =>$testNames) {
                if (empty($testNames)) {
                    $newData[$testFile][$line] = [];
                    continue;
                }
                $testArray = [];
                $testArray[] = $newName;
                $newData[$testFile][$line] = $testArray;
            }

        }
        $fileCoverage->setData($newData);
        $newTestArray = [];
        $newTestArray[$newName] = ['size' => 'unknown', 'status' => -1];
        $fileCoverage->setTests($newTestArray);
        $writeString = buildDataString($fileCoverage);
        file_put_contents($outputDir.DIRECTORY_SEPARATOR.$file, $writeString);
    }
    printf("Renamed Coverage written to $outputDir\n");
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

/**
 * Generates the final delta file from an already diff'ed \CodeCoverage object.
 *
 * @param \SebastianBergmann\CodeCoverage\CodeCoverage $coverage
 * @return string
 */
function buildDataString($coverage)
{
    $output = "<?php\n\$coverage = new SebastianBergmann\CodeCoverage\CodeCoverage;\n\$coverage->setData(\n";
    $output.= var_export($coverage->getData(true), true);

    $output.= ");\n\n\$coverage->setTests(";
    $output.= var_export($coverage->getTests(true), true);

    $output.= ");\n\n\$filter = \$coverage->filter();\n\$filter->setWhitelistedFiles(";
    $output.= var_export($coverage->filter()->getWhitelistedFiles(), true);

    $output .= ");\n\nreturn \$coverage;";

    return $output;
}
if (!array_key_exists(3, $argv)) {
    printf("This script requires 3 parameters to run:\nInputDirectory OutputDirectory NewTestNames");
} else {
    main(filter_var($argv[1], FILTER_SANITIZE_STRING),filter_var($argv[2], FILTER_SANITIZE_STRING),filter_var($argv[3], FILTER_SANITIZE_STRING));
}
