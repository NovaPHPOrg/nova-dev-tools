<?php

namespace nova\commands\plugin;

use nova\commands\ConfigUtils;
use nova\commands\RemoteManager;
use nova\commands\ui\UiManager;
use nova\console\Output;

/**
 * 插件管理器：负责插件列表、安装、卸载与插件配置联动。
 *
 * package.php 可选字段：config（并入项目配置）、require（依赖的其他 PHP 插件）、
 * ui_require（随本插件一并安装的 NovaPHPOrgUI 组件名）。
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
                $this->conf->merge($config['config']);
                $this->exampleConf->merge($config['config']);
            }

            if (!empty($config['ui_require']) && is_array($config['ui_require'])) {
                $ui = new UiManager($this->baseCommand);
                $ui->setSkipCache($this->skipCache);
                foreach ($config['ui_require'] as $item) {
                    $ui->add((string) $item);
                }
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
                $this->conf->remove_keys($config['config']);
                $this->exampleConf->remove_keys($config['config']);
            }
            if (isset($config["require"])) {
                foreach ($config["require"] as $item) {
                    $this->remove($item);
                }
            }

            if (!empty($config['ui_require']) && is_array($config['ui_require'])) {
                $ui = new UiManager($this->baseCommand);
                $ui->setSkipCache($this->skipCache);
                foreach ($config['ui_require'] as $item) {
                    $ui->remove((string) $item);
                }
            }
        }

        Output::info("Uninstalling plugin $pluginName...");
        $this->command->removeSubmodule("./src/nova/plugin/{$this->getSaveName($pluginName)}");
        Output::info("Plugin $pluginName uninstalled successfully.");
    }
}