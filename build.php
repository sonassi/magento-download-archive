<?php

$srcRoot = "./src";
$buildRoot = "./bin";

$phar = new Phar($buildRoot . "/mda.phar",
    FilesystemIterator::CURRENT_AS_FILEINFO |       FilesystemIterator::KEY_AS_FILENAME, "mda.phar");
$phar["app.php"] = file_get_contents($srcRoot . "/app.php");
$phar["Download.php"] = file_get_contents($srcRoot . "/Download.php");
$phar["config.ini"] = file_get_contents($srcRoot . "/config.ini");
$phar->setStub($phar->createDefaultStub("app.php"));

#copy($srcRoot . "/config.ini", $buildRoot . "/config.ini");