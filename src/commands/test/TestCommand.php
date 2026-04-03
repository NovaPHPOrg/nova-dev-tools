<?php

namespace nova\commands\test;

use nova\commands\BaseCommand;
use nova\console\Output;

class TestCommand extends BaseCommand
{

    public function init()
    {


        Output::info("Running tests...");
        //查找tests文件夹里面所有以.test.php结尾的文件
        $dir = $this->workingDir . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR;
        Output::info("Found any Tests in: " . $dir);
        if (!is_dir($dir)) {
            Output::error("No tests found.");
            return;
        }


        $tests = glob($dir. "*Test.php");

        if (count($tests) === 0) {
            Output::error("No tests found.");
            return;
        }

        if (count($this->options) > 0) {
            $tests = array_filter($tests, function ($test) {
                return in_array(str_replace("Test.php","",basename($test)), $this->options);
            });
        }


        foreach ($tests as $test) {

            Output::info("Run test file: " . $test);

            $this->runTest($test);
        }

        Output::success("All tests complete.");
    }

    private function runTest($test)
    {
        Output::info("Running test: " . $test);
       //调用php文件里面的test开头的方法
        require $test;
        $class = "tests\\" . basename($test, ".php");
        $class = new $class($this);
        $class->test();
    }
}