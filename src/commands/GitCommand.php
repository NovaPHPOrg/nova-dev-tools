<?php

namespace nova\commands;

use nova\console\Output;

class GitCommand
{
    private BaseCommand $baseCommand;
    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
    }

    function updateSubmodules(): void
    {
        Output::info("Updating all submodules");
        
        $gitmodulesPath = $this->baseCommand->workingDir . DIRECTORY_SEPARATOR . '.gitmodules';
        if (!file_exists($gitmodulesPath)) {
            Output::warn(".gitmodules file not found, nothing to update.");
            return;
        }

        $gitmodules = parse_ini_file($gitmodulesPath, true, INI_SCANNER_TYPED);
        if (!$gitmodules) {
            Output::error("Failed to parse .gitmodules file.");
            return;
        }

        foreach ($gitmodules as $section => $config) {
            if (!isset($config['path'])) {
                continue;
            }
            $path = $config['path'];

            $absPath = $this->baseCommand->workingDir . DIRECTORY_SEPARATOR . $path;

            Output::info("Processing submodule at '$absPath'...");

            if (!is_dir($absPath)) {
                Output::warn("Submodule directory '$absPath' does not exist, skipping.");
                continue;
            }

            $this->checkOutDefaultBranch($absPath);
            $currentBranch = trim($this->baseCommand->exec('git branch --show-current', $absPath) ?: '');
            
            if (!$currentBranch) {
                Output::warn("Could not determine current branch in '$absPath'.");
                continue;
            }

            Output::info("Current branch in '$absPath': '$currentBranch'");

            if (!$this->baseCommand->exec('git pull origin ' . $currentBranch, $absPath)) {
                Output::warn("Failed to pull from origin in '$absPath'.");
            } else {
                Output::success("Successfully pulled updates for submodule '$absPath' on branch '$currentBranch'.");
            }
        }

        Output::info("All submodules processed.");
    }

    function checkOutDefaultBranch($path): void
    {
        Output::info("Checking out default branch in: $path");

        $defaultBranch = null;

        // Try getting the remote default branch directly
        // Note: Using execSafe to avoid stderr spam if origin/HEAD is not set
        $ref = trim($this->baseCommand->exec("git rev-parse --abbrev-ref origin/HEAD", $path, true) ?: '');
        if ($ref && str_starts_with($ref, 'origin/')) {
            $defaultBranch = substr($ref, 7);
        }

        if (!$defaultBranch) {
            // Fallback: manually parse branch output
            $branchOutput = $this->baseCommand->exec("git branch", $path);
            if ($branchOutput !== false) {
                $branches = explode("\n", trim($branchOutput));
                foreach (['main', 'master'] as $target) {
                    foreach ($branches as $line) {
                        $line = trim(str_replace('*', '', $line));
                        if ($line === $target) {
                            $defaultBranch = $target;
                            break 2;
                        }
                    }
                }
                
                if (!$defaultBranch && !empty($branches)) {
                    foreach ($branches as $line) {
                        if (!str_contains($line, 'detached')) {
                            $defaultBranch = trim(str_replace('*', '', $line));
                            break;
                        }
                    }
                }
            }
        }
        
        if (!$defaultBranch) {
            Output::error("No valid branch found in: $path");
            return;
        }
        
        Output::info("Switching to branch: $defaultBranch in: $path");

        $result = $this->baseCommand->exec("git switch $defaultBranch", $path);
        if ($result === false) {
            Output::error("Failed to switch to branch '$defaultBranch' in: $path");
        } else {
            Output::success("Successfully switched to branch '$defaultBranch' in: $path");
        }
    }

    function addSubmodule(string $submoduleUrl, string $path): void
    {
        $normalizedPath = ltrim($path, './');
        $absolutePath = $this->baseCommand->workingDir . DIRECTORY_SEPARATOR . $normalizedPath;

        if (is_dir($absolutePath)) {
            Output::warn("Submodule directory '$path' already exists, skip add.");
            return;
        }
        
        $gitmodulesPath = $this->baseCommand->workingDir . DIRECTORY_SEPARATOR . '.gitmodules';
        if (!file_exists($gitmodulesPath)) {
            file_put_contents($gitmodulesPath, '');
        }
        
        $command = "git submodule add --force " . escapeshellarg($submoduleUrl) . " " . escapeshellarg($path);
        $result = $this->baseCommand->exec($command, $this->baseCommand->workingDir);
        if ($result === false) {
            Output::error("Failed to add submodule at '$path'.");
            return;
        }
        Output::success("Submodule added at '$path'.");

        $this->baseCommand->exec("git submodule update --init --force -- " . escapeshellarg($normalizedPath), $this->baseCommand->workingDir);
        $this->checkOutDefaultBranch($absolutePath);
        Output::success("Submodule initialized and updated.");
    }

    function removeSubmodule(string $path): void
    {
        if (is_dir($path)) {
            if (!$this->baseCommand->removePath($path)) {
                Output::error("Failed to remove submodule directory '$path'.");
            } else {
                Output::success("Submodule directory '$path' removed.");
            }
        } else {
            Output::error("Submodule directory '$path' does not exist.");
        }

        $command = "git submodule deinit -f " . escapeshellarg($path);
        if (!$this->baseCommand->exec($command, $this->baseCommand->workingDir)) {
            Output::error("Failed to deinit submodule '$path'.");
        } else {
            Output::success("Submodule '$path' deinitialized.");
        }

        $command = "git rm -f " . escapeshellarg($path);
        if (!$this->baseCommand->exec($command, $this->baseCommand->workingDir)) {
            Output::error("Failed to remove submodule configuration for '$path'.");
        } else {
            Output::success("Submodule configuration for '$path' removed.");
        }
        
        $modulePath = ".git/modules/" . ltrim($path, './');
        if (is_dir($modulePath)) {
            if (!$this->baseCommand->removePath($modulePath)) {
                Output::error("Failed to remove submodule directory '$modulePath'.");
            } else {
                Output::success("Submodule directory '$modulePath' removed.");
            }
        }
    }
}