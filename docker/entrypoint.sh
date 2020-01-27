#!/bin/bash

if [ ! -z "$COW_MODE_RELEASE" ] ; then
    set -e
    mkdir ~/.ssh
    ssh-keyscan -H github.com 2> /dev/null 1> ~/.ssh/known_hosts
    ssh -qT git@github.com 2>&1 | grep "successfully authenticated" || (echo "GitHub authentication error" && exit 1)
    /usr/bin/cow $@;
    exit;
fi

if [ ! -z "$COW_MODE_TRANSIFEX" ] ; then
    tx $@
    exit;
fi

if [ ! -z "$COW_MODE_DEBUG" ] ; then
    DIR="$COW_DIR";
else
    DIR="/app";
fi

DIR="$(readlink -f $DIR)";

if [[ ! -d "$DIR/vendor" ]] ; then
    cd $DIR;
    composer install --prefer-dist -vv;
    cd -;
fi

if [ ! -z "$COW_MODE_TEST" ] ; then
    cd $DIR;
    ./vendor/bin/phpunit $@;
    exit;
fi

if [ ! -z "$COW_MODE_PHPCS" ] ; then
    cd $DIR;
    ./vendor/bin/phpcs --standard=PSR12 bin/ src/ tests/ $@;
    exit;
fi

$DIR/bin/cow $@