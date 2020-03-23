<?php

/**
 * This script finds all unique test names mentioned with coverage in a .cov file
 */

require_once __DIR__ . '/vendor/autoload.php';

// Change this path to point to a valid .cov file
require_once("/Users/treece/tmp/mtf-minus-mftf-minus-integration/mtf-minus-mftf-minus-integration.cov");

$uniqueTests = [];

foreach ($coverage->getData() as $files) {
    foreach ($files as $line) {
        if (empty($line)) {
            continue;
        }
        foreach ($line as $test => $name) {
            $uniqueTests[$name] = true;
        }
    }
}

$testsString = implode("\n", array_keys($uniqueTests));

return;
