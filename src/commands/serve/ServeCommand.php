<?php

namespace nova\commands\serve;

use nova\commands\BaseCommand;
use nova\console\Output;

class ServeCommand extends BaseCommand
{
    private string $modulePath = "./src/nova/workerman";

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
            $this->echoError("Serve module not found. Re-run `php nova.phar init` to embed it.");
            return;
        }

        $entryScript = $this->findEntryScript($moduleDir);
        if ($entryScript === null) {
            $this->echoError("Serve entry script not found in module.");
            $this->echoInfo("Expected one of: start.php, server.php, bin/start.php");
            return;
        }

        $extra = "";
        if (!empty($this->options)) {
            $extra = " " . implode(" ", array_map('escapeshellarg', $this->options));
        }

        $command = "php " . escapeshellarg($entryScript) . " " . $action . $extra;
        $this->exec($command, $moduleDir);
    }

    private function getModuleDir(): ?string
    {
        $normalizedPath = str_replace('/', DIRECTORY_SEPARATOR, ltrim($this->modulePath, './'));
        $moduleDir = $this->workingDir . DIRECTORY_SEPARATOR . $normalizedPath;

        if (!is_dir($moduleDir)) {
            return null;
        }

        return $moduleDir;
    }

    private function findEntryScript(string $moduleDir): ?string
    {
        $candidates = [
            "start.php",
            "server.php",
            "bin/start.php",
        ];

        foreach ($candidates as $candidate) {
            $fullPath = $moduleDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (is_file($fullPath)) {
                return $candidate;
            }
        }

        return null;
    }
}

