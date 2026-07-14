# Imagen base con Apache + mod_php. Versión fijada para builds reproducibles.
FROM php:8.3-apache

# Extensiones: pdo_mysql para la BD; APCu para el cache de API keys y el
# rate limiting en memoria compartida entre peticiones del mismo worker.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install pdo_mysql \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# APCu también en modo CLI (para bin/create-api-key.php y diagnósticos).
RUN echo "apc.enable_cli=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-apcu.ini"

# Producción por defecto en el contenedor: sin mostrar errores al cliente.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# El webroot es public/: solo el front controller queda expuesto.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

# Permitir .htaccess (AllowOverride All) en el webroot.
RUN printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/z-allow-override.conf \
    && a2enconf z-allow-override

# Instalar Composer y las dependencias PHP.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
# Instalación reproducible a partir del composer.lock versionado.
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

EXPOSE 80
