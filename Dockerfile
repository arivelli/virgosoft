FROM php:8.5-fpm-bookworm

ARG PROJECT_ROOT=/var/www/html
ENV PROJECT_ROOT=${PROJECT_ROOT}

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    # PHP extensions dependencies
    git unzip zip libicu-dev libonig-dev libxml2-dev libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libssl-dev libpq-dev \
    libcurl4-openssl-dev libicu-dev g++ libpq-dev \
    # Development tools
    nano vim-tiny less htop procps iputils-ping dnsutils curl wget \
    # Debugging tools
    strace lsof net-tools tcpdump \
    # Version control
    git \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    bcmath exif intl pcntl pdo_mysql zip gd opcache \
    pdo_pgsql pgsql

# Install PECL extensions (install xdebug but do NOT auto-enable it)
RUN pecl install redis xdebug \
 && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Change www-data UID and GID to 1000
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Setup www-data user with proper home directory and shell
RUN usermod -d /home/www-data -m www-data
RUN usermod -s /bin/bash www-data \
    && mkdir -p /home/www-data/.composer \
    && mkdir -p /home/www-data/.ssh \
    && chmod 700 /home/www-data/.ssh \
    && chown -R www-data:www-data /home/www-data \
    && echo 'export PS1="\[\033[1;36m\]\u@\h:\w\\$ \[\033[0m\]"' >> /home/www-data/.bashrc \
    && echo 'export PATH="$PATH:${PROJECT_ROOT}/vendor/bin:/home/www-data/.composer/vendor/bin"' >> /home/www-data/.bashrc \
    && echo 'export COMPOSER_HOME="/home/www-data/.composer"' >> /home/www-data/.bashrc \
    && echo 'alias ll="ls -la"' >> /home/www-data/.bashrc \
    && echo 'alias artisan="php artisan"' >> /home/www-data/.bashrc \
    && echo 'export HISTSIZE=10000' >> /home/www-data/.bashrc \
    && echo 'export HISTFILESIZE=20000' >> /home/www-data/.bashrc \
    && echo 'export HISTCONTROL=ignoreboth' >> /home/www-data/.bashrc \
    && echo 'export PROMPT_COMMAND="history -a"' >> /home/www-data/.bashrc \
    && chown -R www-data:www-data /home/www-data/.bashrc

# Configure composer for www-data user
RUN mkdir -p /home/www-data/.composer/cache \
    && chown -R www-data:www-data /home/www-data/.composer \
    && echo '{"config": {"bin-dir": "/home/www-data/.composer/vendor/bin"}}' > /home/www-data/.composer/config.json \
    && chown www-data:www-data /home/www-data/.composer/config.json

# Copy health check script
COPY etc/healthcheck/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# Set working directory
WORKDIR ${PROJECT_ROOT}

# Install libfcgi-bin for health check
RUN apt-get update && apt-get install -y --no-install-recommends libfcgi-bin \
    && rm -rf /var/lib/apt/lists/*

# Create a directory for PHP-FPM socket
RUN mkdir -p /var/run/php-fpm

# Create and set permissions for Xdebug log directory
RUN mkdir -p ${PROJECT_ROOT}/xdebug \
    && chown -R www-data:www-data ${PROJECT_ROOT}/xdebug \
    && chmod -R 777 ${PROJECT_ROOT}/xdebug

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm", "-F"]
