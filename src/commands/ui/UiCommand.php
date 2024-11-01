<?php

namespace nova\commands\ui;

use nova\commands\BaseCommand;
use nova\commands\plugin\PluginManager;

class UiCommand extends BaseCommand
{
    private function help()
    {
        $this->echoInfo("Usage: nova ui [command] [options]");
        $this->echoInfo("Commands:");
        $this->echoInfo("  init: Initialize the UI.");
        $this->echoInfo("  list: List all available ui components.");
        $this->echoInfo("  add [componentName]: Add a component.");
        $this->echoInfo("  remove [componentName]: Remove a component.");
    }

    public function init(): void
    {
       $pluginManager = new UiManager($this);

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
                   $this->echoError("Please specify the component name.");
                else{
                    foreach ($this->options as $option) {
                        $this->echoInfo("Install Component $option");
                        $pluginManager->add($option);
                    }
                }
               break;
           case "remove":
                if (count($this->options) < 2)
                     $this->echoError("Please specify the component name.");
                else {
                    foreach ($this->options as $option) {
                        $this->echoInfo("Uninstall component $option");
                        $pluginManager->remove($option);
                    }
                }
               break;
           default:
                $this->help();

       }
    }
}