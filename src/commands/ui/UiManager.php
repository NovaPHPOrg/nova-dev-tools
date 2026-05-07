<?php

namespace nova\commands\ui;

use nova\commands\ConfigUtils;
use nova\commands\RemoteManager;
use nova\console\Output;

/**
 * UI 组件管理器：负责组件列表、安装与卸载，以及 package.php 中 config/require 与主项目配置联动。
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

    /** 静默拉取可安装组件列表，仅填充 {@see $data}，不输出任何内容。 */
    private function fetchComponentsList(): bool
    {
        $list = $this->listOrgRepos();
        if ($list === null) {
            return false;
        }

        $this->data = [];
        $executed = [
            'framework',
        ];

        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (in_array($item['name'], $executed, true)) {
                continue;
            }

            $this->data[] = str_replace('nova-', '', $item['name']);
        }

        return true;
    }

    /** 拉取并打印可安装组件列表。 */
    function list(): void
    {
        if (!$this->fetchComponentsList()) {
            Output::error("Failed to fetch components list.");
            return;
        }

        foreach ($this->data as $name) {
            Output::info($name);
        }
    }

    /**
     * 安装指定 UI 组件并处理组件目录中的 package.php（config/require 与项目配置联动）。
     */
    function add(string $pluginName): void
    {
        if (empty($this->data)) {
            if (!$this->fetchComponentsList()) {
                Output::error("Failed to fetch components list.");
                return;
            }
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

        $file = getcwd() . "/src/app/static/components/{$this->getSaveName($pluginName)}/package.php";
        if (file_exists($file)) {
            $config = include $file;
            if (isset($config['config'])) {
                $this->conf->merge($config['config']);
                $this->exampleConf->merge($config['config']);

            }

            if (isset($config['require'])) {
                foreach ($config['require'] as $item) {
                    $this->add($item);
                }
            }
        }
    }

    /** 卸载指定 UI 组件并回滚 package.php 声明的联动配置。 */
    function remove(string $pluginName): void
    {
        $file = getcwd() . "/src/app/static/components/{$this->getSaveName($pluginName)}/package.php";
        if (file_exists($file)) {
            $config = include $file;
            if (isset($config['config'])) {
                $this->conf->remove_keys($config['config']);
                $this->exampleConf->remove_keys($config['config']);
            }
            if (isset($config['require'])) {
                foreach ($config['require'] as $item) {
                    $this->remove($item);
                }
            }
        }

        Output::info("Uninstalling component $pluginName...");
        $this->command->removeSubmodule("./src/app/static/components/{$this->getSaveName($pluginName)}");
        Output::info("Component $pluginName uninstalled successfully.");
    }

}