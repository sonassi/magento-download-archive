<?php

require_once "phar://mda.phar/Download.php";
require_once "phar://mda.phar/Colors.php";

$downloader = new Downloader;
$downloader->interactiveDownload();
