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
    /**
     * 列出某个 GitHub 组织的公开仓库
     *
     * @return array|null  成功返回数组，失败返回 null
     */
    function listGitHubRepos(): ?array
    {
        $url = "https://api.github.com/orgs/{$this->orgName}/repos";

        $ch = curl_init($url);

        $headers = [
            'User-Agent: PHP',
            // 'Authorization: Bearer YOUR_GITHUB_TOKEN', // ← 如需鉴权取消注释
            'Accept: application/vnd.github+json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,   // 返回字符串而不是直接输出
            CURLOPT_TIMEOUT        => 10,     // 超时 10 秒
            CURLOPT_HTTPHEADER     => $headers,
            // ———— 若遇到证书问题可用以下两行临时跳过，生产环境应保留校验 ————
             CURLOPT_SSL_VERIFYPEER => false,
             CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);

        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            // 可在此记录日志：curl_strerror($errno)
            return null;
        }

        return json_decode($response, true);
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
            if(!is_array($item)){
                $this->baseCommand->echoWarn("fetch item: ". $item);
                continue;
            }

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
            $result = explode("-", $pluginName, 2);
            return $result[0];
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