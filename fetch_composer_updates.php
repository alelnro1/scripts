<?php

/**
 * Get the installed version of a package from composer.lock
 * Purpose: The script checks the versions of packages listed in your composer.json file (both under require and require-dev sections), compares them with the latest versions available, and generates a CSV report.
 * Output: The output is a CSV file named composer_package_versions.csv with details about each package's current version, whether an update is needed, and the latest version available.

 * Usage
 * Execution: Place the script in the root directory of your Magento 2 project and run it with the command php fetch_composer_updates.php.
 * Output Review: After completion, check the composer_package_versions.csv file in the same directory for a comprehensive report on your Composer dependencies.

 * Benefits
 * Upgrade Planning: This script is particularly useful for planning upgrades, ensuring that your project's dependencies are up-to-date.
 * Transparency: By generating a clear report, it provides transparency into which packages might need attention.
 * Efficiency: The progress bar and detailed console output make the script user-friendly, especially for projects with a large number of dependencies.

 * Output
 * The CSV file includes four columns:
 * Module: The name of the Composer package.
 * Requires Upgrade: Indicates 'Yes' if the package version in composer.lock is different from the latest version available, and 'No' otherwise.
 * Current Version: The version of the package currently installed in your project.
 * Latest Version: The most recent version available for the package.
 */

// Paths to your composer.json and composer.lock files
$composerJsonPath = __DIR__ . '/composer.json';
$composerLockPath = __DIR__ . '/composer.lock';
$outputCsvPath = __DIR__ . '/composer_package_versions.csv';

// Check if the files exist
if (!file_exists($composerJsonPath) || !file_exists($composerLockPath)) {
    die("composer.json or composer.lock file not found.");
}

// Decode the JSON data from both files
$composerJsonData = json_decode(file_get_contents($composerJsonPath), true);
$composerLockData = json_decode(file_get_contents($composerLockPath), true);

/**
 * Get the installed version of a package from composer.lock
 * 
 * @param string $packageName Name of the package
 * @param array $composerLockData Data from composer.lock file
 * @return string Version of the package or 'Not found' if not found
 */
function getPackageVersion($packageName, $composerLockData) {
    foreach ($composerLockData['packages'] as $package) {
        if ($package['name'] === $packageName) {
            return $package['version'];
        }
    }
    return 'Not found';
}

/**
 * Get the latest available version of a package using Composer
 * 
 * @param string $packageName Name of the package
 * @return string Latest version of the package or 'Unknown' if unable to determine
 */
function getLatestPackageVersion($packageName) {
    $output = shell_exec("composer show $packageName --latest --no-ansi");
    if (preg_match('/latest[\s]*:[\s]*([^\s]+)/', $output, $matches)) {
        return $matches[1];
    }
    return 'Unknown';
}

/**
 * Display the progress in the console
 * 
 * @param int $current Current progress
 * @param int $total Total items to process
 */
function showProgress($current, $total, $packageName, $section) {
    $percentage = ($current / $total) * 100;
    $bar = str_repeat("=", round($percentage / 2)) . str_repeat(" ", 50 - round($percentage / 2));
    echo "\rProgress: [{$bar}] " . round($percentage, 2) . "% - Checking: $packageName ($section)";
}

/**
 * Determine whether a package needs an upgrade
 * 
 * @param string $currentVersion Current installed version of the package
 * @param string $latestVersion Latest available version of the package
 * @return string 'Yes' if needs upgrade, 'No' otherwise
 */
function needsUpgrade($currentVersion, $latestVersion) {
    return $currentVersion !== $latestVersion ? 'Yes' : 'No';
}

// Collect and process packages
$requiredPackages = array_keys($composerJsonData['require'] ?? []);
$requiredDevPackages = array_keys($composerJsonData['require-dev'] ?? []);
$allRequiredPackages = array_merge($requiredPackages, $requiredDevPackages);
$totalPackages = count($allRequiredPackages);

$f = fopen($outputCsvPath, 'w');
if ($f === false) {
    die("Unable to open file for writing: $outputCsvPath");
}
fputcsv($f, ['Module', 'Requires Upgrade', 'Current Version', 'Latest Version']);

foreach ($allRequiredPackages as $index => $packageName) {
    $currentVersion = getPackageVersion($packageName, $composerLockData);
    $latestVersion = getLatestPackageVersion($packageName);
    $requiresUpgrade = needsUpgrade($currentVersion, $latestVersion);
    $section = in_array($packageName, $requiredPackages) ? 'require' : 'require-dev';

    fputcsv($f, [$packageName, $requiresUpgrade, $currentVersion, $latestVersion]);

    // Update progress with current module and section
    showProgress($index + 1, $totalPackages, $packageName, $section);
}

fclose($f);
echo "\nCSV file generated: $outputCsvPath\n";
