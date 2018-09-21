#!/bin/bash
ulimit -n100000;
composer update --no-dev;
phing package;
composer update;
