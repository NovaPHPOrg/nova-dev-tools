<?php

namespace nova\commands\plugin;

use nova\commands\BaseCommand;

class PluginManager
{
    private BaseCommand $baseCommand;
    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
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
    function list()
    {
        $list = $this->listGitHubRepos();
        if ($list === null) {
            $this->baseCommand->echoError("Failed to fetch plugin list.");
            return;
        }
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
            $this->baseCommand->echoInfo(str_replace("nova-", "", $item["name"]));
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
        $this->baseCommand->echoInfo("Installing plugin $pluginName...");

        $this->addSubmodule("https://github.com/NovaPHPOrg/nova-$pluginName","./src/nova/plugin/{$this->getSaveName($pluginName)}");
        $this->baseCommand->echoInfo("Plugin $pluginName installed successfully.");
    }

    function remove($pluginName)
    {
        $this->baseCommand->echoInfo("Uninstalling plugin $pluginName...");
        $this->removeSubmodule("./src/nova/plugin/$pluginName");
        $this->baseCommand->echoInfo("Plugin $pluginName uninstalled successfully.");
    }

    function update()
    {
        $this->baseCommand->echoInfo("Updating plugins...");
        exec("git submodule update --init  --remote  --force --recursive", $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to update plugins.");
            exit(1);
        }

        $this->baseCommand->echoInfo("Plugins updated successfully.");
    }

    function addSubmodule(string $submoduleUrl, string $path): void
    {
        // 拉取子模块
        $command = "git submodule add $submoduleUrl $path";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to add submodule.");
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule added at '$path'.");

        //git submodule update --init --force --recursive
        // 初始化并更新子模块
        exec("git submodule update --init --recursive", $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to initialize and update submodule.");
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule initialized and updated.");
    }

    function removeSubmodule(string $path): void
    {
        // 移除子模块目录
        if (is_dir($path)) {
            if (!$this->baseCommand->removePath($path)) {
                $this->baseCommand->echoError("Failed to remove submodule directory '$path'.");
                exit(1);
            }
            $this->baseCommand->echoSuccess("Submodule directory '$path' removed.");
        } else {
            $this->baseCommand->echoError("Submodule directory '$path' does not exist.");
            exit(1);
        }

        // 从 .gitmodules 文件中移除子模块配置
        $command = "git submodule deinit -f $path";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to deinit submodule '$path'.");
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule '$path' deinitialized.");

        // 从 .git/config 文件中移除子模块配置
        $command = "git rm -f $path";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to remove submodule configuration for '$path'.");
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule configuration for '$path' removed.");

        // 删除 .git/modules 目录下的子模块目录
        $modulePath = ".git/modules/$path";
        if (is_dir($modulePath)) {
            if (!$this->baseCommand->removePath($modulePath)) {
                $this->baseCommand->echoError("Failed to remove submodule directory '$modulePath'.");
                exit(1);
            }
            $this->baseCommand->echoSuccess("Submodule directory '$modulePath' removed.");
        }
    }
}