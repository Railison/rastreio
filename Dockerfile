FROM php:8.2-cli-alpine

RUN apk add --no-cache git openssl curl

WORKDIR /app

COPY composer.json ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php && rm composer-setup.php \
    && php composer.phar install --no-dev

COPY worker.php ./

CMD ["php", "worker.php"]
