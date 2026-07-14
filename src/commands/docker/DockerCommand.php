<?php

namespace nova\commands\docker;

use nova\commands\BaseCommand;
use nova\console\Output;

class DockerCommand extends BaseCommand
{
    public function init()
    {
        Output::section("Docker Setup");

        $dockerfile = $this->workingDir . DIRECTORY_SEPARATOR . 'Dockerfile';
        $compose = $this->workingDir . DIRECTORY_SEPARATOR . 'docker-compose.yml';
        $nginx = $this->workingDir . DIRECTORY_SEPARATOR . 'nginx.conf';

        $exists = file_exists($dockerfile) || file_exists($compose);

        if ($exists) {
            $overwrite = $this->prompt("Docker files already exist. Overwrite? [y/N]", "n");
            if (strtolower($overwrite) !== 'y') {
                Output::info("Aborted.");
                return;
            }
        }

        $this->generateDockerfile($dockerfile);
        $this->generateCompose($compose);
        $this->generateNginx($nginx);

        Output::success("Docker files generated successfully.");
        Output::writeln();
        Output::info("Run 'docker-compose up -d' to start the service.");
        Output::writeln();
    }

    private function generateDockerfile(string $path): void
    {
        $extensions = $this->detectExtensions();
        $extList = empty($extensions) ? '' : implode(' ', $extensions);

        $content = <<<EOF
FROM php:8.3-fpm-alpine

# Install common utilities
RUN apk add --no-cache curl zip unzip git

# Install PHP extensions using mlocati's script
# This handles all dependencies automatically
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions opcache {$extList}

WORKDIR /app

# Copy project files
COPY . /app/

# Setup permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/runtime

CMD ["php-fpm"]
EOF;
        file_put_contents($path, $content);
        Output::step("Created Dockerfile");
    }

    private function generateCompose(string $path): void
    {
        $content = <<<EOF
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/app
    networks:
      - nova-network
    restart: unless-stopped

  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - .:/app
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - nova-network
    restart: unless-stopped

networks:
  nova-network:
    driver: bridge
EOF;
        file_put_contents($path, $content);
        Output::step("Created docker-compose.yml");
    }

    private function generateNginx(string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        $content = <<<EOF
server {
    listen 80;
    server_name localhost;
    root /app/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF;
        file_put_contents($path, $content);
        Output::step("Created nginx.conf");
    }
}
