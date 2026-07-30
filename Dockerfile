# Image PHP + Apache pour Railway
FROM php:8.3-apache

# Extension PDO MySQL requise par api/config.php
RUN docker-php-ext-install pdo_mysql

# Copie du code dans la racine web d'Apache
COPY . /var/www/html/

# Apache écoute sur 80 ; côté Railway, cibler le port 80 lors de la
# génération du domaine public (Settings → Networking).
EXPOSE 80
CMD ["apache2-foreground"]
