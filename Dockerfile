FROM php:8.5-cli-trixie

# curl and json extensions are already compiled into the official cli image
WORKDIR /repo
CMD ["php", "app/send.php"]
