<?php

namespace nova\commands\test;

use AssertionError;



class TestCase
{

    private $baseCommand;
public function __construct($baseCommand)
    {
       $this->baseCommand = $baseCommand;
       $workingDir = $baseCommand->workingDir;
        $GLOBALS['__nova_app_start__'] = microtime(true);
        $GLOBALS['__nova_app_config__'] =  include_once "$workingDir/src/config.php";
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



        include_once "$workingDir/src/nova/framework/constants.php";
    }
    function checkObj($obj1,$obj2)
    {
        foreach (get_object_vars($obj1) as $key => $value) {
            try {
                assert($obj1->$key == $obj2->$key);
                $this->baseCommand->echoSuccess("obj1->$key: ".print_r($value,true)." == obj2->$key: " . print_r($obj2->$key ,true));
            }catch (AssertionError $e){
               $this->baseCommand->echoError("obj1->$key: ".print_r($value,true)." != obj2->$key: " . print_r($obj2->$key ,true));
            }
        }
    }

    function checkArray($arr1,$arr2)
    {
        foreach ($arr1 as $key => $value) {
            try {
                assert($value == $arr2[$key]);
                $this->baseCommand->echoSuccess("arr1[$key]: ".print_r($value,true)." == arr2[$key]: " . print_r($arr2[$key] ,true));
            }catch (AssertionError $e){
               $this->baseCommand->echoError("arr1[$key]: ".print_r($value,true)." != arr2[$key]: " . print_r($arr2[$key] ,true));
            }
        }
    }

    function checkString($str1,$str2)
    {
        try {
            assert(gettype($str1) == "string");
            assert($str1 == $str2);
            $this->baseCommand->echoSuccess("str1: ".print_r($str1,true)." == str2: " . print_r($str2 ,true));
        }catch (AssertionError $e){
           $this->baseCommand->echoError("str1: ".print_r($str1,true)." != str2: " . print_r($str2 ,true));
        }
    }

    function checkInt($int1,$int2)
    {
        try {
            assert(gettype($int1) == "integer");
            assert($int1 == $int2);
            $this->baseCommand->echoSuccess("int1: ".print_r($int1,true)." == int2: " . print_r($int2 ,true));
        }catch (AssertionError $e){
           $this->baseCommand->echoError("int1: ".print_r($int1,true)." != int2: " . print_r($int2 ,true));
        }
    }

    function checkFloat($float1,$float2)
    {
        try {
            assert(gettype($float1) == "double");
            assert($float1 == $float2);
            $this->baseCommand->echoSuccess("float1: ".print_r($float1,true)." == float2: " . print_r($float2 ,true));
        }catch (AssertionError $e){
           $this->baseCommand->echoError("float1: ".print_r($float1,true)." != float2: " . print_r($float2 ,true));
        }
    }

    function checkBool($bool1,$bool2)
    {
        try {
            assert(gettype($bool1) == "boolean");
            assert($bool1 == $bool2);
            $this->baseCommand->echoSuccess("bool1: ".print_r($bool1,true)." == bool2: " . print_r($bool2 ,true));
        }catch (AssertionError $e){
           $this->baseCommand->echoError("bool1: ".print_r($bool1,true)." != bool2: " . print_r($bool2 ,true));
        }
    }

    function checkNull($null1)
    {
        try {
            assert($null1 == null );
               $this->baseCommand->echoSuccess("null1: ".print_r($null1,true)." == null ");
        }catch (AssertionError $e){
           $this->baseCommand->echoError("null1: ".print_r($null1,true)." != null" );
        }
    }


}