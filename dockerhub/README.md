## Overview
The idea with this approach is to use an image built and hosted on hub.docker.com, then manually run cow commands

The Dockerfile in this directory is very similar to the one in the /docker directory, minus xdebug and the
multi-stage builds

You can update the image on hub.docker.com by editing the Dockerfile in this directory.
- The `latest` tag is added when the Dockerfile on the master branch is updated in github.
- Tagged versions are created in hub.docker.com pushing semver tags to github

## Running cow
Spin up a new container by pulling a pre-built image from hub.docker.com
```
docker run \
  --name mycowcontainer \
  -dit \
  --rm \
  -v ~/.gitconfig:/home/cow/.gitconfig:ro \
  -v ~/.ssh:/home/cow/.ssh:ro \
  -v ~/.transifexrc:/home/cow/.transifexrc:ro \
  emteknetnz/silverstripe-cow:latest
````

SSH in to the container
`docker exec -it mycowcontainer /bin/bash`

Run cow from within the container
`cd ~`
`cow [commands] e.g. cow release`

When finished
`exit`
`docker stop mycowcontainer`

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
