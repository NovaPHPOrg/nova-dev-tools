<?php

namespace nova\commands\ui;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use Phar;

class UiManager
{
    private BaseCommand $baseCommand;
    private GitCommand $command;
    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
        $this->command = new GitCommand($baseCommand);
    }

    private string $orgName = "nova-ui";
    function listGiteaRepos(): ?array
    {
        // https://git.ankio.net/api/v1/orgs/nova-ui/repos
        $url = "https://git.ankio.net/api/v1/orgs/$this->orgName/repos";
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
    function list(): void
    {
        $list = $this->listGiteaRepos();
        if ($list === null) {
            $this->baseCommand->echoError("Failed to fetch components list.");
            return;
        }
        $this->data = [];
        $executed = [
            "framework",
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
            $this->baseCommand->echoError("Component $pluginName not found.");
            return;
        }
        $this->baseCommand->echoInfo("Installing component $pluginName...");

        $this->command->addSubmodule("https://git.ankio.net/nova-ui/nova-$pluginName","./src/static/components/{$this->getSaveName($pluginName)}");
        $this->baseCommand->echoInfo("Component $pluginName installed successfully.");
    }

    function remove($pluginName): void
    {
        $this->baseCommand->echoInfo("Uninstalling component $pluginName...");
        $this->command->removeSubmodule("./src/static/components/$pluginName");
        $this->baseCommand->echoInfo("Component $pluginName uninstalled successfully.");
    }


}