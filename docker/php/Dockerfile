FROM wordpress:7.0.1-php8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl msmtp-mta unzip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/compadres.ini
COPY docker/php/msmtprc /etc/msmtprc
RUN chmod 0600 /etc/msmtprc \
    && a2enmod headers expires rewrite
