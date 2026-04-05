<?php

namespace nova\commands\ui;

use nova\commands\BaseCommand;
use nova\console\Output;

class UiCommand extends BaseCommand
{
    private function help(): void
    {
        Output::usage("nova ui <command> [options]");
        Output::section("Commands");
        Output::commandRow("init", "Scaffold a new Nova Admin UI project");
        Output::commandRow("list", "List all available components");
        Output::commandRow("add <name>", "Install a component");
        Output::commandRow("remove <name>", "Uninstall a component");
        Output::writeln();
    }

    public function init(): void
    {
        $uiManager = new UiManager($this);

        if (count($this->options) < 1) {
            $this->help();
            return;
        }

        $condition = (string) array_shift($this->options);

        switch ($condition) {
            case "init":
                $this->initUiProject($uiManager);
                break;
            case "list":
                $uiManager->list();
                break;
            case "add":
                if (count($this->options) < 1) {
                    Output::error("Please specify the component name.");
                    return;
                }

                foreach ($this->options as $option) {
                    Output::info("Install Component $option");
                    $uiManager->add((string) $option);
                }
                break;
            case "remove":
                if (count($this->options) < 1) {
                    Output::error("Please specify the component name.");
                    return;
                }

                foreach ($this->options as $option) {
                    Output::info("Uninstall component $option");
                    $uiManager->remove((string) $option);
                }
                break;
            default:
                $this->help();
                break;
        }
    }

    private function initUiProject(UiManager $uiManager): void
    {
        $templateDir = $this->resolveTemplateDir('init/ui');
        if ($templateDir === null) {
            Output::error("UI template directory not found: init/ui");
            return;
        }

        if (!$this->copyDir($templateDir, $this->workingDir)) {
            Output::error("Failed to initialize UI template.");
            return;
        }

        $uiManager->installFrameworkModule();

        $link = "ln -s ./src/app/static ./static";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $link = "mklink /D static src/app/static";
        }

        $this->exec($link);
    }
}

