<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Reads a single .cov file into memory.
 *
 * @param string $coveragePath
 * @return array
 */
function readCoverage(string $coveragePath): array
{
    if (!is_file($coveragePath)) {
        return [];
    }
    $file = include($coveragePath);
    print_mem('readCoverage: ' . $coveragePath);

    $data = $file->getData(true)->lineCoverage();
    print_mem('readCoverage: read data');

    // Trim irrelevant data
    foreach ($data as $filepath => $lines) {
        if (empty($lines)) {
            unset($data[$filepath]);
            continue;
        }
        foreach ($lines as $line => $tests) {
            if (empty($tests)) {
                unset($data[$filepath][$line]);
                if (empty($data[$filepath])) {
                    unset($data[$filepath]);
                }
            }
        }
    }
    print_mem('readCoverage: trimmed irrelevant data');

    $file = null;
    print_mem('readCoverage: unset coverage');

    return $data;
}

/**
 * Merge line coverage array from $newData into $oldData and return merged result.
 *
 * @param array $oldData
 * @param array $newData
 * @return array
 */
function mergeCoverageData(array $oldData, array $newData): array
{
    foreach ($newData as $file => $lines) {
        if (!isset($oldData[$file])) {
            $oldData[$file] = $lines;

            continue;
        }

        // we should compare the lines if any of two contains data
        $compareLineNumbers = array_unique(
            array_merge(
                array_keys($oldData[$file]),
                array_keys($newData[$file])
            )
        );

        foreach ($compareLineNumbers as $line) {
            if (!isset($newData[$file][$line])) {
                continue;
            }
            if (!isset($oldData[$file][$line])) {
                $oldData[$file][$line] = $newData[$file][$line];
            } elseif (is_array($oldData[$file][$line])) {
                $oldData[$file][$line] = array_unique(
                    array_merge($oldData[$file][$line], $newData[$file][$line])
                );
            }
        }
    }
    return $oldData;
}

/**
 * Print current memory usage and peak memory usage.
 *
 * @param string $mark
 * @return void
 */
function print_mem(string $mark): void
{
    echo "\n$mark: ";

    // Currently used memory
    $mem_usage = memory_get_usage();
    // Peak memory usage
    $mem_peak = memory_get_peak_usage();

    echo 'Memory Usage ' . round($mem_usage / 1048576) . 'MB, ';
    echo 'Peak Memory Usage ' . round($mem_peak / 1048576) . 'MB.';
}

/**
 * Write coverage data from an array into a cov file.
 *
 * @param string $filepath
 * @param array  $data
 * @return void
 */
function writeCovFile(string $filepath, array $data): void
{
    if (!empty($data)) {
        $filter = new \SebastianBergmann\CodeCoverage\Filter;
        $coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage(
            (new \SebastianBergmann\CodeCoverage\Driver\Selector)->forLineCoverage($filter),
            $filter
        );

        $processedData = new \SebastianBergmann\CodeCoverage\ProcessedCodeCoverageData();
        $processedData->setLineCoverage($data);
        $coverage->setData($processedData);

        $writer = new \SebastianBergmann\CodeCoverage\Report\PHP();
        $writer->process($coverage, $filepath);
        printf("\nCoverage data written to {$filepath}\n");
    }
}

/**
 * Write test names from an array into a file that has one test name per line.
 *
 * @param string $filepath
 * @param array $tests
 * @return void
 */
function writeTestFile(string $filepath, array $tests): void
{
    if (!empty($tests)) {
        $writeString = implode("\n", $tests);
        file_put_contents($filepath, $writeString);
        printf("\nCoverage tests written to {$filepath}\n");
    }
}
