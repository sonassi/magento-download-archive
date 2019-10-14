# Magento Download Archive

Quickly download Magento patches for any Magento version or full Magento source, using a backup download source for high performance downloads.

## Options

~~~~
Options:

    --id=        Magento download ID
    --token=     Magento download token
    --filter=    Filename filter (regex supported)
~~~~

## Usage

Change to to your Magento document root and grab the pre-built phar,

~~~
cd /microcloud/domains/example/domains/example.com/http
wget -O mda.phar --no-check-certificate https://raw.githubusercontent.com/sonassi/magento-download-archive/master/bin/mda.phar
~~~~

Then execute the phar and follow the on-screen options,

~~~~
Downloadable types
--

 [0]:    Ce-full
 [1]:    Ce-patch
 [2]:    Ee-full
 [3]:    Ee-patch
 [4]:    Other

 [q]:    Quit

Select a valid option:
~~~~

### Setting custom id/token for personalised downloads

If you are a Magento EE subscriber, or simply want to use your own id/token for downloads, you can do so using the following options.

The application supports the following long options,

 - `--id`  
    Your Magento ID, found in your [My Account](https://account.magento.com/customer/account/) section on the top left hand side.
 - `--token`  
    Your Magento Token, found in your [Access Token](https://account.magento.com/downloads/token/) section.

## Building phar

Download the repo source,

~~~
mkdir -p /usr/src/magento
cd /usr/src/magento
git clone https://github.com/sonassi/magento-download-archive
cd magento-download-archive
~~~

Then compile the phar,

~~~~
php -d 'phar.readonly=0' build.php
~~~~

The resulting file will be stored in `./bin`
