# code-coverage-diff-tool
Reads and diffs coverage gathered via pcov or xdebug.

Resulting `.cov` file contains lines present **only** in the base and not the delta. 

Works by reading base provided and stripping away lines covered in the additional coverage provided.

## Prerequisites
* [phpcov](https://github.com/sebastianbergmann/phpcov)

## Usage

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

After completing this, go into `parseCoverage.php` and fill out the following lines:

```php
$baseCoverageDir = '';
$additionalCoverageDir = '';
$deltaDestinationPath = '';
```

Once complete, simply run via `php parseCoverage.php`, the result `.cov` file can then be generated into a coverage report.
```
php phpcov.phar merge ./deltaOutputPath/ --html=output
```
