<?php

namespace nova\commands\plugin;

use nova\commands\BaseCommand;
use nova\commands\ConfigUtils;
use nova\commands\GitCommand;

class PluginManager
{
    private BaseCommand $baseCommand;
    private GitCommand $command;
    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
        $this->command = new GitCommand($baseCommand);
    }

    private string $orgName = "NovaPHPOrg";
    function listGitHubRepos(): ?array
    {
        $url = "https://api.github.com/orgs/$this->orgName/repos";
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => [
                    "User-Agent: PHP",
                ]
            ]
        ];
        $context = stream_context_create($opts);

        $result = @file_get_contents($url, false, $context);
        if ($result === FALSE) {
            return null;
        }

        return json_decode($result, true);
    }
    private array $data = [];
    function list()
    {
        $list = $this->listGitHubRepos();
        if ($list === null) {
            $this->baseCommand->echoError("Failed to fetch plugin list.");
            return;
        }
        $this->data = [];
        $executed = [
            "nova-server",
            "nova",
            "nova-framework",
            "nova-dev-tools"
        ];
        foreach ($list as $item){
            if (in_array($item["name"], $executed)){
                continue;
            }
            $name = str_replace("nova-", "", $item["name"]);
            $this->baseCommand->echoInfo($name);
            $this->data[] = $name;
        }
    }

    function getSaveName($pluginName){
        if(str_contains($pluginName,"-")){
            return str_split($pluginName,"-")[0];
        }
        return $pluginName;
    }

    function add($pluginName): void
    {
        if (empty($data)){
            $this->list();
        }
        if (!in_array($pluginName, $this->data)){
            $this->baseCommand->echoError("Plugin $pluginName not found.");
            return;
        }
        $this->baseCommand->echoInfo("Installing plugin $pluginName...");

        $this->command->addSubmodule("https://github.com/NovaPHPOrg/nova-$pluginName","./src/nova/plugin/{$this->getSaveName($pluginName)}");
        $this->baseCommand->echoInfo("Plugin $pluginName installed successfully.");
        // 判断是否有package.php
        $file = getcwd()."/src/nova/plugin/{$this->getSaveName($pluginName)}/package.php";
        if (file_exists($file)){
            $config = include $file;
            // return [
            //    "config"=>[
            //        "framework.start"=>[
            //            "nova\\plugin\\task\\Task",
            //        ]
            //    ]
            //];
            if (isset($config['config'])){
                $conf = new ConfigUtils();
                $conf->merge($config['config']);
                unset($conf);
            }

            if (isset($config["require"])){
                foreach ($config["require"] as $item){
                    $this->add($item);
                }
            }
        }
    }

    function remove($pluginName): void
    {
        $file = getcwd()."/src/nova/plugin/{$this->getSaveName($pluginName)}/package.php";
        if (file_exists($file)) {
            $config = include $file;
            if (isset($config['config'])){
                $conf = new ConfigUtils();
                $conf->remove_keys($config['config']);
                unset($conf);
            }
            if (isset($config["require"])){
                foreach ($config["require"] as $item){
                    $this->remove($item);
                }
            }

        }
        $this->baseCommand->echoInfo("Uninstalling plugin $pluginName...");
        $this->command->removeSubmodule("./src/nova/plugin/$pluginName");
        $this->baseCommand->echoInfo("Plugin $pluginName uninstalled successfully.");
    }


}