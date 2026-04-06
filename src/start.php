<?php
namespace nova;
use nova\console\Output;

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

function help(): void
{
    Output::banner("Nova Dev Tools", "v" . \nova\VERSION);

    Output::writeln();
    Output::usage("nova <command> [options]");

    Output::section("General");
    Output::commandRow("help",    "Display this help message");
    Output::commandRow("version", "Show devtools version");

    Output::section("Project");
    Output::commandRow("init",    "Create a new Nova project");
    Output::commandRow("build",   "Build project as .phar package or .zip archive");
    Output::commandRow("test",    "Manage project tests");
    Output::subCommandRow("test list",       "List all discovered tests");
    Output::subCommandRow("test all",        "Run all discovered tests");
    Output::subCommandRow("test run <name>", "Run one or more tests by name");
    Output::commandRow("format",  "Format code style (php-cs-fixer)");
    Output::commandRow("refresh", "Force-update git index (reset cached files)");
    Output::commandRow("update",  "Update all git submodules");
    Output::commandRow("migrate", "Migrate nova-ui submodules from git.ankio.icu → GitHub");

    Output::section("Server");
    Output::commandRow("serve",   "Manage the local development server");
    Output::subCommandRow("serve start",   "Start the server");
    Output::subCommandRow("serve stop",    "Stop the server");
    Output::subCommandRow("serve restart", "Restart the server");
    Output::subCommandRow("serve reload",  "Reload the server");
    Output::subCommandRow("serve status",  "Show server status");

    Output::section("Plugins");
    Output::commandRow("plugin",  "Manage Nova PHP plugins");
    Output::subCommandRow("plugin list",              "List all available plugins");
    Output::subCommandRow("plugin add <name>",    "Install a plugin");
    Output::subCommandRow("plugin remove <name>", "Uninstall a plugin");

    Output::section("UI");
    Output::commandRow("ui",      "Manage Nova Admin UI components");
    Output::subCommandRow("ui init",              "Scaffold a new Nova Admin UI project");
    Output::subCommandRow("ui list",              "List all available components");
    Output::subCommandRow("ui add <name>",    "Install a component");
    Output::subCommandRow("ui remove <name>", "Uninstall a component");

    Output::writeln();
    Output::muted("Tip: type 'exit' at any prompt to cancel the current operation.");
    Output::writeln();
}


// get command line arguments
if(count($argv) < 2){
    help();
    exit();
}

$command = strtolower(str_replace("-", "", $argv[1]));
$workingDir = getcwd();
$options = array_slice($argv, 2);

Output::workingDir($workingDir);

switch ($command){
    case "version":
    case "v":
        Output::writeln(
            Output::apply(['bold', 'light_cyan'], "  ⚡ Nova Dev Tools ") .
            Output::apply('white', "v" . VERSION)
        );
        break;
    default:
        $cls = "nova\\commands\\" . $command . "\\" . ucfirst($command)."Command";
        if(class_exists($cls)) {
            $obj = new $cls($workingDir, $options);
            $obj->init();
        }else{
            Output::error("Unknown command: $command");
            Output::writeln();
            help();
            break;
        }
}



