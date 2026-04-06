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
        Output::commandRow("start",   "Start the local development server");
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
        if (!empty($this->options)) {
            $extra = " " . implode(" ", array_map('escapeshellarg', $this->options));
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
}

