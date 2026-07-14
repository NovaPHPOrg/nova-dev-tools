<?php
namespace app;

if (file_exists($f = __DIR__ . '/../extends.txt')) {
    foreach (explode(',', file_get_contents($f)) as $ext) {
        if (($ext = trim($ext)) !== '' && !extension_loaded($ext)) {
            die("Fatal Error: Extension '$ext' is required but not loaded.\n");
        }
    }
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}
include __DIR__ . '/../nova/framework/bootstrap.php';