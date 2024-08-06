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

    public function init()
    {
       $pluginManager = new PluginManager($this);

       if (count($this->options) < 1){
           $this->help();
           return;
       }

       switch ($this->options[0]){
           case "list":
               $pluginManager->list();
               break;
           case "add":
               if (count($this->options) < 2)
                   $this->echoError("Please specify the plugin name.");
                else

                    $pluginManager->add($this->options[1]);
               break;
           case "remove":
                if (count($this->options) < 2)
                     $this->echoError("Please specify the plugin name.");
                else
                    $pluginManager->remove($this->options[1]);
               break;
           case "update":
               $pluginManager->update();
               break;
           default:
                $this->help();

       }
    }
}