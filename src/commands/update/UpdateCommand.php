<?php

namespace nova\commands\update;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;

class UpdateCommand extends BaseCommand
{

    public function init(): void
    {
        $git = new GitCommand($this);
        $git->updateSubmodules();
    }
}