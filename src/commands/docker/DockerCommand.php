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

        $exists = file_exists($dockerfile) || file_exists($compose);

        if ($exists) {
            $overwrite = $this->prompt("Docker files already exist. Overwrite? [y/N]", "n");
            if (strtolower($overwrite) !== 'y') {
                Output::info("Aborted.");
                return;
            }
        }

        file_put_contents($dockerfile, self::dockerfileContent());
        Output::step("Created Dockerfile");

        file_put_contents($compose, self::composeContent());
        Output::step("Created docker-compose.yml");

        Output::success("Docker files generated successfully.");
        Output::writeln();
        Output::info("Run 'docker compose up -d' to start the service.");
        Output::writeln();
    }

    public static function dockerfileContent(): string
    {
        return <<<'EOF'
FROM php:8.3-cli-alpine

# Install common utilities
RUN apk add --no-cache curl zip unzip git sqlite

# Install PHP extensions using mlocati's script
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions opcache curl gd mbstring pcntl posix pdo pdo_sqlite sqlite3

WORKDIR /app

# Copy project files
COPY src/ /app/

# Setup permissions
RUN mkdir -p /app/runtime \
      && chown -R www-data:www-data /app \
      && chmod -R 755 /app/runtime \
      && chmod +x /app/nova/plugin/workerman/workerman.sh

EXPOSE 9528

CMD ["sh","/app/nova/plugin/workerman/workerman.sh","start"]

EOF;
    }

    public static function composeContent(): string
    {
        return <<<'EOF'
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "9528:9528"
    volumes:
      - ./src:/app
    restart: unless-stopped

EOF;
    }
}
