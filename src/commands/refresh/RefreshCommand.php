<?php

namespace nova\commands\refresh;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;

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
                $this->echoInfo("已删除旧的文件或符号链接：$target");
            } elseif (is_dir($target)) {
                $this->deleteDir($target);
                $this->echoInfo("已删除旧的目录：$target");
            }
        }

        // 判断系统平台，组装命令
        if (stripos(PHP_OS, 'WIN') === 0) {
            $linkCmd = "cmd /c mklink /D \"$target\" \"$dir\"";
        } else {
            $linkCmd = "ln -s \"$dir\" \"$target\"";
        }

        $this->echoInfo("创建链接命令：$linkCmd");

        $result = $this->exec($linkCmd);
        if ($result === false) {
            $this->echoError("创建符号链接失败！");
        } else {
            $this->echoSuccess("符号链接创建成功：$target -> $dir");
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
            $this->echoInfo("Refresh index: $path");
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
            $this->echoError("获取子模块URL失败！");
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

        $this->echoSuccess(".gitmodules 文件已成功重建！");
    }

}