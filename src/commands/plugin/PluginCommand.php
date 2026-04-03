<?php

namespace nova\commands\plugin;

use nova\commands\BaseCommand;
use nova\console\Output;

class PluginCommand extends BaseCommand
{
    private function help()
    {
        Output::usage("nova plugin <command> [options]");
        Output::section("Commands");
        Output::commandRow("list",             "List all available plugins");
        Output::commandRow("add <name>",   "Install a plugin");
        Output::commandRow("remove <name>","Uninstall a plugin");
        Output::writeln();
    }

    public function init(): void
    {
       $pluginManager = new PluginManager($this);

       if (count($this->options) < 1){
           $this->help();
           return;
       }

       //删除第一个参数
       $condition =  array_shift($this->options);

       switch ($condition){
           case "list":
               $pluginManager->list();
               break;
           case "add":
               if (count($this->options) < 1)
                   Output::error("Please specify the plugin name.");
                else{
                    foreach ($this->options as $option) {
                        Output::info("Install Plugin $option");
                        $pluginManager->add($option);
                    }
                }


               break;
           case "remove":
                if (count($this->options) < 1)
                     Output::error("Please specify the plugin name.");
                else {
                    foreach ($this->options as $option) {
                        Output::info("Uninstall Plugin $option");
                        $pluginManager->remove($option);
                    }
                }
               break;
           default:
                $this->help();

       }
    }
}