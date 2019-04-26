#!/bin/bash
clear;
printf "Hast du daran gedacht die Update Methode in der Bootstrap.php zu ergänzen? [j/N] : ";
read -r cmd;

if [[ $cmd = 'j' || $cmd = 'J' ]]; then
    ulimit -n 100000;
    composer update --no-dev;
    php ./vendor/bin/phing release;
    composer update;
fi