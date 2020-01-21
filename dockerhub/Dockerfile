FROM php:7.3-cli-stretch

RUN apt-get update -y \
 && apt-get install -y \
    unzip wget \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libmemcached-dev \
    zlib1g-dev libicu-dev libpq-dev libtidy-dev libzip-dev \
    libldap-dev libgmp-dev \
    libmagickwand-dev  # for the image magick extension (imagick)

RUN docker-php-source extract \
 && docker-php-ext-install iconv \
 && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
 && docker-php-ext-install gd \
 && docker-php-ext-install intl \
 && docker-php-ext-install zip \
 && docker-php-ext-install ldap \
 && docker-php-ext-install gmp \
 && docker-php-ext-install mysqli \
 && docker-php-ext-install pgsql \
 && docker-php-ext-install pdo \
 && docker-php-ext-install pdo_mysql \
 && docker-php-ext-install pdo_pgsql \
 && docker-php-ext-install tidy \
 && docker-php-ext-install exif \
 && docker-php-ext-install bcmath \
 && docker-php-ext-install bz2 \
 && yes '' | pecl install memcached && docker-php-ext-enable memcached \
 && yes '' | pecl install redis && docker-php-ext-enable redis \
 && yes '' | pecl install imagick && docker-php-ext-enable imagick \
 && yes '' | pecl install apcu && docker-php-ext-enable apcu \
 && docker-php-source delete

# If composer-setup.php fails verification to install, then execution will be halted
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === '$(wget -q -O - https://composer.github.io/installer.sig)') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
 && php composer-setup.php --install-dir=/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

ARG UID=1000
ARG GID=${UID}

RUN echo "Group ID: ${GID}" \
 && echo "User ID: ${UID}" \
 && groupadd -f -g ${GID} cow \
 && useradd -g ${GID} -d /home/cow -m -u ${UID} cow \
 && mkdir -p /home/cow/.composer/cache \
 && chown -R cow:cow /home/cow/.composer

USER root
RUN apt-get update -y \
 && apt-get install -y git python3-pip \
 && pip3 install -U pip \
 && pip install transifex-client awscli \
 && apt-get install -y ruby \
 && gem install yamlclean

# Automatically require the latest version of silverstripe/cow when the container is spun up
# These commands in .bashrc will be execuated on `docker run`
# This method allows us to avoid an extra entrypoint.sh file
# Note: silverstripe/cow must be required before requiring composer/satis to prevent a requirements conflict
USER cow
RUN echo "export PATH=~/.composer/vendor/bin:$PATH" >> ~/.bashrc
RUN echo "echo \"================================================================================\"" >> ~/.bashrc
RUN echo "echo \"Now composer requiring the latest version of silverstripe/cow and composer/satis\"" >> ~/.bashrc
RUN echo "echo \"================================================================================\"" >> ~/.bashrc
RUN echo "composer -vvv global require silverstripe/cow:dev-master composer/satis:^1" >> ~/.bashrc

ENTRYPOINT ["/bin/bash"]
WORKDIR "/home/cow"
