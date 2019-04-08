#!/bin/bash
ulimit -n 100000;
composer update --no-dev;
php ./vendor/bin/phing release;
composer update;