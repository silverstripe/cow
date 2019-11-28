#!/bin/bash

if [[ ! -d "/app/vendor" ]] ; then
    cd /app;
    composer install --prefer-dist -vv;
    cd -;
fi

/app/bin/cow $@