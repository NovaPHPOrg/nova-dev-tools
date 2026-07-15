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
        $configFile = $this->workingDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config.php';
        if (!file_exists($configFile)) {
            $configFile = $this->workingDir . DIRECTORY_SEPARATOR . 'example.config.php';
        }

        $config = [];
        if (file_exists($configFile)) {
            $config = include $configFile;
        }

        $extensions = $this->detectExtensions();
        $dbServices = '';
        $appDepends = '';
        $volumesSection = '';
        $configUpdated = false;

        // 根据 config 决定是否有 DB
        if (isset($config['db']) || in_array('pdo_mysql', $extensions) || in_array('mysqli', $extensions)) {
            $dbName = $config['db']['db'] ?? 'nova';
            $dbUser = !empty($config['db']['username']) ? $config['db']['username'] : 'root';
            $dbPass = !empty($config['db']['password']) ? $config['db']['password'] : 'root';
            $dbPort = $config['db']['port'] ?? 3306;

            $envUser = $dbUser === 'root' ? '' : "\n      MYSQL_USER: {$dbUser}";

            $dbServices .= <<<EOF
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: {$dbPass}
      MYSQL_DATABASE: {$dbName}{$envUser}
    ports:
      - "{$dbPort}:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - nova-network
    restart: unless-stopped


EOF;
            $appDepends .= "      - mysql\n";
            $volumesSection = "volumes:\n  mysql_data:\n";
            
            if (isset($config['db']['host']) && in_array($config['db']['host'], ['127.0.0.1', 'localhost'])) {
                $config['db']['host'] = 'mysql';
                $configUpdated = true;
                Output::info("Auto-updated DB host to 'mysql' in config for Docker network.");
            }
        }

        // 根据 config 决定是否有 Redis
        if (isset($config['redis']) || in_array('redis', $extensions)) {
            $redisPort = $config['redis']['port'] ?? 6379;
            $redisAuth = !empty($config['redis']['password']) ? " redis-server --requirepass {$config['redis']['password']}" : "";
            
            $dbServices .= <<<EOF
  redis:
    image: redis:alpine
    command:{$redisAuth}
    ports:
      - "{$redisPort}:6379"
    networks:
      - nova-network
    restart: unless-stopped


EOF;
            $appDepends .= "      - redis\n";
            
            if (isset($config['redis']['host']) && in_array($config['redis']['host'], ['127.0.0.1', 'localhost'])) {
                $config['redis']['host'] = 'redis';
                $configUpdated = true;
                Output::info("Auto-updated Redis host to 'redis' in config for Docker network.");
            }
        }

        if ($configUpdated && file_exists($configFile)) {
            $newConfigContent = "<?php\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($configFile, $newConfigContent);
        }

        if ($appDepends !== '') {
            $appDepends = "    depends_on:\n" . rtrim($appDepends, "\n");
        }

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
{$appDepends}

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

{$dbServices}networks:
  nova-network:
    driver: bridge

{$volumesSection}EOF;
        file_put_contents($path, trim($content) . "\n");
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
