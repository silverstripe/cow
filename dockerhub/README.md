## Overview
The idea with this approach is to use an image built and hosted on hub.docker.com, then manually run cow commands

The Dockerfile in this directory is very similar to the one in the /docker directory, minus xdebug and the
multi-stage builds

You can update the image on hub.docker.com by editing the Dockerfile in this directory.
- The `latest` tag is added when the Dockerfile on the master branch is updated in github.
- Tagged versions are created in hub.docker.com pushing semver tags to github

## Running cow
Spin up a new container by pulling a pre-built image from hub.docker.com.
You will be automatically SSH'd into the container and be in the /home/cow directory
```
docker run \
  --name mycowcontainer \
  -it \
  --rm \
  -v ~/.gitconfig:/home/cow/.gitconfig:ro \
  -v ~/.ssh:/home/cow/.ssh:ro \
  -v ~/.transifexrc:/home/cow/.transifexrc:ro \
  silverstripe/cow:latest
```

Run cow from within the container.  You can read more about the cow commands used in the [standard release process](https://docs.silverstripe.org/en/4/contributing/making_a_silverstripe_core_release/#standard-release-process) and also get a [detailed breakdown](../readme.md)
`cow [commands] e.g. cow release`

When finished, exit the container and the container will be automatically deleted for you
`exit`

## hub.docker.com setup
Automated builds in hub.docker.com should be setup as follows: 

### Build 1 (latest)
Source type = Branch
Source = master
Docker Tag = latest
Dockerfile location = Dockerfile
Build context = /dockerhub/

### Build 2 (tagged)
Source type = Tag
Source = /^[0-9.]+$/
Docker Tag = {sourceref}
Dockerfile location = Dockerfile
Build context = /dockerhub/
