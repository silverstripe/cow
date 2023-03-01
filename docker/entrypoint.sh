#!/bin/bash

if [ ! -z "$COW_MODE_RELEASE" ] ; then
    set -e

    echo -n "Checking GITHUB_API_TOKEN ... "

    COMPOSER_GITHUB_API_TOKEN=$(composer config -gl | grep github-oauth.github.com || true)

    if [ -z "$GITHUB_API_TOKEN" -a -z "$COMPOSER_GITHUB_API_TOKEN" ] ; then
        echo "GITHUB_API_TOKEN environment variable is undefined"
        exit 1;
    elif [ -z "$GITHUB_API_TOKEN" ] ; then
        export GITHUB_API_TOKEN=$(composer config -g -- github-oauth.github.com);
    elif [ -z "$COMPOSER_GITHUB_API_TOKEN" ] ; then
        composer config -g github-oauth.github.com $GITHUB_API_TOKEN;
    fi

    echo "done"

    echo -n "Running SSH Agent... ";
    eval $(ssh-agent);

    echo "Loading SSH Keys into the SSH Agent..."
    ssh-add;

    echo "Scanning github.com keys, verifying github authentication"
    ssh-keyscan -H github.com 2> /dev/null 1> ~/.ssh/known_hosts
    ssh -qT git@github.com 2>&1 | grep "successfully authenticated" || (echo "GitHub authentication error" && exit 1)
fi

if [ ! -z "$COW_MODE_COMPOSER" ] ; then
    composer $@
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