FROM php:8.4-apache
ARG NODE_VERSION=20

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
ENV COMPOSER_ALLOW_SUPERUSER=1

# Dépendances système
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    file \
    git \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zsh \
    && rm -rf /var/lib/apt/lists/*

# Configuration et installation des extensions PHP standard
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl pdo_mysql zip opcache soap gd

# Activation du rewrite d'Apache (indispensable pour Symfony)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js et npm
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm@11.16.0 \
    && rm -rf /var/lib/apt/lists/*

RUN git config --global --add safe.directory /var/www/html

# Optionnel : Install starship pour le look du terminal en dev
RUN curl -sS https://starship.rs/install.sh | sh -s -- -y \
    && echo 'eval "$(starship init bash)"' >> /root/.bashrc

WORKDIR /var/www/html
