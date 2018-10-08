#!/bin/bash
ulimit -n 100000;
composer update --no-dev;
php ./phing.phar release;
composer update;