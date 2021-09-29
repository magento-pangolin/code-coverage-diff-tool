<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/vendor/autoload.php';

$baseCoverageDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'base';
$additionalCoverageDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'additional';
$deltaCovDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'delta-cov';
$deltaTestDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'delta-test';
$moduleListFilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'modulelist.txt';

$modules = explode("\n", file_get_contents($moduleListFilePath));
sort($modules);

$allTests = [];

$time_pre = microtime(true);
foreach ($modules as $module) {
    $baseCoverage = readCoverageFromFolder(
        $baseCoverageDir,
        'app/code/Magento/' . $module . '/'
    );
    $deltaData = filterDataByFile(
        $additionalCoverageDir,
        $baseCoverage->getData(true),
        'app/code/Magento/' . $module . '/'
    );
    $baseCoverage->setData($deltaData);

    $writeString = buildDataString($baseCoverage);
    file_put_contents($deltaCovDir . '/' . $module . '.cov', $writeString);
    printf("\nCoverage diff written to {$deltaCovDir}/{$module}.cov\n");

    $coverageTests = coverageTests($deltaData);
    if (!empty($coverageTests)) {
        $allTests = array_merge($allTests, $coverageTests);
        $writeString = implode("\n", $coverageTests);
        file_put_contents($deltaTestDir . '/' . $module . '-test.txt', $writeString);
        printf("\nCoverage tests written to {$deltaTestDir}/{$module}-test.txt\n");
    }
}
$time_post = microtime(true);
$exec_time = $time_post - $time_pre;
echo "\nExecution time " . $exec_time . "seconds";

$writeString = implode("\n", array_unique($allTests));
file_put_contents($deltaTestDir . '/ALL-tests.txt', $writeString);
printf("\nAll coverage tests written to {$deltaTestDir}/ALL-test.txt\n");

/**
 * Reads each .cov file in a directory and merge that file's contents into a single \CodeCoverage
 * class instance in memory.
 *
 * @param string $coveragePath
 * @param string $modulePattern
 * @return \SebastianBergmann\CodeCoverage\CodeCoverage
 */
function readCoverageFromFolder(string  $coveragePath, string $modulePattern)
{
    $fileCount = count(scandir($coveragePath));
    $currentFile = 0;
    printf("\nReading base coverage...\n");

    $coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage();
    foreach (scandir($coveragePath) as $file) {
        printf("\nReading ($currentFile/$fileCount)\n");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $fileCoverage = readCoverage($coveragePath . DIRECTORY_SEPARATOR . $file);
        if ($coverage->filter()->hasWhitelist() == false) {
            $coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage(null, $fileCoverage->filter());
        }

        $coverage->merge(filterCoverageByModule($fileCoverage, $modulePattern));
    }
    return $coverage;
}

/**
 * Reads a single .cov file into memory.
 */
function readCoverage($coveragePath)
{
    if (!is_file($coveragePath)) {
        return null;
    }
    $file = include($coveragePath);
    return $file;
}

/**
 * Removes array entries from $base data array if they are in the $delta data array.
 *
 * @param string[] $base A mapping of filename => line numbers => test names. Comes from \CodeCoverage class.
 * @param string[] $delta A mapping of filename => line numbers => test names. Comes from \CodeCoverage class.
 * @return mixed
 */
function filterData($base, $delta)
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
 * @param string $modulePattern
 * @return mixed
 */
function filterDataByFile($folder, $base, $modulePattern)
{
    $baseData = $base;
    $fileCount = count(scandir($folder));
    printf("\nStarting coverage diff...\n");
    $currentFile = 0;

    foreach (scandir($folder) as $file) {
        $currentFile+= 1;
        printf("\nComparing ($currentFile/$fileCount)\n");
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $fileCoverage = readCoverage($folder . DIRECTORY_SEPARATOR . $file);
        $fileCoverage = filterCoverageByModule($fileCoverage, $modulePattern);
        $baseData = filterData($baseData, $fileCoverage->getData(true));
    }

    return $baseData;
}

/**
 * Generates the final delta file from an already diff'ed \CodeCoverage object.
 *
 * @param \SebastianBergmann\CodeCoverage\CodeCoverage $coverage
 * @return string
 */
function buildDataString($coverage)
{
    printf("\nFormatting coverage for output...\n");

    $output = "<?php\n\$coverage = new SebastianBergmann\CodeCoverage\CodeCoverage;\n\$coverage->setData(\n";
    $output.= var_export($coverage->getData(), true);

    $output.= ");\n\n\$coverage->setTests(";
    $output.= var_export($coverage->getTests(), true);

    $output.= ");\n\n\$filter = \$coverage->filter();\n\$filter->setWhitelistedFiles(";
    $output.= var_export($coverage->filter()->getWhitelistedFiles(), true);

    $output .= ");\n\nreturn \$coverage;";

    return $output;
}

/**
 * Trims and returns CodeCoverage matching a particular path pattern. e.g. module name
 *
 * @param \SebastianBergmann\CodeCoverage\CodeCoverage $coverage
 * @param string $modulePattern
 * @return \SebastianBergmann\CodeCoverage\CodeCoverage
 */
function filterCoverageByModule($coverage, $modulePattern)
{
    $data = $coverage->getData(true);
    foreach (array_keys($data) as $filepath) {
        if (strstr($filepath, $modulePattern) === false) {
            unset($data[$filepath]);
        }
    }
    $coverage->setData($data);
    return $coverage;
}

/**
 * Returns coverage test names referenced in coverage data from within a .cov file.
 *
 * @param array $coverageData
 * @return array
 */
function coverageTests($coverageData)
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
