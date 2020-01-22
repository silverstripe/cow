## Overview
This Dockerfile is used to create an image that is hosted on hub.docker.com.  Users can then pull this image and
manually run cow commands.

## Running cow
Spin up a new container by pulling a pre-built image from hub.docker.com
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

## Updating docker image on hub.docker.com
Manually update the image on hub.docker.com if you make changes to the Dockerfile, or if you just need to update the
dependencies in the image e.g. updating transifex-client.  You will need to have write access to the dockerhub repository.

```
cd ./cow/dockerhub
export COW_VERSION=1.2.0 [tagged release | 1.x-dev | dev-master]
docker build --build-arg COW_VERSION=$COW_VERSION -t silverstripe/cow:$COW_VERSION .
docker push silverstripe/cow:$COW_VERSION
```
