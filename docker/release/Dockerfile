FROM ghcr.io/silverstripe/cow/master:latest

ARG USER_ID=${USER_ID}
ARG GROUP_ID=${GROUP_ID}

USER root

# Sync the user id and group id with the host machine
RUN usermod -u ${USER_ID} -g ${GROUP_ID} cow && chown -R cow:cow /home/cow

USER cow
