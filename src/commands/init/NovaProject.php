<?php

namespace nova\commands\init;

class NovaProject
{
    public string $name = "nova-project";
    public string $description = "A Nova project";
    public string $version = "1.0.0";
    public string $author = "Nova Team <ankio@ankio.net>";
    public string $license = "MIT";
    public array $scripts = [
        "list" => "php nova.phar plugin list",
        "build" => "php nova.phar build",
        "test" => "php nova.phar test"
    ];
    public string $source = "/src";
}