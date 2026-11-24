# Servidor de desarrollo para MaxisGym: PHP 8.3 + Apache (equivalente a Laragon)
FROM php:8.3-apache

# Extensiones que pide diagnostico.php (mbstring ya viene incluida)
RUN docker-php-ext-install pdo_mysql \
 # Respeta los .htaccess del proyecto (bloquean app/, config/, database/...)
 && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && a2enmod rewrite \
 && echo "date.timezone=America/Monterrey" > /usr/local/etc/php/conf.d/timezone.ini

# Apache corre con tu mismo UID para poder escribir en storage/ (correos y logs)
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} www-data
