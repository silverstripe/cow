## Overview
The idea with this approach is to use an image built and hosted on hub.docker.com, then manually run cow commands
You can update the image on hub.docker.com by editing the Dockerfile in this directory.  The `latest` tag is added
when the Dockerfile on the master branch is updated.  Tagged versions are created in hub.docker.com pushing semver
tags to github

## Running cow
Spin up a new container by pulling an image from hub.docker.com
```
docker run \
  --name mycowcontainer \
  -dit \
  --rm \
  -v ~/.gitconfig:/home/cow/.gitconfig:ro \
  -v ~/.ssh:/home/cow/.ssh:ro \
  emteknetnz/silverstripe-cow:latest
````

SSH in to the container
`docker exec -it mycowcontainer /bin/bash`

Run cow from within the container
```
cd ~
cow [commands] e.g. cow release
```

When finished
`exit`
`docker stop mycowcontainer`
