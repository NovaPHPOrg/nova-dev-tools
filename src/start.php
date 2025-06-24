<?php
namespace nova;
use nova\commands\init\InitCommand;
$config = require "config.php";
define("nova\VERSION", $config['version']);
define("nova\SUPPORTED_PHP_VERSION", $config['php_version']);
// check php version
if (version_compare(phpversion(), SUPPORTED_PHP_VERSION, '<')) {
    exit("This script requires PHP ".SUPPORTED_PHP_VERSION." or later.\n");
}
// check if running from command line
if (!isset($argv)){
    exit("This script is meant to be run from the command line.\n");
}

include "autoload.php";

function help()
{
    echo "Usage: nova <command> [options]\n";
    echo "Available commands:\n";
    echo "  help    - this message\n";
    echo "  version - devtools version\n";
    echo "  init    - create an new nova project\n";
    echo "  build   - build nova project as an phar package or an zip archive\n";
    echo "  test <name>   - test nova project\n";
    echo "  update  - update all submodules\n";
    echo "  plugin  <list> - list plugins of nova php\n";
    echo "  plugin  <add> <plugin-name> - install a plugin\n";
    echo "  plugin  <remove> <plugin-name> - uninstall a plugin\n";
    echo "  ui  <init>    - create an new nova-admin ui project\n";
    echo "  ui  <list> - list components of nova php\n";
    echo "  ui  <add> <component-name> - install a component\n";
    echo "  ui  <remove> <component-name> - uninstall a component\n";
}


// get command line arguments
if(count($argv) < 2){
    help();
    exit();
}

$command = strtolower(str_replace("-", "", $argv[1]));
$workingDir = getcwd();
$options = array_slice($argv, 2);
echo "Working directory: $workingDir\n";
switch ($command){
    case "version":
    case "v":
        echo "Nova " . VERSION . "\n";
        break;
    default:
        $cls = "nova\\commands\\" . $command . "\\" . ucfirst($command)."Command";
        if(class_exists($cls)) {
            $obj = new $cls($workingDir, $options);
            $obj->init();
        }else{
            help();
            break;
        }
}

