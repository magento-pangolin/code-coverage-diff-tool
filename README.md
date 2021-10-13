# code-coverage-diff-tool
Reads and diffs coverage gathered via pcov or xdebug.

Resulting `.cov` file contains lines present **only** in the base and not the delta. 

Works by reading base provided and stripping away lines covered in the additional coverage provided.

## Prerequisites
* [phpcov](https://github.com/sebastianbergmann/phpcov)

## Setup
Organize the following:
* Folder with baseline .cov files
* Folder with additional .cov files (to compare against)
* Clone of Magento codebase used to generate the .cov files

Before proceeding, make sure the .cov files contents are pointed at a real path; check line 4 of any .cov file:
```php
'/magento2ce/app/code/Magento/.../.../.../ClassTested.php' => 
```
Ensure the above line is pointed at a real Magento codebase before proceeding. Use a recursive replace to correct the path if necessary:
```bash
find ./coveragePath -name '*.cov' -print0 | xargs -0 sed -i "" "s|pathToFind|pathToReplace|g"
```

## Scripts

* findCoverageAndTestDiff.php
* findUniqueTests.php
* mergeCoverage.php
* parseCoverage.php
* relabelFilePaths.php
* relabelTest.php

## Usage

### parseCoverage.php
Fill out the following lines in `parseCoverage.php`:

```php
$baseCoverageDir = '';
$additionalCoverageDir = '';
$deltaDestinationPath = '';
```

Once complete, simply run via `php parseCoverage.php`, the result `.cov` file can then be generated into a coverage report.
```
php phpcov.phar merge ./deltaOutputPath/ --html=output
```

### mergeCoverage.php
Define SRC_DIR, MERGE_DIR, and MERGE_SIZE in `mergeCoverage.php`, and run script to merge cov files in `SRC_DIR`
with merge size `MERGE_SIZE` and generate merged cov files in `MERGE_DIR`.
```
php mergeCoverage.php
```

### findCoverageAndTestDiff.php
Define `BASE_DIR`, `ADDITIONAL_DIR`, and `DELTA_DIR` in `findCoverageAndTestDiff.php`, and run script to find the coverage and test diff between base and additional. 
The results will be saved in `DELTA_DIR`. It can be used wih `mergeCoverage.php`.
```
php findCoverageAndTestDiff.php
```