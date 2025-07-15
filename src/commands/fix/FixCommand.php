<?php

namespace nova\commands\fix;

use nova\commands\BaseCommand;

class FixCommand extends BaseCommand
{

    public function init(): void
    {
        $this->echoInfo("fix code ....");
        $this->exec("php php-cs-fixer.phar fix src --allow-risky=yes");
        $this->exec('git add -- "src/**/*.php"');
        $this->exec('git commit -m ":art: Code formatting"');
        $this->echoSuccess("fix code success");
    }
}