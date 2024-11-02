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
        $this->baseCommand->echoInfo("Updating all submodules...");
        exec("git submodule update --init  --remote  --force --recursive", $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to update submodule.");
            exit(1);
        }

        $this->baseCommand->echoInfo("Submodule updated successfully.");
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
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to add submodule.");
            $this->removeSubmodule($path);
            exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule added at '$path'.");

        //git submodule update --init --force --recursive
        // 初始化并更新子模块
        exec("git submodule update --init --recursive", $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to initialize and update submodule.");
            $this->removeSubmodule($path);
            exit(1);
        }
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
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->baseCommand->echoError("Failed to deinit submodule '$path'.");
           // exit(1);
        }
        $this->baseCommand->echoSuccess("Submodule '$path' deinitialized.");

        // 从 .git/config 文件中移除子模块配置
        $command = "git rm -f $path";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
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