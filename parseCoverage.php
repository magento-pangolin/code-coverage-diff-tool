<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

require_once __DIR__ . '/vendor/autoload.php';

$baseCoverageDir = '';
$additionalCoverageDir = '';
$deltaDestinationPath = '';

$baseCoverage = readCoverageFromFolder($baseCoverageDir);

$deltaData = filterDataByFile($additionalCoverageDir, $baseCoverage->getData(true));

$baseCoverage->setData($deltaData);

$writeString = buildDataString($baseCoverage);
file_put_contents($deltaDestinationPath, $writeString);

printf("Coverage Diff written to $deltaDestinationPath\n");

function readCoverageFromFolder($coveragePath) {
    $fileCount = count(scandir($coveragePath));
    $currentFile = 0;
    printf("Reading base coverage...\n");

    $coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage();
    foreach (scandir($coveragePath) as $file) {
        printf("Reading ($currentFile/$fileCount)\r");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $fileCoverage = readCoverage($coveragePath . DIRECTORY_SEPARATOR . $file);
        if ($coverage->filter()->hasWhitelist() == false) {
            $coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage(null, $fileCoverage->filter());
        }
        $coverage->merge($fileCoverage);
    }
    return $coverage;

}


function readCoverage($coveragePath) {
    if (!is_file($coveragePath)) {
        return null;
    }
    $file = include($coveragePath);
    return $file;
}


function filterData($base, $delta) {
    foreach ($delta as $fileName => $lineArrays) {
        if (!isset($base[$fileName])) {
            continue;
        }
        foreach ($lineArrays as $lineNumber => $testNames) {
            //Remove line if present in delta
            unset($base[$fileName][$lineNumber]);
        }
    }
    return $base;
}

function filterDataByFile($folder, $base) {

    $baseData = $base;
    $fileCount = count(scandir($folder));
    printf("Starting coverage diff...\n");
    $currentFile = 0;

    foreach (scandir($folder) as $file) {
        $currentFile+= 1;
        printf("Comparing ($currentFile/$fileCount)\r");
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $fileCoverage = readCoverage($folder . DIRECTORY_SEPARATOR . $file);
        $baseData = filterData($baseData, $fileCoverage->getData(true));
    }

    return $baseData;
}


function buildDataString($coverage)
{
    printf("Formatting coverage for output...\n");

    $output = "<?php\n\$coverage = new SebastianBergmann\CodeCoverage\CodeCoverage;\n\$coverage->setData(\n";
    $output.= var_export($coverage->getData(), true);

    $output.= ");\n\n\$coverage->setTests(";
    $output.= var_export($coverage->getTests(), true);

    $output.= ");\n\n\$filter = \$coverage->filter();\n\$filter->setWhitelistedFiles(";
    $output.= var_export($coverage->filter()->getWhitelistedFiles(), true);

    $output .= ");\n\nreturn \$coverage;";

    return $output;
}




