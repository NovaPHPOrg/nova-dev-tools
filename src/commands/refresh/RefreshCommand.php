<?php

namespace nova\commands\refresh;

use nova\commands\BaseCommand;

class RefreshCommand extends BaseCommand
{

    public function init(): void
    {
        $gitmodules = parse_ini_file('.gitmodules', true, INI_SCANNER_TYPED);

        foreach ($gitmodules as $section => $config) {
            if (!isset($config['path'])) {
                continue;
            }

            $path = $config['path'];
            $this->echoInfo("Refresh index: $path");

            $this->exec('git update-index --really-refresh', $path);
        }

    }

}