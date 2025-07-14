<?php

namespace nova\commands;

use nova\console\ConsoleColor;

abstract class BaseCommand
{
    protected ConsoleColor $consoleColor;
    abstract public function init();
    public string $workingDir;
    protected array $options;
    public function __construct($workingDir, $options)
    {
        $this->consoleColor = new ConsoleColor();
        $this->workingDir = $workingDir;
        $this->options = $options;
    }
   protected function prompt($prompt_msg,$default = ""): string
   {
        $this->echoInfo("$prompt_msg(Default: $default)",false);
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        $received = trim($line);
        if ($received == "exit"){
            $this->echoWarn("操作已取消。");
            exit(0);
        }
        return $received == "" ? $default : $received;
    }

    private function print($message,$style, $newLine = true){
        try {
            echo $this->consoleColor->apply($style, $message) . ($newLine ? "\n" : "");
        }catch (\Exception $e){
            echo $message . "\n";
        }
    }

    function echoWarn($message, $newLine = true): void
    {
        $this->print($message,"bg_light_yellow", $newLine );
    }

    function echoError($message, $newLine = true): void
    {
        $this->print($message,"bg_light_red", $newLine );
    }

    function echoSuccess($message, $newLine = true): void
    {
        $this->print($message,"light_green", $newLine );
    }

    function echoInfo($message, $newLine = true): void
    {
        $this->print($message,"light_blue", $newLine );
    }

    /**
     * 封装删除目录和文件的函数，支持Windows和Linux
     *
     * @param string $path 要删除的路径
     * @return bool 成功返回true，失败返回false
     */
    function removePath(string $path): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows删除目录和文件命令
            if (is_dir($path)) {
                $command = "rmdir /S /Q \"$path\"";
            } else {
                $command = "del /F /Q \"$path\"";
            }
        } else {
            // UNIX-like系统删除目录和文件命令
            $command = "rm -rf \"$path\"";
        }
        return $this->exec($command)!==false;
    }
    protected function getDir($dir): string
    {
        return str_replace("/", DIRECTORY_SEPARATOR, $dir);
    }
    protected function copyDir(string $string, string $string1): void
    {
        if (!is_dir($string1 )) {
            mkdir($string1 , 0777, true);
        }
        $dir = opendir($string);
        while ($file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($string . DIRECTORY_SEPARATOR . $file)) {
                    $this->copyDir($string . DIRECTORY_SEPARATOR . $file, $string1 . DIRECTORY_SEPARATOR . $file);
                } else {

                    copy($string . DIRECTORY_SEPARATOR . $file, $string1 . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);
    }

     function exec($command,$dir = null):bool|string
    {
// ── 1. 定义要捕获的管道 ──
        $descriptorspec = [
            1 => ['pipe', 'w'],   // stdout
            2 => ['pipe', 'w'],   // stderr
        ];

        // ── 2. 启动子进程 ──
        $process = proc_open(
            $command,
            $descriptorspec,
            $pipes,
            $dir        // 这里指定 cwd，无需再 chdir()
        );

        if (!is_resource($process)) {
            $this->echoError("无法启动进程");
            exit(1);
        }

        // ── 3. 读取输出 ──
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // ── 4. 取退出码并关闭进程句柄 ──
        $returnVar = proc_close($process);
        // 正常情况下把 git 的 stdout 列出来
        if ($stdout !== '') {
            $this->echoInfo($stdout);
        }
        // ── 5. 按结果输出信息 ──
        if( $stderr !== '') {
            $this->echoError("Command run failed.");
            $this->echoError($stderr);      // 打印 git 的错误输出
            return false;
        }


        $this->echoSuccess("Command run success.");
        return $stdout;

    }

}