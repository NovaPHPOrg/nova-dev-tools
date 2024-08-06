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
        "test" => "echo \"Error: no test specified\" && exit 1",
        "build" => "echo \"Error: no build specified\" && exit 1",
        "start" => "echo \"Error: no start specified\" && exit 1"
    ];
    public string $source = "/src";
}