<?php

namespace nova\commands\fix;

use nova\commands\BaseCommand;
use nova\console\Output;

class FixCommand extends BaseCommand
{

    public function init(): void
    {
        Output::info("Fixing code ...");
        $this->exec("php php-cs-fixer.phar fix src --allow-risky=yes");
        $this->exec('git add -- "src/**/*.php"');
        $this->exec('git commit -m ":art: Code formatting"');
        Output::success("Code fixing completed successfully");
    }
}