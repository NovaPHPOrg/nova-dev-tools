<?php

namespace nova\commands\plugin;

use nova\commands\BaseCommand;

class PluginCommand extends BaseCommand
{

    public function init()
    {
       $pluginManager = new PluginManager($this);
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
               $this->echoError("Invalid command.");
               break;
       }
    }
}