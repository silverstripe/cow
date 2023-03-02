FROM php:8.1-cli as php

RUN apt-get update -y \
 && apt-get install -y \
    unzip wget \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libmemcached-dev \
    zlib1g-dev libicu-dev libpq-dev libtidy-dev libzip-dev \
    libldap-dev libgmp-dev \
    libmagickwand-dev  # for the image magick extension (imagick)

RUN docker-php-source extract \
 && docker-php-ext-install iconv \
 && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
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

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php -r "if (hash_file('sha384', 'composer-setup.php') === '$(wget -q -O - https://composer.github.io/installer.sig)') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
 && php composer-setup.php --install-dir=/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"



######## Target: base

FROM php as base

RUN apt-get update -y \
 && apt-get install -y git python3-pip openssh-client gpg \
 && apt-get install -y ruby \
 && gem install yamlclean



######## Target: stable

FROM base as stable

ARG COW_VERSION=dev-master
ARG USER_ID=1000
ARG GROUP_ID=${USER_ID}

RUN composer -vvv global require silverstripe/cow:${COW_VERSION} \
 && chmod 755 /root \
 && ln -s /root/.composer/vendor/bin/cow /usr/bin/. \
 && groupadd -f -g ${GROUP_ID} cow \
 && useradd -g ${GROUP_ID} -d /home/cow -m -u ${USER_ID} cow
#  && mkdir -p /home/cow/.cache/composer/cache \
#  && chown -R cow:cow /home/cow/.cache/composer

USER cow
WORKDIR /home/cow
ENTRYPOINT [ /bin/bash ]


######## Target: debug

FROM stable as debug

USER root
RUN docker-php-source extract \
 && yes '' | pecl install xdebug && docker-php-ext-enable xdebug \
 && docker-php-source delete
USER cow
