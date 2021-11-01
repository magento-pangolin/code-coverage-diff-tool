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
    printf("Reading coverage...\n");

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.class.php';
    //INPUT RUN IF NOT PRESENT
    $run = DB::query("SELECT * FROM CC_RUN WHERE runid=%s", $runID);
    if (empty($run)) {
        DB::insert("CC_RUN", [
            "runid" => $runID
        ]);
        $cachedCCRUNID = DB::insertId();
    } else {
        $cachedCCRUNID = $run[0]['ccrunid'];
    }
    unset($run);

    //CACHE LOCAL IDS FOR EASY USE
    $cachedTests = [];
    $tests = DB::query("SELECT * FROM CC_TESTS WHERE ccrunid=%s AND testtype=%s", $cachedCCRUNID, $testType);
    foreach ($tests as $test) {
        $cachedTests[$test['testname']] = $test['testid'];
    }
    unset($tests);
    $cachedFiles = [];
    $files = DB::query("SELECT * FROM CC_FILES WHERE ccrunid=%s", $cachedCCRUNID);
    foreach ($files as $file) {
        $cachedFiles[$file['filepath']] = $file['fileid'];
    }
    unset($files);

    //ITERATE THROUGH FILES
    foreach (scandir($coveragePath) as $file) {
        printf("Reading ($currentFile/$fileCount)\r");
        $currentFile += 1;
        if (pathinfo($file)['extension'] !== 'cov') {
            continue;
        }
        $fileCoverage = readCoverage($coveragePath . DIRECTORY_SEPARATOR . $file);
        //TEST NAME INSERT
        foreach ($fileCoverage->getTests() as $testname => $content) {
            if (isset($cachedTests[$testname])) {
                continue;
            }
//            $existingTest = DB::query(
//                "SELECT * FROM CC_TESTS WHERE ccrunid=%s AND testtype=%s AND testname=%s",
//                $cachedCCRUNID, $testType, $testname
//            );
//            if (!empty($existingTest)) {
//                continue;
//            }
            DB::insert("CC_TESTS", [
                "testtype" => $testType,
                "testname" => $testname,
                "ccrunid" => $cachedCCRUNID
            ]);
            $cachedTests[$testname] = DB::insertId();
        }

        //DO FILE + LINE INSERTS
        foreach ($fileCoverage->getData(true)->lineCoverage() as $testFile => $content) {
            if (!isset($cachedFiles[$testFile])) {
                DB::insert("CC_FILES", [
                    "filepath" => $testFile,
                    "ccrunid" => $cachedCCRUNID
                ]);
                $cachedFiles[$testFile] = DB::insertId();
            }
//            $existingFile = DB::query(
//                "SELECT * FROM CC_FILES WHERE ccrunid=%s AND filepath=%s",
//                $cachedCCRUNID, $testFile
//            );
//            if (empty($existingFile)) {
//                DB::insert("CC_FILES", [
//                    "filepath" => $testFile,
//                    "ccrunid" => $cachedCCRUNID
//                ]);
//                $cachedFiles[$testFile] = DB::insertId();
//            }
            foreach ($content as $lineNumber => $tests) {
                foreach ($tests as $test) {
                    $existingLine = DB::query(
                        "SELECT * FROM CC_LINES WHERE linenumber=%s AND testid=%s AND fileid=%s AND ccrunid=%s",
                        $lineNumber, $cachedTests[$test], $cachedFiles[$testFile], $cachedCCRUNID
                    );
                    if (empty($existingLine)) {
                        DB::insert("CC_LINES", [
                            "linenumber" => $lineNumber,
                            "testid" => $cachedTests[$test],
                            "fileid" => $cachedFiles[$testFile],
                            "ccrunid" => $cachedCCRUNID
                        ]);
                    }
                }
            }
        }
        printf("Inserted from file {$file}\n");
    }
    printf("Inserted from all files in {$coveragePath}\n");
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
