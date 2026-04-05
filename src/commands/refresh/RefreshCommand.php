<?php

namespace nova\commands\refresh;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\console\Output;

class RefreshCommand extends BaseCommand
{

    /**
     * 刷新入口：先修复异常子模块状态，再重建配置并同步旧远程，最后刷新索引。
     *
     * @return void
     */
    public function init(): void
    {
        $this->cleanupMissingSubmodules();
        $this->rebuildGitmodules();
        $this->refreshModules();
        // $this->relink();
    }

    /**
     * 重新创建 static 软链接（或目录链接）。
     *
     * @return void
     */
    function relink(): void
    {
        $dir = "src" . DIRECTORY_SEPARATOR . "app" . DIRECTORY_SEPARATOR . "static";
        $target = "static";

        // 如果目标已存在（不论是链接、文件、目录），都处理掉
        if (file_exists($target) || is_link($target)) {
            if (is_link($target) || is_file($target)) {
                unlink($target);
                Output::info("Removed old file or symbolic link: $target");
            } elseif (is_dir($target)) {
                $this->removePath($target);
                Output::info("Removed old directory: $target");
            }
        }

        // 判断系统平台，组装命令
        if (stripos(PHP_OS, 'WIN') === 0) {
            $linkCmd = "cmd /c mklink /D \"$target\" \"$dir\"";
        } else {
            $linkCmd = "ln -s \"$dir\" \"$target\"";
        }

        Output::info("Create link command: $linkCmd");

        $result = $this->exec($linkCmd);
        if ($result === false) {
            Output::error("Failed to create symbolic link!");
        } else {
            Output::success("Symbolic link created successfully: $target -> $dir");
        }
    }
    /**
     * 从 .git/config 中读取所有子模块配置，返回 [name => url] 映射。
     *
     * @return array<string, string> 键为子模块名（同时作为相对路径），值为远程 URL
     */
    private function getSubmoduleConfigs(): array
    {
        $output = $this->exec('git config --file .git/config --get-regexp "^submodule\\..*\\.url$"');
        if ($output === false || trim($output) === '') {
            return [];
        }

        $configs = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (preg_match('/^submodule\.(.+)\.url\s+(.*)$/', $line, $matches)) {
                $configs[$matches[1]] = $matches[2];
            }
        }
        return $configs;
    }

    /**
     * 刷新已配置子模块索引，并尝试切换到默认分支。
     *
     * @return void
     */
    public function refreshModules(): void
    {
        $configs = $this->getSubmoduleConfigs();
        if (empty($configs)) {
            Output::warn("No submodule entries found in .git/config, skipping refresh.");
            return;
        }

        foreach ($configs as $name => $url) {
            $path = $this->workingDir . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($path)) {
                Output::warn("Submodule path missing, skip refresh: $path");
                continue;
            }

            Output::info("Refresh index: $path");
            (new GitCommand($this))->checkOutDefaultBranch($path);
            $this->exec('git update-index --really-refresh', $path);
        }
        $this->exec('git update-index --really-refresh');
    }

    /**
     * 基于 .git/config 中的 submodule 配置重建 .gitmodules 文件。
     *
     * @return void
     */
    public function rebuildGitmodules(): void
    {
        $gitmodules = $this->workingDir . DIRECTORY_SEPARATOR . '.gitmodules';
        $configs = $this->getSubmoduleConfigs();

        $content = '';
        foreach ($configs as $name => $url) {
            $content .= "[submodule \"$name\"]\n    path = $name\n    url = $url\n";
        }
        file_put_contents($gitmodules, $content);

        Output::success(".gitmodules file rebuilt successfully!");
    }

    /**
     * 检测已不存在的子模块目录，并清理对应残留配置。
     * 直接从 .git/config 读取权威的 path 配置。
     *
     * @return void
     */
    private function cleanupMissingSubmodules(): void
    {
        $configs = $this->getSubmoduleConfigs();
        if (empty($configs)) {
            return;
        }

        foreach ($configs as $name => $url) {
            Output::info("Checking submodule: $name -> $url");

            $path = $this->workingDir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                continue;
            }

            Output::warn("Submodule directory missing, removing residual metadata: $path");
            $this->cleanupSubmoduleResidual($name, $path);
        }
    }

    /**
     * 清理单个子模块的残留元数据与模块目录。
     *
     * @param string $name 子模块名（配置节名）
     * @param string $path 子模块工作目录路径
     * @return void
     */
    private function cleanupSubmoduleResidual(string $name, string $path): void
    {
        $pathArg = escapeshellarg($path);
        $sectionArg = escapeshellarg("submodule.$name");

        // 这里使用 best-effort：子步骤失败也继续，尽量把残留状态清干净。
        // execSafe() 跨平台替代 "cmd || true" / "cmd 2>/dev/null"，兼容 Windows。
        $this->execSafe("git submodule deinit -f -- $pathArg");
        $this->execSafe("git rm -f --cached -- $pathArg");
        $this->execSafe("git config --file .git/config --remove-section $sectionArg");
        $this->execSafe("git config --file .gitmodules --remove-section $sectionArg");

        $this->removePath('.git/modules/' . $name);
        if ($name !== $path) {
            $this->removePath('.git/modules/' . $path);
        }
    }

}




