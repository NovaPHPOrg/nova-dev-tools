<?php

namespace nova\commands\ui;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\commands\plugin\PluginManager;
use Phar;

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
           case "init":
               if (Phar::running()) {
                   // 如果在 .phar 中运行，使用 phar:// 协议进行访问
                   $sourceFile =  Phar::running() . DIRECTORY_SEPARATOR ;
               } else {
                   // 如果未打包成 .phar，则直接使用文件系统路径
                   $sourceFile = '';
               }
               $this->copyDir($sourceFile.$this->getDir("../../init/ui"),$this->workingDir);
               $git = new GitCommand($this);
               $git->addSubmodule("https://git.ankio.icu/nova-ui/framework","./src/app/static/framework");
               $link = "ln -s ./src/app/static ./static";
               // 如果是windows系统，使用mklink命令
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                     $link = "mklink /D static src/app/static";
                }
               $this->exec($link);
               break;
           case "list":
               $pluginManager->list();
               break;
           case "add":
               if (count($this->options) < 1)
                   $this->echoError("Please specify the component name.");
                else{
                    foreach ($this->options as $option) {
                        $this->echoInfo("Install Component $option");
                        $pluginManager->add($option);
                    }
                }
               break;
           case "remove":
                if (count($this->options) < 1)
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