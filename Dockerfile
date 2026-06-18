FROM php:8.2-apache

# Estensione PDO Postgres (per account + metriche)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers     && sed -i "s|AllowOverride None|AllowOverride All|g" /etc/apache2/apache2.conf     && rm -f /etc/apache2/conf-enabled/security.conf.bak
