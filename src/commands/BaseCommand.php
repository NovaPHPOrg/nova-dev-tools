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
     * 执行系统命令，实时流式读取 stdout / stderr。
     * 使用 fread + proc_get_status 轮询，兼容 Windows（stream_select 在 Windows 管道上不可用）。
     *
     * @param string      $command     要执行的命令
     * @param string|null $dir         工作目录，null 表示使用当前目录
     * @param bool        $ignoreError 为 true 时忽略非零退出码与 stderr 输出（跨平台替代 "|| true" / "2>/dev/null"）
     * @return bool|string 成功（或 $ignoreError=true）返回 stdout，失败返回 false
     */
    function exec(string $command, string $dir = null, bool $ignoreError = false): bool|string
    {
        if ($ignoreError) {
            Output::muted("~ $command");
        } else {
            Output::step("$ $command");
        }

        if ($dir !== null && !is_dir($dir)) {
            Output::error("Working directory does not exist: $dir");
            return $ignoreError ? '' : false;
        }

        $descriptorspec = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes, $dir);

        if (!is_resource($process)) {
            Output::error("Failed to start process");
            return $ignoreError ? '' : false;
        }

        // 非阻塞模式，允许实时轮询读取
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout   = '';
        $stderr   = '';
        $outBuf   = ''; // stdout 未完整行缓冲
        $errBuf   = ''; // stderr 未完整行缓冲
        $exitCode = -1;

        // 将缓冲区中完整的行（以 \n 结尾）冲刷到滚动日志
        $flushLines = function (string &$buf, bool $show): void {
            while (($nl = strpos($buf, "\n")) !== false) {
                $line = rtrim(substr($buf, 0, $nl));
                $buf  = substr($buf, $nl + 1);
                if ($line !== '' && $show) {
                    Output::liveLog($line);
                }
            }
        };

        Output::liveLogBegin(5);

        do {
            $chunk1 = fread($pipes[1], 8192);
            if ($chunk1 !== false && $chunk1 !== '') {
                $stdout .= $chunk1;
                $outBuf .= $chunk1;
            }

            $chunk2 = fread($pipes[2], 8192);
            if ($chunk2 !== false && $chunk2 !== '') {
                $stderr .= $chunk2;
                $errBuf .= $chunk2;
            }

            $flushLines($outBuf, true);
            $flushLines($errBuf, !$ignoreError);

            $status = proc_get_status($process);

            if (!$status['running']) {
                // 进程已退出，捕获退出码（部分系统只有第一次调用有效）
                $exitCode = (int)$status['exitcode'];

                // 切回阻塞模式，彻底清空 OS 管道缓冲区
                stream_set_blocking($pipes[1], true);
                stream_set_blocking($pipes[2], true);

                $r1 = stream_get_contents($pipes[1]);
                if ($r1 !== false && $r1 !== '') { $stdout .= $r1; $outBuf .= $r1; }

                $r2 = stream_get_contents($pipes[2]);
                if ($r2 !== false && $r2 !== '') { $stderr .= $r2; $errBuf .= $r2; }

                $flushLines($outBuf, true);
                $flushLines($errBuf, !$ignoreError);

                // 刷新末尾没有换行符的残余内容
                if ($outBuf !== '') {
                    $line = rtrim($outBuf);
                    if ($line !== '') Output::liveLog($line);
                }
                if ($errBuf !== '' && !$ignoreError) {
                    $line = rtrim($errBuf);
                    if ($line !== '') Output::liveLog($line);
                }

                break;
            }

            // 无新数据时短暂休眠，避免 CPU 空转
            // 此处使用轮询而非 stream_select，以兼容 Windows（pipe 不支持 stream_select）
            if (($chunk1 === false || $chunk1 === '') && ($chunk2 === false || $chunk2 === '')) {
                usleep(10000); // 10ms
            }
        } while (true);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $closeCode = proc_close($process);
        // proc_close 在 Windows 上有时返回 -1（exitcode 已被 proc_get_status 消费）
        // 优先使用 proc_close 的值，否则回退到 proc_get_status 捕获的值
        if ($closeCode >= 0) {
            $exitCode = $closeCode;
        }

        Output::liveLogEnd();

        if ($exitCode !== 0) {
            if (!$ignoreError) {
                Output::error("Command failed with exit code: $exitCode");
            }
            return $ignoreError ? $stdout : false;
        }

        if (!$ignoreError) {
            Output::success("Command executed successfully");
        }
        return $stdout;
    }

    /**
     * 以最大努力模式执行命令：忽略非零退出码，不显示 stderr。
     *
     * 跨平台替代方案，无需任何 Shell 特有语法：
     *   - Unix:    cmd || true          →  $this->execSafe('cmd')
     *   - Unix:    cmd 2>/dev/null      →  $this->execSafe('cmd')
     *   - Windows: cmd 2>nul            →  $this->execSafe('cmd')
     *
     * @param string      $command 命令（不含任何 Shell 错误抑制符号）
     * @param string|null $dir     工作目录
     * @return string stdout 内容（失败时返回空字符串）
     */
    protected function execSafe(string $command, string $dir = null): string
    {
        $result = $this->exec($command, $dir, true);
        return is_string($result) ? $result : '';
    }

}