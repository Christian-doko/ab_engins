# Image PHP + Apache pour Railway
FROM php:8.3-apache

# Extension PDO MySQL requise par api/config.php
RUN docker-php-ext-install pdo_mysql

# Copie du code dans la racine web d'Apache
COPY . /var/www/html/

# Railway fournit le port via $PORT ; Apache écoute dessus
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

EXPOSE ${PORT}
CMD ["apache2-foreground"]
