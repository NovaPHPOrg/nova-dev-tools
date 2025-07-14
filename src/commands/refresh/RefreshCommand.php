<?php

namespace nova\commands\refresh;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\commands\plugin\PluginManager;

class RefreshCommand extends BaseCommand
{

    private function help()
    {
        $this->echoInfo("Usage: nova refresh [dir]");
    }

    public function init(): void
    {
        if (count($this->options) < 1){
            $this->help();
            return;
        }

        $dir  =  array_shift($this->options);

        $this->echoInfo("Refresh index: $dir");
        chdir($dir);
        exec("git update-index --ignore-submodules --really-refresh", $output, $returnVar);
        if ($returnVar !== 0) {
            $this->echoError("Failed to refresh index: $dir.");
            exit(1);
        }
        $this->echoSuccess("Refresh index: $dir success.");
    }
}