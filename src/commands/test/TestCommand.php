        $this->echoInfo("Running test: " . $test);
        $this->echoSuccess("All tests complete.");
            $this->echoInfo("Run test file: " . $test);
            $this->echoError("No tests found.");
            $this->echoError("No tests found.");
use nova\console\Output;
        $this->echoInfo("Found any Tests in: " . $dir);
        $this->echoInfo("Running tests...");
<?php

namespace nova\commands\test;

use nova\commands\BaseCommand;
class TestCommand extends BaseCommand
        Output::info("Running tests...");

    public function init()
        Output::info("Found any Tests in: " . $dir);

            Output::error("No tests found.");
        $this->echoInfo("Running tests...");
        //查找tests文件夹里面所有以.test.php结尾的文件
        $dir = $this->workingDir . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR;
        $this->echoInfo("Found any Tests in: " . $dir);
        if (!is_dir($dir)) {
            $this->echoError("No tests found.");
            return;
            Output::error("No tests found.");


        $tests = glob($dir. "*Test.php");

        if (count($tests) === 0) {
            $this->echoError("No tests found.");
            return;
        }

        if (count($this->options) > 0) {
            $tests = array_filter($tests, function ($test) {
                return in_array(str_replace("Test.php","",basename($test)), $this->options);
            Output::info("Run test file: " . $test);
        }


        foreach ($tests as $test) {
        Output::success("All tests complete.");
            $this->echoInfo("Run test file: " . $test);

            $this->runTest($test);
        }
        Output::info("Running test: " . $test);
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