Build your own complete Magento download archive using this script.

~~~
mkdir -p /usr/src/magento
cd /usr/src/magento
git clone https://github.com/sonassi/magento-download-archive
cd magento-download-archive
cp settings.conf.default settings.conf
~~~

Edit `settings.conf` and define the runtime settings.

Then execute the script.

~~~
php app.php
~~~
