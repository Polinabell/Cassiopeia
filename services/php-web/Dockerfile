FROM php:8.3-fpm-alpine

# пакеты и расширения (включая redis, zip, gd для phpspreadsheet)
RUN apk add --no-cache bash curl git unzip rsync postgresql-dev libzip-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    autoconf g++ make linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_pgsql zip gd \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf g++ make linux-headers

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# каталоги
RUN mkdir -p /var/www/html /opt/laravel-patches
WORKDIR /var/www/html

# entrypoint
COPY entrypoint.sh /usr/local/bin/php_entrypoint.sh
RUN chmod +x /usr/local/bin/php_entrypoint.sh

CMD ["bash", "/usr/local/bin/php_entrypoint.sh"]
