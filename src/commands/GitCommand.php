<?php

namespace nova\commands;

class GitCommand
{
    private BaseCommand $baseCommand;
    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
    }

    function updateSubmodules(): void
    {
        $this->baseCommand->echoInfo("Updating all submodules");

        $gitmodules = parse_ini_file('.gitmodules', true, INI_SCANNER_TYPED);

        foreach ($gitmodules as $section => $config) {
            if (!isset($config['path'])) {
                continue;
            }
            $path = $config['path'];

            $this->baseCommand->echoInfo("Processing submodule at '$path'...");
            $this->baseCommand->echoInfo("Working directory: " . getcwd());

            $real = realpath($path);
            $this->baseCommand->echoInfo("Resolved path: " . ($real ?: 'NOT RESOLVED'));


            // 检查子模块目录是否存在
            if (!is_dir($real)) {
                $this->baseCommand->echoWarn("Submodule directory '$path' does not exist, skipping.");
                continue;
            }
$this->checkOutDefaultBranch($path);
            // 获取当前分支
            $currentBranch = trim($this->baseCommand->exec('git branch --show-current', $real));
            if (!$currentBranch) {
                $this->baseCommand->echoWarn("Could not determine current branch in '$path'.");
                continue;
            }

            $this->baseCommand->echoInfo("Current branch in '$path': '$currentBranch'");

            // 执行 git pull 拉取远程更新
            if (!$this->baseCommand->exec('git pull origin ' . $currentBranch, $real)) {
                $this->baseCommand->echoWarn("Failed to pull from origin in '$path'.");
            } else {
                $this->baseCommand->echoSuccess("Successfully pulled updates for submodule '$path' on branch '$currentBranch'.");
            }
        }

        $this->baseCommand->echoInfo("All submodules processed.");
    }


    function checkOutDefaultBranch($path): void
    {
        $this->baseCommand->echoInfo("Checking out default branch in: $path");
        
        // 获取所有分支
        $branchOutput = $this->baseCommand->exec("git branch", $path);
        if ($branchOutput === false) {
            $this->baseCommand->echoError("Failed to get branches in: $path");
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
            $this->baseCommand->echoError("No valid branch found in: $path");
            return;
        }
        
        $this->baseCommand->echoInfo("Switching to branch: $defaultBranch in: $path");
        
        // 切换到默认分支
        $result = $this->baseCommand->exec("git switch $defaultBranch", $path);
        if ($result === false) {
            $this->baseCommand->echoError("Failed to switch to branch '$defaultBranch' in: $path");
        } else {
            $this->baseCommand->echoSuccess("Successfully switched to branch '$defaultBranch' in: $path");
        }
    }

    function addSubmodule(string $submoduleUrl, string $path): void
    {
        // 检查子模块是否已存在
        if (is_dir($path)) {
            $this->baseCommand->echoError("Submodule directory '$path' already exists.");
            exit(1);
        }
        // 拉取子模块
        $command = "git submodule add --force  $submoduleUrl $path";
        $this->baseCommand->exec($command);
        $this->baseCommand->echoSuccess("Submodule added at '$path'.");

        //git submodule update --init --force --recursive
        // 初始化并更新子模块
        $this->baseCommand->exec("git submodule update --init --recursive");
        $this->checkOutDefaultBranch($path);
        $this->baseCommand->echoSuccess("Submodule initialized and updated.");
    }

    function removeSubmodule(string $path): void
    {
        // 移除子模块目录
        if (is_dir($path)) {
            if (!$this->baseCommand->removePath($path)) {
                $this->baseCommand->echoError("Failed to remove submodule directory '$path'.");
              //  exit(1);
            }
            $this->baseCommand->echoSuccess("Submodule directory '$path' removed.");
        } else {
            $this->baseCommand->echoError("Submodule directory '$path' does not exist.");
         //   exit(1);
        }

        // 从 .gitmodules 文件中移除子模块配置
        $command = "git submodule deinit -f $path";
        if (!$this->baseCommand->exec($command)) {
            $this->baseCommand->echoError("Failed to deinit submodule '$path'.");
           // exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule '$path' deinitialized.");

        // 从 .git/config 文件中移除子模块配置
        $command = "git rm -f $path";
        if (!$this->baseCommand->exec($command)) {
            $this->baseCommand->echoError("Failed to remove submodule configuration for '$path'.");
           // exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule configuration for '$path' removed.");
        // 删除 .git/modules 目录下的子模块目录
        $modulePath = ".git/modules/$path";
        if (is_dir($modulePath)) {
            if (!$this->baseCommand->removePath($modulePath)) {
                $this->baseCommand->echoError("Failed to remove submodule directory '$modulePath'.");
              //  exit(1);
            }
            $this->baseCommand->echoSuccess("Submodule directory '$modulePath' removed.");
        }
    }
}