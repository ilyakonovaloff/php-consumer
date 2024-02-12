FROM php:8.3-cli-alpine

ENV UID=1000

RUN apk add --no-cache \
    linux-headers

RUN docker-php-ext-install \
    pcntl

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY . .
