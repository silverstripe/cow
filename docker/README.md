# Docker setup for running Cow

The `docker` folder contains everything related to the docker setup, its configs and shims.

The main idea is that users don't have to know anything about docker or its configs.
The only requirement is to have Docker installed.

To allow that we provide `shims` - bash scripts implementing shortcuts for the most common use cases.

`docker/.env` file contains some configuration parameters (e.g. xdebug configs)

The `Dockerfile` builds environment on top of an official php container from dockerhub,
installing all the PHP extensions necessary as well as 3rd party dependencies of COW
(such as git, composer, ruby gems and transifex client).

`docker-compose.yml` contains a set of configurations for different use cases such as running cow for development, debugging, running tests, debugging tests, running code sniffer and running cow in production mode.
Although you may run any of these configs straightforwardly by running docker-compose if you want, the idea is to run them
through `shims` that facilitate environment control, propagation of environment variables and required settings.


# Shims

## ./docker/run

Running `./bin/cow` transparently within a container.

This script runs cow in development mode by mounting current folder (`$(pwd)`) into the container preserving the path.
It also uses the current cow folder as is, so if you patched any scripts, the changes will be runnnig exactly as is within the container.

## ./docker/test

This script runs all the unit tests within a container

## ./docker/phpcs

This script runs phpcs within the container

## ./docker/dbg

This script does the same as `./docker/run`, however the container will have XDebug installed and activated, so you can
debug cow. XDebug configuration settings can be controlled through `.env` file.

## ./docker/dbg-test

This script does the same as `./docker/test`, however the container will have XDebug installed and activated, so you can
debug unit tests. XDebug configuration settings can be controlled through `.env` file.
