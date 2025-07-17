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

    function addSubmodule(string $submoduleUrl, string $path): void
    {
        // 检查子模块是否已存在
        if (is_dir($path)) {
            $this->baseCommand->echoError("Submodule directory '$path' already exists.");
            exit(1);
        }
        // 拉取子模块
        $command = "git submodule add --force  $submoduleUrl $path";
        if (!$this->baseCommand->exec($command)) {
            $this->baseCommand->echoError("Failed to add submodule.");
            $this->removeSubmodule($path);
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule added at '$path'.");

        //git submodule update --init --force --recursive
        // 初始化并更新子模块
       /* if (!$this->baseCommand->exec("git submodule update --init --recursive")) {
            $this->baseCommand->echoError("Failed to initialize and update submodule.");
            $this->removeSubmodule($path);
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule initialized and updated.");*/
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