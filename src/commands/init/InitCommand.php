<?php

namespace nova\commands\init;

use nova\commands\BaseCommand;
use const nova\SUPPORTED_PHP_VERSION;

class InitCommand extends BaseCommand
{
    private NovaProject $nova;
    private string $projectDir;
    public function __construct($workingDir, $options)
    {
        parent::__construct($workingDir, $options);
        $this->nova = new NovaProject();
    }

    public function init()
    {
        $this->echoInfo("初始化项目...");
        $this->nova->name = $this->getProjectName();
        $this->nova->description = $this->prompt("请输入项目描述: ",$this->nova->description);
        $this->nova->version = $this->prompt("请输入版本号: ",$this->nova->version);
        $this->nova->author = $this->prompt("请输入作者: ", $this->nova->author);
        $this->nova->license = $this->prompt("请输入许可证: ",$this->nova->license);
        // 创建项目目录
        $this->projectDir = $this->workingDir . DIRECTORY_SEPARATOR . $this->nova->name;
        $this->echoInfo("创建项目目录 {$this->projectDir}...");
        if (!file_exists($this->projectDir)) {
            mkdir($this->projectDir);
            // 初始化git
            shell_exec("cd {$this->projectDir} && git init");
            // 创建nova.json
            $json = json_encode($this->nova, JSON_PRETTY_PRINT);
            file_put_contents($this->projectDir . DIRECTORY_SEPARATOR . "nova.json", $json);

            $dirs = [
                "src",
                "src/app", //应用程序目录
                "src/public", //公共目录
                "src/nova",//nova运行目录
                "src/nova/framework",//nova框架目录
                "src/nova/plugins",//nova插件目录
            ];
            foreach ($dirs as $dir) {
                $dir = $this->getDir($dir);
                mkdir($this->projectDir . DIRECTORY_SEPARATOR . $dir, 0777, true);
                // 创建.gitkeep
                file_put_contents($this->projectDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . ".gitkeep", "");
            }

            $this->initFramework();
            $this->echoSuccess("项目 {$this->nova->name} 初始化成功。");
        }else{
            $this->echoError("项目目录已存在。");
            exit();
        }
    }
    private function getDir($dir){
        return str_replace("/", DIRECTORY_SEPARATOR, $dir);
    }
    private function initReadme()
    {
        // 创建README.md
        $readme = <<<EOF
# {$this->nova->name}
{$this->nova->description}

# License
{$this->nova->license}
EOF;
        file_put_contents($this->projectDir . DIRECTORY_SEPARATOR . "README.md", $readme);
    }
    private function initComposer(){
        $composer = json_encode([
            "name" => "app/".$this->nova->name,
            "description" => $this->nova->description,
            "version" => $this->nova->version,
            "authors" => [
                $this->nova->author
            ],
            "license" => $this->nova->license,
            "require" => [
                "php" => ">=".SUPPORTED_PHP_VERSION
            ],
            "autoload" => [
                "psr-4" => [
                    "app\\" => "src/app"
                ]
            ]
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->projectDir . DIRECTORY_SEPARATOR . "composer.json", $composer);
    }

    private function initIgnore()
    {
        $ignore = <<<EOF
/vendor
composer.lock
EOF;
        file_put_contents($this->projectDir . DIRECTORY_SEPARATOR . ".gitignore", $ignore);
        shell_exec("cd {$this->projectDir} && git add . && git commit -m ':tada:  init {$this->nova->name}'");
    }

    private function initPublic(){
        $index = <<<EOF
<?php
require __DIR__ . '/../vendor/autoload.php';
//TODO 入口文件，App启动
EOF;
        file_put_contents($this->projectDir . DIRECTORY_SEPARATOR . $this->getDir("src/public/index.php"), $index);

    }
    private function initFramework()
    {
        $this->initReadme();
        $this->initComposer();
        $this->initPublic();
        //$this->initIgnore();
    }

    private function getProjectName(): string
    {
        $projectName = $this->prompt("请输入项目名: ");
        $regex = "/^[a-z0-9_\-]+$/";
        if (!preg_match($regex, $projectName)) {
            $this->echoError("项目名只能包含小写字母、数字、下划线和破折号。");
            $projectName =  $this->getProjectName();
        }
        return $projectName;
    }


}