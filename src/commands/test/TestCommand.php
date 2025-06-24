<?php

namespace nova\commands\test;

use nova\commands\BaseCommand;

class TestCommand extends BaseCommand
{

    public function init()
    {


        $this->echoInfo("Running tests...");
        //查找tests文件夹里面所有以.test.php结尾的文件
        $dir = $this->workingDir . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR;
        $this->echoInfo("Found any Tests in: " . $dir);
        if (!is_dir($dir)) {
            $this->echoError("No tests found.");
            return;
        }


        $tests = glob($dir. "*Test.php");

        if (count($tests) === 0) {
            $this->echoError("No tests found.");
            return;
        }

        if (count($this->options) > 0) {
            $tests = array_filter($tests, function ($test) {
                return in_array(str_replace("Test.php","",basename($test)), $this->options);
            });
        }


        foreach ($tests as $test) {

            $this->echoInfo("Run test file: " . $test);

            $this->runTest($test);
        }

        $this->echoSuccess("All tests complete.");
    }

    private function runTest($test)
    {
        $this->echoInfo("Running test: " . $test);
       //调用php文件里面的test开头的方法
        require $test;
        $class = "tests\\" . basename($test, ".php");
        $class = new $class($this);
        $class->test();
    }
}