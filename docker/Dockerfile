FROM php:8.5-cli-trixie

# git + unzip for composer package installs; sqlite/mbstring/curl are already
# compiled into the official cli image
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /repo/app
CMD ["php", "artisan", "list"]
