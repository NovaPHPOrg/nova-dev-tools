<?php
namespace nova;
use nova\commands\init\InitCommand;

const VERSION = "1.0.0";
const SUPPORTED_PHP_VERSION = "7.4.0";
// check php version
if (version_compare(phpversion(), SUPPORTED_PHP_VERSION, '<')) {
    exit("This script requires PHP ".SUPPORTED_PHP_VERSION." or later.\n");
}
// check if running from command line
if (!isset($argv)){
    exit("This script is meant to be run from the command line.\n");
}

include "autoload.php";

function help($command = "")
{
    switch ($command){

        default:
            echo "Usage: nova <command> [options]\n";
            echo "Available commands:\n";
            echo "  help\n";
            echo "  version\n";
            echo "  init\n";
            echo "  build\n";
            echo "  test\n";
            echo "  deploy\n";
            break;
    }
}


// get command line arguments
if(count($argv) < 2){
    help();
    exit();
}

$command = strtolower(str_replace("-", "", $argv[1]));
$workingDir = getcwd();
$options = array_slice($argv, 2);
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

