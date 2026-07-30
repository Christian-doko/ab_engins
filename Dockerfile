# Image PHP pour Railway — serveur web intégré de PHP (sans Apache).
# L'image php:8.3-apache produit "More than one MPM loaded" sur Railway ;
# le serveur intégré élimine ce problème et suffit pour cette application.
FROM php:8.3-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . /app

# Plusieurs workers pour absorber les requêtes simultanées (fetch parallèles du front)
ENV PHP_CLI_SERVER_WORKERS=8

EXPOSE 8080
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} -t /app"]
