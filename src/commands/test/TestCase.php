<?php

namespace nova\commands\test;

use nova\console\Output;

abstract class TestCase
{
    abstract public function test();

    private $baseCommand;
    private int $totalChecks = 0;
    private int $passedChecks = 0;
    private int $failedChecks = 0;
    private float $floatEpsilon = 1e-9;

    public function __construct($baseCommand)
    {
        $this->baseCommand = $baseCommand;
        $workingDir = $baseCommand->workingDir;
        $GLOBALS['__nova_app_start__'] = microtime(true);
        $GLOBALS['__nova_app_config__'] = include_once "$workingDir/src/config.php";
        $GLOBALS['__nova_session_id__'] = uniqid('session_', true);

        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '';
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = "$workingDir/src/index.php";
        $_SERVER['PHP_SELF'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $_SERVER['HTTP_COOKIE'] = '';
        $_SERVER['HTTP_REFERER'] = '';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = '';
        $_SERVER['HTTP_ACCEPT'] = '';
        $_SERVER['HTTP_CONNECTION'] = '';


        if(file_exists($workingDir . '/src/vendor/autoload.php')){
            require $workingDir . '/src/vendor/autoload.php';
        }

        include_once "$workingDir/src/nova/framework/core/Loader.php";

        global $loader;

        $loader = (new \ReflectionClass("nova\\framework\\core\\Loader"))->newInstance();

        global $context;

        $context = (new \ReflectionClass("nova\\framework\\core\\Context"))->newInstance($loader);

        include_once "$workingDir/src/nova/framework/helper.php";

        $context->init();

        $this->initEvent();
    }

    function initEvent()
    {
        $ref = new \ReflectionClass('nova\framework\event\EventManager');
        $ref->getMethod('register')->invoke(null);
        // EventManager::trigger("framework.start", $this);
        $ref->getMethod('trigger')->invoke(null, "framework.start");
    }

    private function stringifyValue($value): string
    {
        return var_export($value, true);
    }

    private function recordCheck(bool $passed, string $successMessage, string $failureMessage): bool
    {
        $this->totalChecks++;

        if ($passed) {
            $this->passedChecks++;
            Output::success($successMessage);
            return true;
        }

        $this->failedChecks++;
        Output::error($failureMessage);
        return false;
    }

    private function checkMap(array $actual, array $expected, string $label): bool
    {
        $allPassed = true;

        foreach ($expected as $key => $expectedValue) {
            if (!array_key_exists($key, $actual)) {
                $allPassed = $this->recordCheck(
                    false,
                    "$label[$key] exists",
                    "$label[$key] missing, expected: " . $this->stringifyValue($expectedValue)
                ) && $allPassed;
                continue;
            }

            $actualValue = $actual[$key];
            $allPassed = $this->recordCheck(
                $actualValue === $expectedValue,
                "$label[$key]: " . $this->stringifyValue($actualValue) . " === " . $this->stringifyValue($expectedValue),
                "$label[$key]: " . $this->stringifyValue($actualValue) . " !== " . $this->stringifyValue($expectedValue)
            ) && $allPassed;
        }

        foreach ($actual as $key => $actualValue) {
            if (array_key_exists($key, $expected)) {
                continue;
            }

            $allPassed = $this->recordCheck(
                false,
                "$label[$key] expected",
                "$label[$key] is unexpected, actual: " . $this->stringifyValue($actualValue)
            ) && $allPassed;
        }

        return $allPassed;
    }

    public function checkObj($obj1, $obj2): bool
    {
        if (!is_object($obj1) || !is_object($obj2)) {
            return $this->recordCheck(
                false,
                'Object type check passed',
                'checkObj expects two objects, actual types: ' . gettype($obj1) . ' and ' . gettype($obj2)
            );
        }

        return $this->checkMap(get_object_vars($obj1), get_object_vars($obj2), 'obj');
    }

    public function checkArray($arr1, $arr2): bool
    {
        if (!is_array($arr1) || !is_array($arr2)) {
            return $this->recordCheck(
                false,
                'Array type check passed',
                'checkArray expects two arrays, actual types: ' . gettype($arr1) . ' and ' . gettype($arr2)
            );
        }

        return $this->checkMap($arr1, $arr2, 'arr');
    }

    public function checkString($str1, $str2): bool
    {
        if (!is_string($str1) || !is_string($str2)) {
            return $this->recordCheck(
                false,
                'String type check passed',
                'checkString expects two strings, actual types: ' . gettype($str1) . ' and ' . gettype($str2)
            );
        }

        return $this->recordCheck(
            $str1 === $str2,
            'str1: ' . $this->stringifyValue($str1) . ' === str2: ' . $this->stringifyValue($str2),
            'str1: ' . $this->stringifyValue($str1) . ' !== str2: ' . $this->stringifyValue($str2)
        );
    }

    public function checkInt($int1, $int2): bool
    {
        if (!is_int($int1) || !is_int($int2)) {
            return $this->recordCheck(
                false,
                'Int type check passed',
                'checkInt expects two integers, actual types: ' . gettype($int1) . ' and ' . gettype($int2)
            );
        }

        return $this->recordCheck(
            $int1 === $int2,
            'int1: ' . $this->stringifyValue($int1) . ' === int2: ' . $this->stringifyValue($int2),
            'int1: ' . $this->stringifyValue($int1) . ' !== int2: ' . $this->stringifyValue($int2)
        );
    }

    public function checkFloat($float1, $float2): bool
    {
        if (!is_float($float1) || !is_float($float2)) {
            return $this->recordCheck(
                false,
                'Float type check passed',
                'checkFloat expects two floats, actual types: ' . gettype($float1) . ' and ' . gettype($float2)
            );
        }

        $delta = abs($float1 - $float2);
        return $this->recordCheck(
            $delta <= $this->floatEpsilon,
            'float delta ' . $this->stringifyValue($delta) . ' <= ' . $this->stringifyValue($this->floatEpsilon),
            'float1: ' . $this->stringifyValue($float1) . ' is not close to float2: ' . $this->stringifyValue($float2) . ', delta: ' . $this->stringifyValue($delta)
        );
    }

    public function checkBool($bool1, $bool2): bool
    {
        if (!is_bool($bool1) || !is_bool($bool2)) {
            return $this->recordCheck(
                false,
                'Bool type check passed',
                'checkBool expects two booleans, actual types: ' . gettype($bool1) . ' and ' . gettype($bool2)
            );
        }

        return $this->recordCheck(
            $bool1 === $bool2,
            'bool1: ' . $this->stringifyValue($bool1) . ' === bool2: ' . $this->stringifyValue($bool2),
            'bool1: ' . $this->stringifyValue($bool1) . ' !== bool2: ' . $this->stringifyValue($bool2)
        );
    }

    public function checkNull($null1): bool
    {
        return $this->recordCheck(
            $null1 === null,
            'value is null',
            'value is not null, actual: ' . $this->stringifyValue($null1)
        );
    }

    public function hasFailures(): bool
    {
        return $this->failedChecks > 0;
    }

    /**
     * @return array{total:int, passed:int, failed:int}
     */
    public function getStats(): array
    {
        return [
            'total' => $this->totalChecks,
            'passed' => $this->passedChecks,
            'failed' => $this->failedChecks,
        ];
    }

}