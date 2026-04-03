<?php

namespace nova\commands;

use nova\console\ConsoleColor;
use nova\console\Output;
use Phar;

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
    protected function copyDir(string $string, string $string1): bool
    {
        if (!is_dir($string)) {
            Output::error("目录不存在：$string");
            return false;
        }

        if (!is_dir($string1 )) {
            mkdir($string1 , 0777, true);
        }
        $dir = opendir($string);
        if ($dir === false) {
            Output::error("无法打开目录：$string");
            return false;
        }
        while ($file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($string . DIRECTORY_SEPARATOR . $file)) {
                    if (!$this->copyDir($string . DIRECTORY_SEPARATOR . $file, $string1 . DIRECTORY_SEPARATOR . $file)) {
                        closedir($dir);
                        return false;
                    }
                } else {
                    if (!copy($string . DIRECTORY_SEPARATOR . $file, $string1 . DIRECTORY_SEPARATOR . $file)) {
                        Output::error("复制文件失败：" . $string . DIRECTORY_SEPARATOR . $file);
                        closedir($dir);
                        return false;
                    }
                }
            }
        }
        closedir($dir);
        return true;
    }

    protected function resolveTemplateDir(string $relative): ?string
    {
        $relative = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);

        // PHAR mode: prefer files next to the phar directory as project root, then fallback to embedded templates.
        $runningPhar = Phar::running(false);
        if ($runningPhar !== '') {
            $pharDir = dirname($runningPhar);
            $externalPath = $pharDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relative;
            if (is_dir($externalPath)) {
                return $externalPath;
            }

            $pharPath = 'phar://' . $runningPhar . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            if (is_dir($pharPath)) {
                return $pharPath;
            }
        }

        // Source mode fallback: relative to repository src directory.
        $sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative;
        if (is_dir($sourcePath)) {
            return $sourcePath;
        }

        return null;
    }

    function exec($command, $dir = null): bool|string
    {
        Output::step("$ $command");

        if ($dir !== null && !is_dir($dir)) {
            Output::error("工作目录不存在：$dir");
            return false;
        }

        $descriptorspec = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes, $dir);

        if (!is_resource($process)) {
            Output::error("无法启动进程");
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($stdout !== '') {
            foreach (explode("\n", trim($stdout)) as $line) {
                Output::muted($line);
            }
        }

        if ($stderr !== '') {
            foreach (explode("\n", trim($stderr)) as $line) {
                Output::muted($line);
            }
        }

        if ($exitCode !== 0) {
            Output::error("命令执行失败，退出码：$exitCode");
            return false;
        }

        Output::success("命令执行成功");
        return $stdout;
    }


}