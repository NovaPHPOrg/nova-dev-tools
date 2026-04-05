<?php

namespace nova\commands;


use nova\console\Output;

class GitCommand
{
    private BaseCommand $baseCommand;
    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
    }

    function updateSubmodules(): void
    {
        Output::info("Updating all submodules");

        $gitmodules = parse_ini_file('.gitmodules', true, INI_SCANNER_TYPED);

        foreach ($gitmodules as $section => $config) {
            if (!isset($config['path'])) {
                continue;
            }
            $path = $config['path'];

            Output::info("Processing submodule at '$path'...");
            Output::info("Working directory: " . getcwd());

            $real = realpath($path);
            Output::info("Resolved path: " . ($real ?: 'NOT RESOLVED'));


            // 检查子模块目录是否存在
            if (!is_dir($real)) {
                Output::warn("Submodule directory '$path' does not exist, skipping.");
                continue;
            }
$this->checkOutDefaultBranch($path);
            // 获取当前分支
            $currentBranch = trim($this->baseCommand->exec('git branch --show-current', $real));
            if (!$currentBranch) {
                Output::warn("Could not determine current branch in '$path'.");
                continue;
            }

            Output::info("Current branch in '$path': '$currentBranch'");

            // 执行 git pull 拉取远程更新
            if (!$this->baseCommand->exec('git pull origin ' . $currentBranch, $real)) {
                Output::warn("Failed to pull from origin in '$path'.");
            } else {
                Output::success("Successfully pulled updates for submodule '$path' on branch '$currentBranch'.");
            }
        }

        Output::info("All submodules processed.");
    }


    function checkOutDefaultBranch($path): void
    {
        Output::info("Checking out default branch in: $path");

        // 获取所有分支
        $branchOutput = $this->baseCommand->exec("git branch", $path);
        if ($branchOutput === false) {
            Output::error("Failed to get branches in: $path");
            return;
        }
        
        $branches = explode("\n", trim($branchOutput));
        $defaultBranch = null;
        
        foreach ($branches as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // 跳过 detached HEAD 状态
            if (str_contains($line, '(HEAD detached at') || str_contains($line, '(HEAD detached from')) {
                continue;
            }
            
            // 移除星号标记（当前分支）
            $branchName = trim(str_replace('*', '', $line));
            
            // 优先选择 main 分支，然后是 master 分支
            if ($branchName === 'main') {
                $defaultBranch = 'main';
                break;
            } elseif ($branchName === 'master') {
                $defaultBranch = 'master';
            } elseif ($defaultBranch === null) {
                // 如果没有找到 main 或 master，选择第一个非 detached 的分支
                $defaultBranch = $branchName;
            }
        }
        
        if ($defaultBranch === null) {
            Output::error("No valid branch found in: $path");
            return;
        }
        
        Output::info("Switching to branch: $defaultBranch in: $path");

        // 切换到默认分支
        $result = $this->baseCommand->exec("git switch $defaultBranch", $path);
        if ($result === false) {
            Output::error("Failed to switch to branch '$defaultBranch' in: $path");
        } else {
            Output::success("Successfully switched to branch '$defaultBranch' in: $path");
        }
    }

    function addSubmodule(string $submoduleUrl, string $path): void
    {
        // 规范化路径：去掉 ./ 前缀，计算绝对路径
        $normalizedPath = ltrim($path, './');
        $absolutePath = $this->baseCommand->workingDir . DIRECTORY_SEPARATOR . $normalizedPath;

        // 检查子模块是否已存在
        if (is_dir($absolutePath)) {
            Output::warn("Submodule directory '$path' already exists, skip add.");
            return;
        }
        // git submodule add --force 要求 .gitmodules 文件已存在于工作区
        $gitmodulesPath = $this->baseCommand->workingDir . DIRECTORY_SEPARATOR . '.gitmodules';
        if (!file_exists($gitmodulesPath)) {
            file_put_contents($gitmodulesPath, '');
        }
        // 拉取子模块
        $command = "git submodule add --force  $submoduleUrl $path";
        $result = $this->baseCommand->exec($command, $this->baseCommand->workingDir);
        if ($result === false) {
            Output::error("Failed to add submodule at '$path'.");
            return;
        }
        Output::success("Submodule added at '$path'.");

        // 只更新当前子模块（指定路径），--force 强制检出 "Reactivating" 场景下的工作区
        $this->baseCommand->exec("git submodule update --init --force -- $normalizedPath", $this->baseCommand->workingDir);
        $this->checkOutDefaultBranch($absolutePath);
        Output::success("Submodule initialized and updated.");
    }

    function removeSubmodule(string $path): void
    {
        // 移除子模块目录
        if (is_dir($path)) {
            if (!$this->baseCommand->removePath($path)) {
                Output::error("Failed to remove submodule directory '$path'.");
              //  exit(1);
            }
            Output::success("Submodule directory '$path' removed.");
        } else {
            Output::error("Submodule directory '$path' does not exist.");
         //   exit(1);
        }

        // 从 .gitmodules 文件中移除子模块配置
        $command = "git submodule deinit -f $path";
        if (!$this->baseCommand->exec($command, $this->baseCommand->workingDir)) {
            Output::error("Failed to deinit submodule '$path'.");
           // exit(1);
        }
        Output::success("Submodule '$path' deinitialized.");

        // 从 .git/config 文件中移除子模块配置
        $command = "git rm -f $path";
        if (!$this->baseCommand->exec($command, $this->baseCommand->workingDir)) {
            Output::error("Failed to remove submodule configuration for '$path'.");
           // exit(1);
        }
        Output::success("Submodule configuration for '$path' removed.");
        // 删除 .git/modules 目录下的子模块目录
        $modulePath = ".git/modules/$path";
        if (is_dir($modulePath)) {
            if (!$this->baseCommand->removePath($modulePath)) {
                Output::error("Failed to remove submodule directory '$modulePath'.");
              //  exit(1);
            }
            Output::success("Submodule directory '$modulePath' removed.");
        }
    }
}