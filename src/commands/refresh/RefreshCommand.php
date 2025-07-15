<?php

namespace nova\commands\refresh;

use nova\commands\BaseCommand;

class RefreshCommand extends BaseCommand
{

    public function init(): void
    {

        $this->rebuildGitmodules();
        $this->refreshModules();
        $this->relink();
    }

    function relink(): void
    {
        $dir = "src/app/static";
        $target = "static";

        if(file_exists($target)){
            unlink($target);
        }
        $link = "ln -s $dir $target";
        // 如果是windows系统，使用mklink命令
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $link = "mklink /D $target $dir";
        }
        $this->exec($link);
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

            $this->exec('git update-index --really-refresh', $path);
        }
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

                // 获取 path
                $pathOutput = $this->exec("git config --file .git/config --get submodule." . escapeshellarg($name) . ".path");
                $path = $pathOutput !== false ? trim($pathOutput) : $name;

                // 写入 .gitmodules
                $entry = "[submodule \"$name\"]\n    path = $path\n    url = $url\n";
                file_put_contents('.gitmodules', $entry, FILE_APPEND);
            }
        }

        $this->echoSuccess(".gitmodules 文件已成功重建！");
    }

}