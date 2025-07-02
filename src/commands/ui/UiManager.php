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
    /**
     * 通过 cURL 拉取 Gitea 组织仓库列表
     *
     * @return ?array 若调用失败返回 null，成功则为解码后的数组
     */
    function listGiteaRepos(): ?array
    {
        // 构造 URL
        $url = "https://git.ankio.icu/api/v1/orgs/{$this->orgName}/repos";

        // 初始化 cURL 句柄
        $ch = curl_init($url);

        // 常用 cURL 选项
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,          // 结果直接返回而非输出
            CURLOPT_USERAGENT      => 'PHP',         // UA 与原来保持一致
            CURLOPT_TIMEOUT        => 10,            // 可按需调整超时
            CURLOPT_FAILONERROR    => true,          // 4xx/5xx 时让 curl_exec 返回 false
            // ↓↓↓ 下面两行就是“忽略证书”关键
            CURLOPT_SSL_VERIFYPEER => false,  // 不验证对端证书
            CURLOPT_SSL_VERIFYHOST => false,  // 不检查证书域名
        ]);

        // 执行请求
        $raw = curl_exec($ch);

        // 读取状态码与错误信息
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // 网络或 HTTP 错误都返回 null
        if ($raw === false || $httpCode !== 200) {
            // 如有需要，可使用 error_log 打印调试信息：
            echo "cURL error: $curlErr (HTTP $httpCode)\n";
            // error_log("cURL error: $curlErr (HTTP $httpCode)");
            return null;
        }

        // 成功则解析 JSON
        return json_decode($raw, true);
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

        $this->command->addSubmodule("https://git.ankio.icu/nova-ui/nova-$pluginName","./src/app/static/components/{$this->getSaveName($pluginName)}");
        $this->baseCommand->echoInfo("Component $pluginName installed successfully.");
    }

    function remove($pluginName): void
    {
        $this->baseCommand->echoInfo("Uninstalling component $pluginName...");
        $this->command->removeSubmodule("./src/app/static/components/$pluginName");
        $this->baseCommand->echoInfo("Component $pluginName uninstalled successfully.");
    }


}