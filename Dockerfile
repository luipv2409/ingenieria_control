FROM php:8.2-apache

# Instalar extensiones necesarias de PHP (pdo_mysql)
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite de Apache si fuera necesario
RUN a2enmod rewrite

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar la aplicación web
COPY web/ /var/www/html/

# Permisos para el servidor web
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

