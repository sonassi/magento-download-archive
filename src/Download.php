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
    private $log = [];

    public function __construct()
    {
        $this->cwd = getcwd();
        $this->config = @parse_ini_file('config.ini');
        $this->colors = new Colors();
        $this->cacheFile = $this->cwd . '/' . $this->cacheFile;

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
            @mkdir($this->downloadDir);
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
        $this->printLog();
        printf("Download full releases\n--\n\n");
        $all = false;
        $id = 0;
        $downloadMap = [];
        foreach ($this->downloadData['ce-full'] as $version => $releases) {
            $downloadMap[$id] = $version;
            $option = sprintf(" [%d]:", $id++);
            printf("%s %s\n", str_pad($option, 8), $version);
        }

        $downloadMap['a'] = 'a';
        $downloadMap['q'] = 'q';
        printf("\n [a]:    All downloads\n");
        printf("\n [q]:    Quit\n");

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadRelease = $downloadMap[$downloadVersion];

        // Show all versions for selected release
        system('clear');
        $this->printLog();
        $downloadReleases = [ $downloadRelease ];
        switch ($downloadRelease) {
            case 'a':
                $remove = preg_grep('/[^0-9]+/', array_keys($downloadMap));
                $downloadReleases = array_diff_key($downloadMap, array_flip($remove));
                $all = true;
                break;
            case 'q':
                exit;
                break;
        }

        foreach ($downloadReleases as $downloadRelease) {
            printf("Download %s\n--\n\n", $downloadRelease);
            $id = 0;
            $downloadMap = [];
            foreach ($this->downloadData['ce-full'][$downloadRelease] as $release) {
                $downloadMap[$id] = $release['file_name'];
                $option = sprintf(" [%d]:", $id++);
                $row = sprintf("%s %s\n", str_pad($option, 8), $release['name']);
                if (!$all)
                    echo $row;
            }

            $downloadMap['a'] = 'a';
            $downloadMap['q'] = 'q';
            if (!$all)
                printf("\n [a]:    All downloads\n");

            printf("\n [q]:    Quit\n");

            if (!$all) {

                $downloadVersion = -1;
                while (!array_key_exists($downloadVersion, $downloadMap)) {
                    $downloadVersion = readline("\nSelect a valid option: ");
                }
                $downloadFilename = $downloadMap[$downloadVersion];
            } else {
                $downloadFilename = 'a';
            }

            system('clear');
            $downloadFilenames = [ $downloadFilename ];
            switch ($downloadFilename) {
                case 'a':
                    $remove = preg_grep('/[^0-9]+/', array_keys($downloadMap));
                    $downloadFilenames = array_diff_key($downloadMap, array_flip($remove));
                    $all = true;
                    break;
                case 'q':
                    exit;
                    break;
            }

            foreach ($downloadFilenames as $downloadFilename) {
                printf("\nDownloading %s\n--\n\n", $downloadFilename);
                $downloadFile = sprintf('%s/%s', $this->downloadDir, $downloadFilename);
                if ($destinationFile = $this->download($downloadFilename, $downloadFile)) {
                    $this->log[] = sprintf("File downloaded to %s\r\n", $destinationFile);
                } else {
                    $this->log[] = sprintf("Download Failed\r\n");
                }
            }
        }

        $this->downloadCefull();
    }

   public function downloadOther()
    {
        uksort($this->downloadData['other'], 'version_compare');

        // Show all full releases
        system('clear');
        $this->printLog();
        printf("Download other files\n--\n\n");
        $id = 0;
        $downloadMap = [];
        foreach ($this->downloadData['other'] as $version => $releases) {
            $downloadMap[$id] = $releases['file_name'];
            $option = sprintf(" [%d]:", $id++);
            printf("%s %s\n", str_pad($option, 8), $releases['name']);
        }

        $downloadMap['q'] = 'q';
        printf("\n [q]:    Quit\n");

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadFilename = $downloadMap[$downloadVersion];

        switch ($downloadFilename) {
            case 'q':
                exit;
                break;
        }

        system('clear');
        printf("Downloading %s\n--\n\n", $downloadFilename);
        $downloadFile = sprintf('%s/%s', $this->downloadDir, $downloadFilename);
        if ($destinationFile = $this->download($downloadFilename, $downloadFile)) {
            $this->log[] = sprintf("File downloaded to %s\r\n", $destinationFile);
        } else {
            $this->log[] = sprintf("Download Failed\r\n");
        }

        $this->downloadOther();
    }

    public function downloadCepatch($downloadRelease = false)
    {
        uksort($this->downloadData['ce-patch'], 'version_compare');

        // Show all full releases
        system('clear');
        printf("Download Magento patches\n--\n\n");

        $id = 0;
        $downloadMap = [];

        // Attempt to auto detect Magento version
        if (!$downloadRelease) {
            $mageFilename = sprintf("%s/app/Mage.php", $this->cwd);
            if (file_exists($mageFilename)) {
                require_once getcwd().'/app/Mage.php';
                $versionArray = Mage::getVersionInfo();
                $versionString = sprintf("%s.%s.%s.%s", $versionArray['major'], $versionArray['minor'], $versionArray['revision'], $versionArray['patch']);
                $downloadMap[$id] = $versionString;
                printf(" [%d]: %s %s\n", $id++, $versionString, $this->colors->getColoredString('(auto detected)', 'green'));
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
        }

        $appliedFilename = sprintf("%s/app/etc/applied.patches.list", $this->cwd);
        $appliedPatches = array();
        if (file_exists($appliedFilename)) {
            $content = file_get_contents($appliedFilename);
            preg_match_all('/^[a-zA-Z0-9:\s\-]+ \| ([^\|]+) \|/m', $content, $matches);

            if (isset($matches[1]))
                $appliedPatches = array_unique($matches[1]);
        }

        // Show all patches for selected release
        system('clear');
        $this->printLog();
        printf("Patches for Magento %s\n--\n\n", $downloadRelease);
        $id = 0;
        $downloadMap = [];
        $missingPatches = [];
        foreach ($this->downloadData['ce-patch'][$downloadRelease] as $release) {
            $downloadMap[$id] = $release['file_name'];

            $nameArray = explode(' ', $release['name']);
            $shortName = array_shift($nameArray);

            if (!in_array($shortName, $appliedPatches) && !in_array($release['file_name'], $appliedPatches)) {
                $status = $this->colors->getColoredString(str_pad('Missing', 12), 'red');
                $missingPatches[] = $release['file_name'];
            } else {
                $status = $this->colors->getColoredString(str_pad('Installed', 12), 'green');
            }

            $option = sprintf(" [%d]:", $id++);
            printf("%s %s%s\n", str_pad($option, 8), $status, $release['name']);
        }

        $downloadMap['a'] = 'a';
        $downloadMap['q'] = 'q';
        $downloadMap['m'] = 'm';
        printf("\n [a]:    All patches");
        printf("\n [m]:    All missing patches\n");
        printf("\n [q]:    Quit\n");

        $downloadVersion = -1;
        while (!array_key_exists($downloadVersion, $downloadMap)) {
            $downloadVersion = readline("\nSelect a valid option: ");
        }
        $downloadFilename = $downloadMap[$downloadVersion];

        system('clear');
        $downloadFilenames = [ $downloadFilename ];

        switch ($downloadFilename) {
            case 'a':
                $remove = preg_grep('/[^0-9]+/', array_keys($downloadMap));
                $downloadFilenames = array_diff_key($downloadMap, array_flip($remove));
                break;
            case 'm':
                $downloadFilenames = $missingPatches;
                break;
            case 'q':
                exit;
                break;
        }

        foreach ($downloadFilenames as $downloadFilename) {
            printf("Downloading %s\n--\n\n", $downloadFilename);
            $downloadFile = sprintf('%s/%s', $this->downloadDir, $downloadFilename);
            if ($destinationFile = $this->download($downloadFilename, $downloadFile)) {
                $this->log[] = sprintf("File downloaded to %s\r\n", $destinationFile);
            } else {
                $this->log[] = sprintf("Download Failed\r\n");
            }
        }

        $this->downloadCepatch($downloadRelease);
    }

    public function printLog()
    {
        if (!count($this->log))
            return;

        printf("Download Log\n--\n\n");
        foreach ($this->log as $log) {
            printf(" > %s", $log);
        }
        printf("\n\n");
    }

    public function interactiveDownload()
    {
        system('clear');

        $downloadType = -1;
        $downloadDataKeys = array_keys($this->downloadData);

        // Show different download types
        system('clear');
        printf("Downloadable types\n--\n\n");
        foreach ($downloadDataKeys as $id => $optionText) {
            $option = sprintf(" [%d]:", $id);
            printf("%s %s\n", str_pad($option, 8), ucfirst($optionText));
        }

        $downloadDataKeys['q'] = 'q';
        printf("\n [q]:    Quit\n");

        while (!array_key_exists($downloadType, $downloadDataKeys)) {
            $downloadType = readline("\nSelect a valid option: ");
        }
        $downloadType = $downloadDataKeys[$downloadType];
        $downloadMethod = sprintf('download%s', ucfirst(str_replace('-', '', $downloadType)));

        switch ($downloadType) {
            case 'q':
                exit;
                break;
        }

        $this->{$downloadMethod}();
    }

}


