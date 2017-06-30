<?php

$srcRoot = "./src";
$buildRoot = "./bin";
$testRoot = "./test";

$phar = new Phar($buildRoot . "/mda.phar",
    FilesystemIterator::CURRENT_AS_FILEINFO |       FilesystemIterator::KEY_AS_FILENAME, "mda.phar");
$phar["app.php"] = file_get_contents($srcRoot . "/app.php");
$phar["Colors.php"] = file_get_contents($srcRoot . "/Colors.php");
$phar["Download.php"] = file_get_contents($srcRoot . "/Download.php");
$phar["config.ini"] = file_get_contents($srcRoot . "/config.ini");
$phar->setStub($phar->createDefaultStub("app.php"));

$shortopts  = "";
$longopts  = array(
    "run-test",
);

$options = getopt($shortopts, $longopts);

if (isset($options['run-test'])) {
    unset($argv[0], $argv[1]);
    $args = implode(' ', $argv);
    copy($buildRoot . "/mda.phar", $testRoot . "/mda.phar");
    chdir($testRoot);
    passthru('php mda.phar ' . $args);
}
