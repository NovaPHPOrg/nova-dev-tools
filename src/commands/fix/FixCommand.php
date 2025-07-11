<?php

namespace nova\commands\fix;

use nova\commands\BaseCommand;

class FixCommand extends BaseCommand
{

    public function init(): void
    {
        $this->echoInfo("fix code ....");
        shell_exec("php php-cs-fixer.phar fix src --allow-risky=yes");
        $this->echoSuccess("fix code success");
    }
}