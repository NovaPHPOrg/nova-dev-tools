<?php

namespace nova\commands\refresh;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\console\Output;

class RefreshCommand extends BaseCommand
{

    public function init(): void
    {

        $this->rebuildGitmodules();
        $this->refreshModules();
       // $this->relink();
    }

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
                $this->deleteDir($target);
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
    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }


    public function refreshModules(): void
    {

        $gitmodules = parse_ini_file('.gitmodules', true, INI_SCANNER_TYPED);

        foreach ($gitmodules as $section => $config) {
            if (!isset($config['path'])) {
                continue;
            }

            $path = $config['path'];
            Output::info("Refresh index: $path");
            (new GitCommand($this))->checkOutDefaultBranch($path);
            $this->exec('git update-index --really-refresh', $path);
        }
        $this->exec('git update-index --really-refresh');

    }

    public function rebuildGitmodules(): void
    {
        // 清空 .gitmodules 文件
        file_put_contents('.gitmodules', '');

        // 获取子模块 URL 信息
        $output = $this->exec('git config --file .git/config --get-regexp "^submodule\..*\.url$"');
        if ($output === false) {
            Output::error("Failed to get submodule URLs!");
            return;
        }

        // 拆分每一行
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (preg_match('/^submodule\.(.+)\.url\s+(.*)$/', $line, $matches)) {
                $name = $matches[1];
                $url  = $matches[2];
                // 写入 .gitmodules
                $entry = "[submodule \"$name\"]\n    path = $name\n    url = $url\n";
                file_put_contents('.gitmodules', $entry, FILE_APPEND);
            }
        }

        Output::success(".gitmodules file rebuilt successfully!");
    }

}