<?php

namespace nova\commands;

use nova\console\Output;
use Phar;

abstract class BaseCommand
{
    abstract public function init();
    public string $workingDir;
    protected array $options;

    public function __construct($workingDir, $options)
    {
        $this->workingDir = $workingDir;
        $this->options = $options;
    }

    /**
     * Prompt user for input with optional default value.
     * Delegates to Output::prompt for consistent output handling.
     *
     * @param string $promptMessage The prompt text
     * @param string $default       Default value if user just presses enter
     * @return string               User input or default value
     */
    protected function prompt(string $promptMessage, string $default = ""): string
    {
        return Output::prompt($promptMessage, $default);
    }

    /**
     * Recursively remove a directory or file path.
     * Supports both Windows and Unix-like systems.
     *
     * @param string $path The path to remove
     * @return bool True on success, false on failure
     */
    function removePath(string $path): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows remove directory and file commands
            if (is_dir($path)) {
                $command = "rmdir /S /Q \"$path\"";
            } else {
                $command = "del /F /Q \"$path\"";
            }
        } else {
            // UNIX-like system remove directory and file commands
            $command = "rm -rf \"$path\"";
        }
        return $this->exec($command)!==false;
    }
    protected function getDir($dir): string
    {
        return str_replace("/", DIRECTORY_SEPARATOR, $dir);
    }
    protected function copyDir(string $sourceDir, string $targetDir): bool
    {
        if (!is_dir($sourceDir)) {
            Output::error("Directory does not exist: $sourceDir");
            return false;
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            Output::error("Failed to create directory: $targetDir");
            return false;
        }

        $dir = opendir($sourceDir);
        if ($dir === false) {
            Output::error("Unable to open directory: $sourceDir");
            return false;
        }

        while ($file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $file;
                $targetPath = $targetDir . DIRECTORY_SEPARATOR . $file;

                if (is_dir($sourcePath)) {
                    if (!$this->copyDir($sourcePath, $targetPath)) {
                        closedir($dir);
                        return false;
                    }
                } else {
                    if (!copy($sourcePath, $targetPath)) {
                        Output::error("Failed to copy file: $sourcePath");
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
            Output::error("Command failed with exit code: $exitCode");
            return false;
        }

        Output::success("Command executed successfully");
        return $stdout;
    }


}