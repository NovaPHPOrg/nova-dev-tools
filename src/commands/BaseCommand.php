<?php

namespace nova\commands;

use nova\console\ConsoleColor;

abstract class BaseCommand
{
    protected ConsoleColor $consoleColor;
    abstract public function init();
    protected string $workingDir;
    protected array $options;
    public function __construct($workingDir, $options)
    {
        $this->consoleColor = new ConsoleColor();
        $this->workingDir = $workingDir;
        $this->options = $options;
    }
   protected function prompt($prompt_msg,$default = ""): string
   {
        $this->echoInfo($prompt_msg,false);
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        $received = trim($line);
        if ($received == "exit"){
            $this->echoWarn("操作已取消。");
            exit(0);
        }
        return $received == "" ? $default : $received;
    }

    private function print($message,$style, $newLine = true){
        try {
            echo $this->consoleColor->apply($style, $message) . ($newLine ? "\n" : "");
        }catch (\Exception $e){
            echo $message . "\n";
        }
    }

    function echoWarn($message, $newLine = true)
    {
        $this->print($message,"bg_light_yellow", $newLine );
    }

    function echoError($message, $newLine = true)
    {
        $this->print($message,"bg_light_red", $newLine );
    }

    function echoSuccess($message, $newLine = true)
    {
        $this->print($message,"light_green", $newLine );
    }

    function echoInfo($message, $newLine = true)
    {
        $this->print($message,"light_blue", $newLine );
    }

}