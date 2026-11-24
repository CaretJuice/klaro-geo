# The one image behind every WordPress environment of this plugin: the PHPUnit
# runner and the site used for Playwright and manual testing
# (docker-compose.yml). Everything it needs is fetched here, at a pinned
# version, so starting a container needs no network and gives the same result
# on every machine.
#
# It is built on the plain PHP image, with WordPress downloaded by version, and
# not on the official WordPress image: that one only exists for the PHP
# versions current when each WordPress was released, and the plugin has to be
# tested on old pairs too. Any WP_VERSION wordpress.org still serves works with
# any PHP_VERSION Docker Hub has a php:<version>-apache image for. Nothing is
# installed with apt, because the Debian releases under old PHP images no
# longer have package servers.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

ARG WP_VERSION=7.1.5
# Empty picks the newest PHPUnit that runs on this PHP (see below). WordPress
# before 5.9 needs PHPUNIT_VERSION=7.5.20 whatever the PHP.
ARG PHPUNIT_VERSION=
ARG PHPUNIT_POLYFILLS_VERSION=1.1.5
ARG WP_CLI_VERSION=2.12.0
ARG WP_CLI_SHA512=be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832
ARG GEOIP_DETECT_VERSION=5.6.4
ARG PLUGIN_CHECK_VERSION=2.2.0

RUN docker-php-ext-install mysqli > /dev/null && a2enmod rewrite > /dev/null

# WordPress, where Apache serves it and where the tests load it from.
RUN curl -fsSL -o /tmp/wordpress.tar.gz "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" \
    && echo "$(curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz.sha1")  /tmp/wordpress.tar.gz" | sha1sum -c - \
    && tar -xzf /tmp/wordpress.tar.gz --strip-components=1 -C /var/www/html \
    && rm /tmp/wordpress.tar.gz
COPY wp-config.php /var/www/html/wp-config.php

# WordPress's PHPUnit test library, at the version of WordPress in this image.
# The two must match, which is why it is here and not a Composer dependency.
RUN mkdir -p /opt/wp-phpunit \
    && curl -fsSL "https://github.com/wp-phpunit/wp-phpunit/archive/refs/tags/${WP_VERSION}.tar.gz" \
       | tar -xz --strip-components=1 -C /opt/wp-phpunit

# PHPUnit, as a single file, and the polyfills that let tests written for
# PHPUnit 9 run on older ones. The newest PHPUnit each PHP can run:
# 9.6 from PHP 7.3, 8.5 on 7.2, 7.5 on 7.1, 6.5 on 7.0, 5.7 on 5.6.
RUN version="${PHPUNIT_VERSION:-$(php -r 'echo PHP_VERSION_ID >= 70300 ? "9.6.39" : ( PHP_VERSION_ID >= 70200 ? "8.5.57" : ( PHP_VERSION_ID >= 70100 ? "7.5.20" : ( PHP_VERSION_ID >= 70000 ? "6.5.14" : "5.7.27" ) ) );')}" \
    && curl -fsSL -o /usr/local/bin/phpunit "https://phar.phpunit.de/phpunit-${version}.phar" \
    && chmod +x /usr/local/bin/phpunit \
    && mkdir -p /opt/phpunit-polyfills \
    && curl -fsSL "https://github.com/Yoast/PHPUnit-Polyfills/archive/refs/tags/${PHPUNIT_POLYFILLS_VERSION}.tar.gz" \
       | tar -xz --strip-components=1 -C /opt/phpunit-polyfills

# WP-CLI, for the site.
RUN curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && echo "${WP_CLI_SHA512}  /usr/local/bin/wp" | sha512sum -c - \
    && chmod +x /usr/local/bin/wp

# Plugins the site needs: Geolocation IP Detection, which Klaro Geo reads the
# visitor's location from, and Plugin Check (bin/plugin-check.sh). Unpacked
# with the zip reader WordPress ships, so the image needs no unzip.
COPY unzip.php /usr/local/bin/unzip.php
RUN for plugin in "geoip-detect.${GEOIP_DETECT_VERSION}" "plugin-check.${PLUGIN_CHECK_VERSION}"; do \
        curl -fsSL -o /tmp/plugin.zip "https://downloads.wordpress.org/plugin/${plugin}.zip" \
        && php /usr/local/bin/unzip.php /tmp/plugin.zip /var/www/html/wp-content/plugins \
        && rm /tmp/plugin.zip || exit 1; \
    done \
    && chown -R www-data:www-data /var/www/html

# WP-CLI is run as root with `docker exec`; this is a development container.
ENV WP_TESTS_DIR=/opt/wp-phpunit \
    WP_TESTS_POLYFILLS_DIR=/opt/phpunit-polyfills \
    WP_CLI_ALLOW_ROOT=1

COPY php.ini /usr/local/etc/php/conf.d/klaro-geo.ini
COPY site-setup.sh /usr/local/bin/site-setup
COPY phpunit-entrypoint.sh /usr/local/bin/phpunit-entrypoint
RUN chmod 755 /usr/local/bin/site-setup /usr/local/bin/phpunit-entrypoint
