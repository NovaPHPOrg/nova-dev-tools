<?php

namespace nova\commands\serve;

use nova\commands\BaseCommand;
use nova\console\Output;

class ServeCommand extends BaseCommand
{
    private string $modulePath = "./src/nova/plugin/workerman";

    public function init(): void
    {
        if (count($this->options) < 1) {
            $this->help();
            return;
        }

        $action = strtolower((string) array_shift($this->options));

        switch ($action) {
            case "start":
            case "stop":
            case "restart":
            case "reload":
            case "status":
                $this->runServeAction($action);
                break;
            default:
                $this->help();
                break;
        }
    }

    private function help(): void
    {
        Output::usage("nova serve <command> [options]");
        Output::section("Commands");
        Output::commandRow("start",   "Start the local development server (use --open to auto-open UI)");
        Output::commandRow("stop",    "Stop the local development server");
        Output::commandRow("restart", "Restart the local development server");
        Output::commandRow("reload",  "Reload the local development server");
        Output::commandRow("status",  "Show local server status");
        Output::writeln();
    }

    private function runServeAction(string $action): void
    {
        $moduleDir = $this->getModuleDir();

        if ($moduleDir === null) {
            Output::error("Serve module not found. Re-run `php nova.phar init` to embed it.");
            return;
        }

        $extra = "";
        $openUI = $this->takeFlag('--open');

        if (!empty($this->options)) {
            $extra = " " . implode(" ", array_map('escapeshellarg', $this->options));
        }

        if ($openUI && $action === 'start') {
            $this->openUI();
        }

        $launcherScript = PHP_OS_FAMILY === 'Windows' ? 'workerman.bat' : 'workerman.sh';
        $launcherPath = $moduleDir . DIRECTORY_SEPARATOR . $launcherScript;

        if (PHP_OS_FAMILY === 'Windows') {
            $command = "cmd /c " . escapeshellarg($launcherPath) . " " . escapeshellarg($action) . $extra;
        } else {
            $command = "sh " . escapeshellarg($launcherPath) . " " . escapeshellarg($action) . $extra;
        }

        $this->execStream($command, $moduleDir);
    }

    private function getModuleDir(): ?string
    {
        $moduleDir = $this->workingDir . DIRECTORY_SEPARATOR . $this->modulePath;

        if (!is_dir($moduleDir)) {
            return null;
        }

        return $moduleDir;
    }

    private function openUI(): void
    {
        $configFile = $this->workingDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config.php';
        $port = 10211;
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (isset($config['workerman']['port'])) {
                $port = $config['workerman']['port'];
            }
        }

        $url = "http://127.0.0.1:{$port}/";
        Output::info("UI will be opened at {$url} in 2 seconds...");

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "start {$url}";
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $cmd = "open {$url}";
        } else {
            $cmd = "xdg-open {$url}";
        }

        // 异步后台运行打开浏览器，并延迟2秒等服务启动
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B cmd /c \"timeout /t 2 >nul & {$cmd}\"", "r"));
        } else {
            exec("(sleep 2 && {$cmd}) > /dev/null 2>&1 &");
        }
    }
}

