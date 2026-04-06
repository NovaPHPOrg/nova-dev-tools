<?php

namespace nova\commands\format;

use nova\commands\BaseCommand;
use nova\console\Output;

class FormatCommand extends BaseCommand
{

    public function init(): void
    {
        Output::info("Formatting code ...");
        $this->exec("php php-cs-fixer.phar fix src --allow-risky=yes");
        Output::success("Code formatting completed successfully");
    }
}

