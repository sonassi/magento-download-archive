# Magento Download Archive

Quickly download Magento patches for any Magento version or full Magento source, using a backup download source for high performance downloads.

## Usage

Change to to your Magento document root and grab the pre-built phar,

~~~
cd /microcloud/domains/example/domains/example.com/http
wget --no-check-certificate https://raw.githubusercontent.com/sonassi/magento-download-archive/master/bin/mda.phar
~~~~

Then execute the phar and follow the on-screen options,

~~~~
php mda.phar

Downloadable types

 [0]: ce-full
 [1]: ce-patch
 [2]: other

Select a valid option:
~~~~

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
