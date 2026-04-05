<?php

namespace nova\commands\plugin;

use nova\commands\ConfigUtils;
use nova\commands\RemoteManager;
use nova\console\Output;

/**
 * 插件管理器：负责插件列表、安装、卸载与插件配置联动。
 */
class PluginManager extends RemoteManager
{
    /** 插件来源组织。 */
    protected function getOrgName(): string
    {
        return "NovaPHPOrg";
    }

    /**
     * 将插件名转换为远程仓库名。
     */
    protected function buildPluginRepoName(string $pluginName): string
    {
        return "nova-$pluginName";
    }

    /** 初始化核心 framework 子模块。 */
    public function installFrameworkModule(): void
    {
        $this->installRepoSubmodule($this->buildPluginRepoName('framework'), './src/nova/framework');
    }

    /** 初始化核心 workerman 子模块。 */
    public function installServeModule(): void
    {
        $this->installRepoSubmodule($this->buildPluginRepoName('workerman'), './src/nova/plugin/workerman');
    }

    /** 静默拉取可安装插件列表，仅填充 $this->data，不输出任何内容。返回是否成功。 */
    private function fetchList(): bool
    {
        $list = $this->listOrgRepos();
        if ($list === null) {
            return false;
        }

        $this->data = [];
        $excluded = [
            "nova-server",
            "nova",
            "nova-framework",
            "nova-dev-tools"
        ];

        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (in_array($item["name"], $excluded, true)) {
                continue;
            }
            $this->data[] = str_replace("nova-", "", $item["name"]);
        }

        return true;
    }

    /** 拉取并打印可安装插件列表。 */
    function list(): void
    {
        if (!$this->fetchList()) {
            Output::error("Failed to fetch plugin list.");
            return;
        }

        foreach ($this->data as $name) {
            Output::info($name);
        }
    }

    /**
     * 安装插件并处理 package.php 中的 config/require 依赖。
     */
    function add(string $pluginName): void
    {
        if (empty($this->data)) {
            if (!$this->fetchList()) {
                Output::error("Failed to fetch plugin list.");
                return;
            }
        }

        if (!in_array($pluginName, $this->data, true)) {
            Output::error("Plugin $pluginName not found.");
            return;
        }

        Output::info("Installing plugin $pluginName...");
        $this->installRepoSubmodule(
            $this->buildPluginRepoName($pluginName),
            "./src/nova/plugin/{$this->getSaveName($pluginName)}"
        );
        Output::info("Plugin $pluginName installed successfully.");

        $file = getcwd() . "/src/nova/plugin/{$this->getSaveName($pluginName)}/package.php";
        if (file_exists($file)) {
            $config = include $file;
            if (isset($config['config'])) {
                $conf = new ConfigUtils();
                $conf->merge($config['config']);
                unset($conf);
            }

            if (isset($config["require"])) {
                foreach ($config["require"] as $item) {
                    $this->add($item);
                }
            }
        }
    }

    /**
     * 卸载插件并回滚 package.php 中声明的联动配置。
     */
    function remove(string $pluginName): void
    {
        $file = getcwd() . "/src/nova/plugin/{$this->getSaveName($pluginName)}/package.php";
        if (file_exists($file)) {
            $config = include $file;
            if (isset($config['config'])) {
                $conf = new ConfigUtils();
                $conf->remove_keys($config['config']);
                unset($conf);
            }
            if (isset($config["require"])) {
                foreach ($config["require"] as $item) {
                    $this->remove($item);
                }
            }
        }

        Output::info("Uninstalling plugin $pluginName...");
        $this->command->removeSubmodule("./src/nova/plugin/{$this->getSaveName($pluginName)}");
        Output::info("Plugin $pluginName uninstalled successfully.");
    }
}