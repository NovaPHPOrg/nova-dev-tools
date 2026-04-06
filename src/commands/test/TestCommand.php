<?php

namespace nova\commands\test;

use nova\commands\BaseCommand;
use nova\console\Output;

/**
 * 测试命令。
 *
 * 负责在项目 `tests` 目录中查找 `*Test.php` 文件，
 * 可按命令选项过滤指定测试，并按顺序逐个执行。
 */
class TestCommand extends BaseCommand
{
    /**
     * 输出 test 子命令帮助信息。
     */
    private function help(): void
    {
        Output::usage("nova test <command> [options]");
        Output::section("Commands");
        Output::commandRow("list", "List all discovered tests");
        Output::commandRow("all", "Run all discovered tests");
        Output::commandRow("run <name>", "Run one or more tests by name");
        Output::writeln();
    }

    /**
     * 初始化并执行测试流程。
     *
     * 支持的子命令：
     * 1. `list`：列出所有可发现的测试；
     * 2. `all`：执行全部测试；
     * 3. `run`：执行指定名称的测试。
     */
    public function init(): void
    {
        if (count($this->options) < 1) {
            $this->help();
            return;
        }

        $subCommand = strtolower((string) array_shift($this->options));

        switch ($subCommand) {
            case 'list':
                $this->listTests();
                break;
            case 'all':
                $this->runAllTests();
                break;
            case 'run':
                $this->runNamedTests($this->options);
                break;
            default:
                Output::error("Unknown test subcommand: $subCommand");
                Output::writeln();
                $this->help();
                break;
        }
    }

    /**
     * 列出当前项目中可运行的测试。
     */
    private function listTests(): void
    {
        $tests = $this->discoverTests();

        if (count($tests) === 0) {
            Output::error("No tests found.");
            return;
        }

        Output::section("Tests");

        foreach ($tests as $name => $path) {
            Output::commandRow($name, str_replace($this->workingDir . DIRECTORY_SEPARATOR, '', $path), 20);
        }

        Output::writeln();
        Output::success("Found " . count($tests) . " test(s).");
    }

    /**
     * 执行当前项目中发现的全部测试。
     */
    private function runAllTests(): void
    {
        $tests = $this->discoverTests();

        if (count($tests) === 0) {
            Output::error("No tests found.");
            return;
        }

        Output::info("Running tests...");
        $this->runTests($tests);
    }

    /**
     * 按测试名称执行指定测试。
     *
     * @param array<int, string> $names 测试名称列表（不带 Test.php 后缀）
     */
    private function runNamedTests(array $names): void
    {
        if (count($names) === 0) {
            Output::error("Please specify at least one test name.");
            Output::writeln();
            $this->help();
            return;
        }

        $tests = $this->discoverTests();

        if (count($tests) === 0) {
            Output::error("No tests found.");
            return;
        }

        $selectedTests = [];
        $missingTests = [];

        foreach ($names as $name) {
            if (isset($tests[$name])) {
                $selectedTests[$name] = $tests[$name];
                continue;
            }

            $missingTests[] = $name;
        }

        if (count($missingTests) > 0) {
            Output::error('Tests not found: ' . implode(', ', $missingTests));
            return;
        }

        Output::info("Running tests...");
        $this->runTests($selectedTests);
    }

    /**
     * 发现并返回所有可运行测试。
     *
     * @return array<string, string> 键为测试名，值为测试文件完整路径
     */
    private function discoverTests(): array
    {
        // 测试文件统一放在项目根目录下的 tests 目录中。
        $dir = $this->workingDir . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR;
        Output::info("Found any Tests in: " . $dir);

        if (!is_dir($dir)) {
            return [];
        }

        // 约定测试文件命名为 *Test.php。
        $testFiles = glob($dir . '*Test.php') ?: [];
        $tests = [];

        foreach ($testFiles as $testFile) {
            $tests[basename($testFile, 'Test.php')] = $testFile;
        }

        if (count($tests) > 1) {
            uksort($tests, 'strnatcasecmp');
        }

        return $tests;
    }

    /**
     * 按给定映射顺序执行测试。
     *
     * @param array<string, string> $tests 键为测试名，值为测试文件完整路径
     */
    private function runTests(array $tests): void
    {
        $passed = 0;
        $failed = 0;

        foreach ($tests as $name => $test) {
            Output::info("Run test file: " . $name);
            if ($this->runTest($test)) {
                $passed++;
                continue;
            }

            $failed++;
        }

        if ($failed === 0) {
            Output::success("All tests complete. Passed: $passed, Failed: $failed.");
            return;
        }

        Output::error("Tests complete with failures. Passed: $passed, Failed: $failed.");
    }

    /**
     * 加载并执行单个测试文件。
     *
     * @param string $test 测试文件完整路径
     */
    private function runTest(string $test): bool
    {
        Output::info("Running test: " . $test);

        // 先加载测试文件，再按命名约定实例化 tests\{ClassName}。
        require_once $test;

        $className = "tests\\" . basename($test, ".php");

        if (!class_exists($className)) {
            Output::error("Test class not found: " . $className);
            return false;
        }

        $class = new $className($this);

        // 约定每个测试类都暴露统一的 test() 入口方法。
        if (!method_exists($class, 'test')) {
            Output::error("Test method not found: " . $className . "::test");
            return false;
        }

        try {
            $class->test();
        } catch (\Throwable $exception) {
            Output::error("Test execution failed: " . $exception->getMessage());
            return false;
        }

        if ($class instanceof TestCase) {
            $stats = $class->getStats();
            Output::info("Assertions: {$stats['total']}, Passed: {$stats['passed']}, Failed: {$stats['failed']}");

            if ($class->hasFailures()) {
                Output::error("Test failed: " . $className);
                return false;
            }
        }

        Output::success("Test passed: " . $className);
        return true;
    }
}