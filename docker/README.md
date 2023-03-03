# Docker setup for running Cow

The `docker` folder contains everything related to the Docker setup, its configs and shims.

# How to use it

`docker/bin` folder contains all the scripts to run Cow itself and misc operations.
You can run `docker/bin/run` to run Cow transparently within your shell.

See the command descriptions below in Appendix.


### Plug and play

These scripts have been designed to work transparently in such a way that
you don't have to know anything about Docker to be able to use them. The only requirement
is that Docker installed and running on your machine.

Tested on `Linux` and `macOS`.
Wasn't tested on Windows, but it should work on either `Cygwin` or `WSL` (the scripts are Bash).


# How it works

The scripts in the `docker/bin` folder provide shortcuts for the most common use cases.
The main goal is to launch commands within containers in a way that is completely transparent
and doesn't require the user to know anything about how Docker works. Thus, the commands may be
thought of as _Cow_ scripts and not _Docker_ scripts.

That implies that Docker installed and running on the host machine becomes the only 3rd party
requirement of Cow. Not even PHP is required anymore since it's included into the Docker images.

Containers are very cheap and disposable and we shouldn't think of them as of classic virtual machines (which
are very expensive to initialize and spin up). As such, every script run creates a new
container, runs its function and deletes the container afterwards. That means the only shared
environment between them is your host machine filesystem (and environment variables).

The scripts run Cow and mount current folder (`$(pwd)`) into the container preserving the paths.


# Appendix


### release/Dockerfile

`docker/release/Dockerfile` contains the description of a Docker image that takes latest release
and amends the internal `cow` user User ID and Group ID to match those on the host machine.
This makes the files created within the container to seamlessly belong to the same user between host and guest (container).

### Dockerfile

`docker/Dockerfile` contains the description of Docker image build targets (aka stages):
  - `php` - basic PHP installation with a bunch of usual modules included
  - `base` - on top of `php` installs the 3rd party software required particularly for Cow
  - `stable` - on top of `base` installs a latest stable Cow version from packagist (with composer)
  - `debug` - on top of `base` installs Xdebug to facilitate Cow development and debugging


### docker-compose.yml

`docker-compose.yml` defines 3 services to use in different scenarios:
  - `stable` pulls the latest stable image of Cow (`stable` target) published to GitHub Packages
  - `dev` builds a local version of `base` target from the Dockerfile
  - `debug` builds a local version of `debug` target from the Dockerfile

### docker/.env

Contains some config for the build stage (e.g. xdebug). This is not propagated into containers,
but only used within `docker-compose.yml`


### Scripts

When you run Cow via one of these scripts, Docker will be given access to the contents of the folder you ran it from, along with some of your environment variables, and SSH agent, to allow creating / modifying releases and publishing them to GitHub.

#### ./docker/bin/debug

This script launches your local version of Cow with Xdebug on port 9000 (configurable through `docker/.env`).
It can be used for Cow development and debugging.


#### ./docker/bin/docker-shell

This script launches a container and attaches your shell to it (that appears alike ssh log into container).
The only argument for this script is the service name defined by docker-compose.yml (stable, dev, debug).


#### ./docker/bin/phpcs

This script is a simple shortcut for `vendor/bin/phpcs` that launches within a container.


#### ./docker/bin/release

Same as `docker/bin/run`, but with ssh-agent reading your keys for accessing GitHub from within the container.
Adds some extra checks for your environment (e.g. GITHUB_API_TOKEN)


#### ./docker/bin/run

Runs a stable Cow release, based on the latest published Docker container.


#### ./docker/bin/test

Runs `./vendor/bin/phpunit` within a container with Xdebug knocking to port 9000 (configurable through `docker/.env`).
Can be used for Cow tests development and debugging.
