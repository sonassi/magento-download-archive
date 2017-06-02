<?php

class Downloader
{

    private $downloadData;
    private $downloadUrlFormat = 'https://%s:%s@www.magentocommerce.com/products/downloads';
    private $githubUrlFormat = 'https://raw.githubusercontent.com';
    private $githubDownloadsRepo = 'sonassi/magento-downloads/master';
    private $githubSelfRepo = 'sonassi/magento-download-archive/master';
    private $cacheFile = '.downloads.cache';
    private $config;
    private $downloadId = 0;
    private $downloadDir = './downloads';

    public function __construct()
    {
        $this->cwd = getcwd();
        $this->config = @parse_ini_file('config.ini');

        foreach (['ID', 'TOKEN'] as $requiredField) {
            if (!isset($this->config[$requiredField]))
                throw new Exception(sprintf('Magento %s is not set', $requiredField));
        }

        $url = sprintf($this->downloadUrlFormat, $this->config['ID'], $this->config['TOKEN']);

        // Fetch list of possible downloads
        $downloadUrl = sprintf('%s/info/json/', $url);

        if (file_exists($this->cacheFile) && (time()-filemtime($this->cacheFile) < 3600)) {
            $downloadsJson = file_get_contents($this->cacheFile);
        } else {
            $downloadsJson = file_get_contents($downloadUrl);
            file_put_contents($this->cacheFile, $downloadsJson);
        }

        $this->downloadData = json_decode($downloadsJson, true);

        if (!is_dir($this->downloadDir))
            mkdir($this->downloadDir);
    }

    public function download($filename, $destinationFile = false)
    {
        $msgPrefix = sprintf("Progess ... ");

        $filename = urlencode($filename);
        $url = sprintf($this->downloadUrlFormat, $this->config['ID'], $this->config['TOKEN']);
        $sourceFile = sprintf('%s/file/%s', $url, $filename);

        if (!$destinationFile)
            $destinationFile = sprintf('%s/%s', $this->downloadDir, $filename);

        // Try downloading from the Sonassi github repo for better performance
        $githubUrl = sprintf('%s/%s/%s', $this->githubUrlFormat, $this->githubDownloadsRepo, $filename);
        if ($this->remoteFileExists($githubUrl))
            $sourceFile = $githubUrl;

        $filesize = $this->getRemoteFileSize($sourceFile);
        $filesizeMb = round($filesize / 1024 / 1024, 2);

        printf("Downloading from %s\n", $sourceFile);

        if ($filesize == 0) {
            printf("Does the remote file exist? File size is 0, cannot download");
            return false;
        }

        if (file_exists($destinationFile) && filesize($destinationFile) == $filesize) {
            printf("\nFile already downloaded\n");
            return $destinationFile;
        }

        $remote = fopen($sourceFile, 'r');
        $local = fopen($destinationFile, 'w');

        $readBytes = 0;
        $startTime = time();
        $bufferBytes = 2048;
        while (!feof($remote)) {
            $buffer = fread($remote, $bufferBytes);
            fwrite($local, $buffer);

            $readBytes += $bufferBytes;
            $progress = round(100 * $readBytes / $filesize, 0);

            $seconds = time() - $startTime;
            $mbps = 0;
            if ($seconds > 0)
                $mbps = round(( $readBytes / 1024 / 1024 ) / $seconds, 2);

            echo str_pad(sprintf("%s %s%% of %sMB (%s MB/s)", $msgPrefix, $progress, $filesizeMb, $mbps), 40)."\r";
        }
        fclose($remote);
        fclose($local);

        return $destinationFile;
    }

    public function remoteFileExists($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($result == 200) ? true : false;
    }

    public function getRemoteFileSize($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $data = curl_exec($ch);
        curl_close($ch);

        if (preg_match('/Content-Length: (\d+)/', $data, $matches))
            $contentLength = (int)$matches[1];

        return $contentLength;
    }

    public function downloadCefull()
    {
        uksort($this->downloadData['ce-full'], 'version_compare');

        // Show all full releases
        system('clear');
        printf("Download full releases\n--\n\n");
        $all = false;
        $id = 0;
        $downloadMap = [];
        foreach ($this->downloadData['ce-full'] as $version => $releases) {
            $downloadMap[$id] = $version;
            printf(" [%d]: %s\n", $id++, $version);
        }

        $downloadMap['all'] = 'all';
        printf(" [all]: All downloads\n");

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadRelease = $downloadMap[$downloadVersion];

        // Show all versions for selected release
        system('clear');
        $downloadReleases = [ $downloadRelease ];
        if ($downloadRelease == 'all') {
            array_pop($downloadMap);
            $downloadReleases = $downloadMap;
            $all = true;
        }

        foreach ($downloadReleases as $downloadRelease) {
            printf("Download %s\n--\n\n", $downloadRelease);
            $id = 0;
            $downloadMap = [];
            foreach ($this->downloadData['ce-full'][$downloadRelease] as $release) {
                $downloadMap[$id] = $release['file_name'];
                $row = sprintf(" [%d]: %s\n", $id++, $release['name']);
                if (!$all)
                    echo $row;
            }

            $downloadMap['all'] = 'all';
            if (!$all)
                printf(" [all]: All downloads\n");

            if (!$all) {

                $downloadVersion = -1;
                while (!array_key_exists($downloadVersion, $downloadMap)) {
                    $downloadVersion = readline("\nSelect a valid option: ");
                }
                $downloadFilename = $downloadMap[$downloadVersion];
            } else {
                $downloadFilename = 'all';
            }

            system('clear');
            $downloadFilenames = [ $downloadFilename ];
            if ($downloadFilename == 'all') {
                array_pop($downloadMap);
                $downloadFilenames = $downloadMap;
            }

            foreach ($downloadFilenames as $downloadFilename) {
                printf("Downloading %s\n--\n\n", $downloadFilename);
                $downloadFile = sprintf('%s/%s', $this->downloadDir, $downloadFilename);
                if ($destinationFile = $this->download($downloadFilename, $downloadFile)) {
                    printf("File downloaded to %s\r\n", $destinationFile);
                    $errorCode = 0;
                } else {
                    printf("Failed\r\n");
                    $errorCode = 1;
                }
            }
        }

        exit($errorCode);
    }

   public function downloadOther()
    {
        uksort($this->downloadData['other'], 'version_compare');

        // Show all full releases
        system('clear');
        printf("Download other files\n--\n\n");
        $id = 0;
        $downloadMap = [];
        foreach ($this->downloadData['other'] as $version => $releases) {
            $downloadMap[$id] = $releases['file_name'];
            printf(" [%d]: %s\n", $id++, $releases['name']);
        }

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadFilename = $downloadMap[$downloadVersion];

        system('clear');
        printf("Downloading %s\n--\n\n", $downloadFilename);
        $downloadFile = sprintf('%s/%s', $this->downloadDir, $downloadFilename);
        if ($destinationFile = $this->download($downloadFilename, $downloadFile)) {
            printf("File downloaded to %s\r\n", $destinationFile);
            exit(0);
        } else {
            printf("Failed\r\n");
            exit(1);
        }
    }

    public function downloadCepatch()
    {
        uksort($this->downloadData['ce-patch'], 'version_compare');

        // Show all full releases
        system('clear');
        printf("Download Magento patches\n--\n\n");

        $id = 0;
        $downloadMap = [];

        // Attempt to auto detect Magento version
        $mageFilename = sprintf("%s/app/Mage.php", $this->cwd);
        if (file_exists($mageFilename)) {
            require_once getcwd().'/app/Mage.php';
            $versionArray = Mage::getVersionInfo();
            $versionString = sprintf("%s.%s.%s.%s", $versionArray['major'], $versionArray['minor'], $versionArray['revision'], $versionArray['patch']);
            $downloadMap[$id] = $versionString;
            printf(" [%d]: %s (auto detected)\n", $id++, $versionString);
        }

        foreach ($this->downloadData['ce-patch'] as $version => $releases) {
            $downloadMap[$id] = $version;
            printf(" [%d]: %s\n", $id++, $version);
        }

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadRelease = $downloadMap[$downloadVersion];

        // Show all patches for selected release
        system('clear');
        printf("Patches for Magento %s\n--\n\n", $downloadRelease);
        $id = 0;
        $downloadMap = [];
        foreach ($this->downloadData['ce-patch'][$downloadRelease] as $release) {
            $downloadMap[$id] = $release['file_name'];
            printf(" [%d]: %s\n", $id++, $release['name']);
        }

        $downloadMap['all'] = 'all';
        printf(" [all]: All patches\n");

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadFilename = $downloadMap[$downloadVersion];

        system('clear');
        $downloadFilenames = [ $downloadFilename ];

        if ($downloadFilename == 'all') {
            array_pop($downloadMap);
            $downloadFilenames = $downloadMap;
        }

        foreach ($downloadFilenames as $downloadFilename) {
            printf("Downloading %s\n--\n\n", $downloadFilename);
            $downloadFile = sprintf('%s/%s', $this->downloadDir, $downloadFilename);
            if ($destinationFile = $this->download($downloadFilename, $downloadFile)) {
                printf("File downloaded to %s\r\n\n", $destinationFile);
                $errorCode = 0;
            } else {
                printf("Failed\r\n");
                $errorCode = 1;
            }
        }

        exit($errorCode);
    }

    public function interactiveDownload()
    {
        system('clear');

        $downloadType = -1;
        $downloadDataKeys = array_keys($this->downloadData);

        // Show different download types
        system('clear');
        printf("Downloadable types\n\n");
        foreach ($downloadDataKeys as $id => $option) {
            printf(" [%d]: %s\n", $id, $option);
        }
        while (!array_key_exists($downloadType, $downloadDataKeys)) {
            $downloadType = readline("\nSelect a valid option: ");
        }
        $downloadType = $downloadDataKeys[$downloadType];
        $downloadMethod = sprintf('download%s', ucfirst(str_replace('-', '', $downloadType)));

        $this->{$downloadMethod}();
    }

}


