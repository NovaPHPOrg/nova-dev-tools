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
        $this->migrateLegacyUiRemotes();
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
     * 刷新已配置子模块索引，并尝试切换到默认分支。
     *
     * @return void
     */
    public function refreshModules(): void
    {
        $gitmodules = parse_ini_file('.gitmodules', true, INI_SCANNER_TYPED);
        if ($gitmodules === false || $gitmodules === []) {
            Output::warn("No submodule entries found in .gitmodules, skipping refresh.");
            return;
        }

        foreach ($gitmodules as $section => $config) {
            if (!isset($config['path'])) {
                continue;
            }

            $path = $config['path'];
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
        $submodules = $this->getSubmoduleConfigs();

        // 清空 .gitmodules 文件
        file_put_contents('.gitmodules', '');

        foreach ($submodules as $name => $config) {
            if (!isset($config['path'], $config['url'])) {
                continue;
            }
            $path = $config['path'];
            $url = $config['url'];
            $entry = "[submodule \"$name\"]\n    path = $path\n    url = $url\n";
            file_put_contents('.gitmodules', $entry, FILE_APPEND);
        }

        Output::success(".gitmodules file rebuilt successfully!");
    }

    /**
     * 检测已不存在的子模块目录，并清理对应残留配置。
     *
     * @return void
     */
    private function cleanupMissingSubmodules(): void
    {
        $submodules = $this->getSubmoduleConfigs();

        foreach ($submodules as $name => $config) {
            if (!isset($config['path'])) {
                continue;
            }

            $path = $config['path'];
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

    /**
     * 将旧域名 UI 子模块远程迁移到 GitHub，并尝试同步远程分支。
     *
     * @return void
     */
    private function migrateLegacyUiRemotes(): void
    {
        $submodules = $this->getSubmoduleConfigs();

        foreach ($submodules as $name => $config) {
            if (!isset($config['path'], $config['url'])) {
                continue;
            }

            $path = $config['path'];
            $oldUrl = $config['url'];
            $newUrl = $this->mapLegacyUiUrl($oldUrl);

            if ($newUrl === null || $newUrl === $oldUrl) {
                continue;
            }

            Output::info("Migrating submodule remote: $oldUrl -> $newUrl");
            $urlArg = escapeshellarg($newUrl);
            $configKeyArg = escapeshellarg("submodule.$name.url");
            $pathArg = escapeshellarg($path);

            $this->exec("git config --file .git/config $configKeyArg $urlArg");
            $this->exec("git config --file .gitmodules $configKeyArg $urlArg");
            $this->execSafe("git submodule sync -- $pathArg");

            if (!is_dir($path)) {
                Output::warn("Submodule folder missing after URL migration, skip sync: $path");
                continue;
            }

            $this->syncSubmoduleRemote($path, $newUrl);
        }
    }

    /**
     * 将旧 UI 域名的子模块地址映射到 GitHub 组织地址。
     * 匹配失败返回 null，表示不是迁移目标。
     *
     * @param string $url 原始子模块 URL
     * @return string|null 匹配成功返回新 URL，否则返回 null
     */
    private function mapLegacyUiUrl(string $url): ?string
    {
        $trimmedUrl = trim($url);
        $pattern = '~^(?:https?://|ssh://)?(?:git@)?git\.ankio\.icu[:/]nova-ui/(nova-[^/\s]+?)(?:\.git)?/?$~i';

        if (!preg_match($pattern, $trimmedUrl, $matches)) {
            return null;
        }

        // 规则：nova-xxx -> xxx，对应 NovaPHPOrgUI/xxx 仓库。
        $repoName = $matches[1];
        if (str_starts_with($repoName, 'nova-')) {
            $repoName = substr($repoName, 5);
        }

        if ($repoName === '') {
            return null;
        }

        return "https://github.com/NovaPHPOrgUI/$repoName.git";
    }

    /**
     * 远程同步策略：远程分支存在则 pull，不存在则首次 push 并建立 upstream。
     *
     * @param string $path 子模块路径
     * @param string $remoteUrl 迁移后的远程 URL
     * @return void
     */
    private function syncSubmoduleRemote(string $path, string $remoteUrl): void
    {
        $urlArg = escapeshellarg($remoteUrl);
        if ($this->exec("git remote set-url origin $urlArg", $path) === false) {
            $this->exec("git remote add origin $urlArg", $path);
        }

        (new GitCommand($this))->checkOutDefaultBranch($path);
        $branch = $this->resolveActiveBranch($path);
        if ($branch === null) {
            Output::warn("Cannot determine active branch, skip remote sync: $path");
            return;
        }

        $branchArg = escapeshellarg($branch);
        $remoteBranchInfo = $this->exec("git ls-remote --heads origin $branchArg", $path);
        if ($remoteBranchInfo === false) {
            Output::warn("Failed to inspect remote heads for: $path");
            return;
        }

        if (trim($remoteBranchInfo) !== '') {
            $this->exec("git pull --ff-only origin $branchArg", $path);
            return;
        }

        $this->exec("git push -u origin $branchArg", $path);
    }

    /**
     * 分支选择优先级：当前分支 > main > master > 第一个可用分支。
     * detached HEAD 会被忽略。
     *
     * @param string $path 子模块路径
     * @return string|null 可用分支名，无法解析时返回 null
     */
    private function resolveActiveBranch(string $path): ?string
    {
        $branchOutput = $this->exec('git branch --show-current', $path);
        if ($branchOutput !== false) {
            $branch = trim($branchOutput);
            if ($branch !== '') {
                return $branch;
            }
        }

        $allBranchesOutput = $this->exec('git branch', $path);
        if ($allBranchesOutput === false) {
            return null;
        }

        $branches = explode("\n", trim($allBranchesOutput));
        $fallback = null;

        foreach ($branches as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, '(HEAD detached')) {
                continue;
            }

            $branchName = trim(str_replace('*', '', $line));
            if ($branchName === 'main') {
                return 'main';
            }
            if ($branchName === 'master') {
                $fallback = 'master';
                continue;
            }
            if ($fallback === null) {
                $fallback = $branchName;
            }
        }

        return $fallback;
    }

    /**
     * 读取 .git/config 中的子模块 path/url 并按子模块名分组。
     *
     * @return array<string, array<string, string>> 子模块配置映射
     */
    private function getSubmoduleConfigs(): array
    {
        $output = $this->exec('git config --file .git/config --get-regexp "^submodule\\..*\\.(path|url)$"');
        if ($output === false) {
            return [];
        }

        $lines = explode("\n", trim($output));
        $submodules = [];

        foreach ($lines as $line) {
            if (!preg_match('/^submodule\.(.+)\.(path|url)\s+(.*)$/', $line, $matches)) {
                continue;
            }

            $name = $matches[1];
            $key = $matches[2];
            $value = $matches[3];
            $submodules[$name][$key] = $value;
        }

        return $submodules;
    }

}




