<?php

require_once "phar://mda.phar/Download.php";

$downloader = new Downloader;
$downloader->interactiveDownload();
