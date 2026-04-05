<?php

namespace nova\commands\ui;

use nova\commands\RemoteManager;
use nova\console\Output;

/**
 * UI 组件管理器：负责组件列表、安装与卸载。
 */
class UiManager extends RemoteManager
{
    /** UI 组件来源组织。 */
    protected function getOrgName(): string
    {
        return "NovaPHPOrgUI";
    }

    /** 初始化 UI framework 子模块。 */
    public function installFrameworkModule(): void
    {
        $this->installRepoSubmodule('framework', './src/app/static/framework');
    }

    /** 拉取并打印可安装组件列表。 */
    function list(): void
    {
        $list = $this->listOrgRepos();
        if ($list === null) {
            Output::error("Failed to fetch components list.");
            return;
        }

        $this->data = [];
        $executed = [
            "framework",
        ];

        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (in_array($item["name"], $executed, true)) {
                continue;
            }

            $name = str_replace("nova-", "", $item["name"]);
            Output::info($name);
            $this->data[] = $name;
        }
    }

    /** 安装指定 UI 组件。 */
    function add(string $pluginName): void
    {
        if (empty($this->data)) {
            $this->list();
        }

        if (!in_array($pluginName, $this->data, true)) {
            Output::error("Component $pluginName not found.");
            return;
        }

        Output::info("Installing component $pluginName...");
        $this->installRepoSubmodule(
            $pluginName,
            "./src/app/static/components/{$this->getSaveName($pluginName)}"
        );
        Output::info("Component $pluginName installed successfully.");
    }

    /** 卸载指定 UI 组件。 */
    function remove(string $pluginName): void
    {
        Output::info("Uninstalling component $pluginName...");
        $this->command->removeSubmodule("./src/app/static/components/{$this->getSaveName($pluginName)}");
        Output::info("Component $pluginName uninstalled successfully.");
    }

}