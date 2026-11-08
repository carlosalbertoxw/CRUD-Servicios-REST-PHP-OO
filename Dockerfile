# --- Dependencias: Composer en su propia etapa (no llega a la imagen final) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Instalación reproducible a partir del composer.lock versionado.
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY src ./src
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# --- Runtime: Apache + mod_php ---
FROM php:8.5-apache

# Extensiones: pdo_mysql para la BD; APCu para el cache de API keys y el
# rate limiting en memoria compartida entre peticiones del mismo worker.
RUN docker-php-ext-install pdo_mysql \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /tmp/pear

# APCu también en modo CLI (para bin/create-api-key.php y diagnósticos).
RUN echo "apc.enable_cli=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-apcu.ini"

# Producción por defecto en el contenedor: sin mostrar errores al cliente.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# El webroot es public/: solo el front controller queda expuesto.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

# Permitir .htaccess (AllowOverride All) en el webroot y rechazar en Apache
# cuerpos mayores a 1 MB (antes de que lleguen a PHP).
RUN printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\nLimitRequestBody 1048576\n' \
    > /etc/apache2/conf-available/z-app.conf \
    && a2enconf z-app

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

EXPOSE 80
