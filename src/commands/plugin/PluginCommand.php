<?php

namespace nova\commands\plugin;

use nova\commands\BaseCommand;
use nova\console\Output;

class PluginCommand extends BaseCommand
{
    private function help(): void
    {
        Output::usage("nova plugin <command> [options]");
        Output::section("Commands");
        Output::commandRow("list [--force]", "List all available plugins");
        Output::commandRow("add <name> [--force]", "Install a plugin");
        Output::commandRow("remove <name>", "Uninstall a plugin");
        Output::writeln();
    }

    public function init(): void
    {
        $pluginManager = new PluginManager($this);
        $pluginManager->setSkipCache($this->takeFlag('--force'));

        if (count($this->options) < 1) {
            $this->help();
            return;
        }

        $condition = (string) array_shift($this->options);

        switch ($condition) {
            case "list":
                $pluginManager->list();
                break;
            case "add":
                if (count($this->options) < 1) {
                    Output::error("Please specify the plugin name.");
                    return;
                }

                foreach ($this->options as $option) {
                    Output::info("Install Plugin $option");
                    $pluginManager->add((string) $option);
                }
                break;
            case "remove":
                if (count($this->options) < 1) {
                    Output::error("Please specify the plugin name.");
                    return;
                }

                foreach ($this->options as $option) {
                    Output::info("Uninstall Plugin $option");
                    $pluginManager->remove((string) $option);
                }
                break;
            default:
                $this->help();
                break;
        }
    }
}