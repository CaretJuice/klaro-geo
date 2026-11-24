# The one image behind every WordPress environment of this plugin: the PHPUnit
# runner and the site used for Playwright and manual testing
# (docker-compose.yml). Everything it needs is fetched here, at a pinned
# version, so starting a container needs no network and gives the same result
# on every machine.
#
# WP_VERSION and PHP_VERSION choose the official WordPress image to build on;
# any pair Docker Hub has a wordpress:<wp>-php<php>-apache tag for works.
ARG WP_VERSION=7.1.5
ARG PHP_VERSION=8.3
FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-apache

ARG WP_VERSION
ARG WP_CLI_VERSION=2.12.0
ARG WP_CLI_SHA512=be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832
ARG GEOIP_DETECT_VERSION=5.6.4
ARG PLUGIN_CHECK_VERSION=2.2.0

# The MySQL client is what `wp db ...` runs.
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client unzip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && echo "${WP_CLI_SHA512}  /usr/local/bin/wp" | sha512sum -c - \
    && chmod +x /usr/local/bin/wp

# WordPress's PHPUnit test library, at the version of WordPress in this image.
# The two must match, which is why it is here and not a Composer dependency.
RUN mkdir -p /opt/wp-phpunit \
    && curl -fsSL "https://github.com/wp-phpunit/wp-phpunit/archive/refs/tags/${WP_VERSION}.tar.gz" \
       | tar -xz --strip-components=1 -C /opt/wp-phpunit

# Plugins the site needs. /usr/src/wordpress is what the WordPress image copies
# into /var/www/html when a container first starts.
RUN for plugin in "geoip-detect.${GEOIP_DETECT_VERSION}" "plugin-check.${PLUGIN_CHECK_VERSION}"; do \
        curl -fsSL -o /tmp/plugin.zip "https://downloads.wordpress.org/plugin/${plugin}.zip" \
        && unzip -q /tmp/plugin.zip -d /usr/src/wordpress/wp-content/plugins \
        && rm /tmp/plugin.zip || exit 1; \
    done \
    && chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins

# WP-CLI is run as root with `docker exec`; this is a development container.
ENV WP_TESTS_DIR=/opt/wp-phpunit \
    WP_CLI_ALLOW_ROOT=1

COPY php.ini /usr/local/etc/php/conf.d/klaro-geo.ini
COPY --chmod=755 site-setup.sh /usr/local/bin/site-setup
COPY --chmod=755 phpunit-entrypoint.sh /usr/local/bin/phpunit-entrypoint
