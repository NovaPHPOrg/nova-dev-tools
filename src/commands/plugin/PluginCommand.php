<?php

namespace nova\commands\plugin;

use nova\commands\BaseCommand;

class PluginCommand extends BaseCommand
{
    private function help()
    {
        $this->echoInfo("Usage: nova plugin [command] [options]");
        $this->echoInfo("Commands:");
        $this->echoInfo("  list: List all available plugins.");
        $this->echoInfo("  add [pluginName]: Add a plugin.");
        $this->echoInfo("  remove [pluginName]: Remove a plugin.");
        $this->echoInfo("  update: Update all plugins.");
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
               if (count($this->options) < 2)
                   $this->echoError("Please specify the plugin name.");
                else{
                    foreach ($this->options as $option) {
                        $this->echoInfo("Install Plugin $option");
                        $pluginManager->add($option);
                    }
                }


               break;
           case "remove":
                if (count($this->options) < 2)
                     $this->echoError("Please specify the plugin name.");
                else {
                    foreach ($this->options as $option) {
                        $this->echoInfo("Uninstall Plugin $option");
                        $pluginManager->remove($option);
                    }
                }
               break;
           case "update":
               $pluginManager->update();
               break;
           default:
                $this->help();

       }
    }
}