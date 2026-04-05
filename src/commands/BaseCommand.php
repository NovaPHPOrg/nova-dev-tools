<?php

namespace nova\commands;

use FilesystemIterator;
use nova\console\Output;
use Phar;

/**
 * 基础命令类
 * 为所有命令提供公共功能，包括用户交互、文件操作和命令执行
 */
abstract class BaseCommand
{
    /**
     * 初始化命令
     * 每个具体命令必须实现此方法
     */
    abstract public function init();

    /** @var string 命令执行的工作目录 */
    public string $workingDir;

    /** @var array 命令选项 */
    protected array $options;

    /**
     * 构造函数
     *
     * @param string $workingDir 工作目录
     * @param array $options 命令选项
     */
    public function __construct(string $workingDir, array $options)
    {
        $this->workingDir = $workingDir;
        $this->options = $options;
    }

    /**
     * 提示用户输入，支持默认值
     * 将用户交互委托给 Output::prompt 以保证一致的输出处理
     *
     * @param string $promptMessage 提示信息
     * @param string $default 用户未输入时的默认值
     * @return string 用户输入或默认值
     */
    protected function prompt(string $promptMessage, string $default = ""): string
    {
        return Output::prompt($promptMessage, $default);
    }

    /**
     * 递归删除目录或文件
     *
     * @param string $path 要删除的路径
     * @return bool 成功返回 true，失败返回 false
     */
    function removePath(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path)) {
            return @unlink($path);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileinfo) {
            $fileinfo->isDir() ? @rmdir($fileinfo->getPathname()) : @unlink($fileinfo->getPathname());
        }

        return @rmdir($path);
    }

    /**
     * 递归复制目录及其所有内容
     *
     * @param string $sourceDir 源目录路径
     * @param string $targetDir 目标目录路径
     * @return bool 成功返回 true，失败返回 false
     */
    protected function copyDir(string $sourceDir, string $targetDir): bool
    {
        if (!is_dir($sourceDir)) {
            Output::error("Directory does not exist: $sourceDir");
            return false;
        }

        @mkdir($targetDir, 0777, true);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $path => $file) {
            $relPath = substr($path, strlen($sourceDir) + 1);
            $target = $targetDir . DIRECTORY_SEPARATOR . $relPath;
            $file->isDir() ? @mkdir($target, 0777, true) : @copy($path, $target);
        }

        return true;
    }

    /**
     * 解析模板目录路径
     * 支持 PHAR 模式和源代码模式
     * - PHAR 模式：优先使用 PHAR 外部的文件，然后回退到嵌入的模板
     * - 源代码模式：相对于 src 目录
     *
     * @param string $relative 相对路径
     * @return string|null 解析后的完整路径，如果不存在返回 null
     */
    protected function resolveTemplateDir(string $relative): ?string
    {
        $relative = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);

        $runningPhar = Phar::running(false);
        $basePath = $runningPhar !== ''
            ? 'phar://' . $runningPhar
            : dirname(__DIR__);

        $path = $basePath . DIRECTORY_SEPARATOR . $relative;
        return is_dir($path) ? $path : null;
    }

    /**
     * 执行系统命令
     * 实时流式读取 stdout / stderr，以 docker compose 风格的滚动日志输出（始终只显示最新 5 行）。
     *
     * @param string      $command 要执行的命令
     * @param string|null $dir     命令执行的工作目录，null 表示使用默认目录
     * @return bool|string 成功返回标准输出内容，失败返回 false
     */
    function exec(string $command, string $dir = null): bool|string
    {
        Output::step("$ $command");

        if ($dir !== null && !is_dir($dir)) {
            Output::error("Working directory does not exist: $dir");
            return false;
        }

        $descriptorspec = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes, $dir);

        if (!is_resource($process)) {
            Output::error("Failed to start process");
            return false;
        }

        // 设置非阻塞，配合 stream_select 实时读取
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $open   = [1 => true, 2 => true]; // 标记每条管道是否仍然打开

        Output::liveLogBegin(5);

        while ($open[1] || $open[2]) {
            $read = [];
            if ($open[1]) $read[] = $pipes[1];
            if ($open[2]) $read[] = $pipes[2];

            $write  = null;
            $except = null;

            // 最多等待 200ms，避免 CPU 空转
            if (stream_select($read, $write, $except, 0, 200000) === false) {
                break;
            }

            foreach ($read as $stream) {
                $isStdout = ($stream === $pipes[1]);
                // 循环读取当前可用的所有行
                while (($line = fgets($stream)) !== false) {
                    if ($isStdout) {
                        $stdout .= $line;
                    } else {
                        $stderr .= $line;
                    }
                    $trimmed = rtrim($line);
                    if ($trimmed !== '') {
                        Output::liveLog($trimmed);
                    }
                }
                // fgets 返回 false 且已到 EOF，标记该管道已关闭
                if (feof($stream)) {
                    if ($isStdout) {
                        $open[1] = false;
                    } else {
                        $open[2] = false;
                    }
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        Output::liveLogEnd();

        if ($exitCode !== 0) {
            Output::error("Command failed with exit code: $exitCode");
            return false;
        }

        Output::success("Command executed successfully");
        return $stdout.$stderr;
    }

}