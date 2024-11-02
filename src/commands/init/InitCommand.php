<?php

namespace nova\commands\init;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\commands\plugin\PluginManager;
use Phar;
use const nova\SUPPORTED_PHP_VERSION;

class InitCommand extends BaseCommand
{
    private NovaProject $nova;
    public function __construct($workingDir, $options)
    {
        parent::__construct($workingDir, $options);
        $this->nova = new NovaProject();
    }

    public function init()
    {
        $this->echoInfo("init project...");
        $this->nova->name = $this->getProjectName();
        $this->nova->description = $this->prompt("请输入项目描述: ",$this->nova->description);
        $this->nova->author = $this->prompt("请输入作者: ", $this->nova->author);
        $this->nova->license = $this->prompt("请输入许可证: ",$this->nova->license);
        // 创建项目目录
        // 初始化git
        shell_exec("git init");
        // 创建nova.json
        $json = json_encode($this->nova, JSON_PRETTY_PRINT);
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "package.json", $json);

        $this->initFramework();
        $this->echoSuccess("项目 {$this->nova->name} 初始化成功。");
    }
    private function getDir($dir): string
    {
        return str_replace("/", DIRECTORY_SEPARATOR, $dir);
    }
    private function initReadme(): void
    {
        // 创建README.md
        $readme = <<<EOF
# {$this->nova->name}
{$this->nova->description}

# License
{$this->nova->license}
EOF;
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "README.md", $readme);
    }
    private function initComposer(): void
    {
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
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "composer.json", $composer);
    }

    private function initFrameworkPHP(): void
    {
        $git = new GitCommand($this);
        $git->addSubmodule("https://github.com/NovaPHPOrg/nova-framework","./src/nova/framework");
    }
    private function initFramework(): void
    {
        if (Phar::running()) {
            // 如果在 .phar 中运行，使用 phar:// 协议进行访问
            $sourceFile =  Phar::running() . DIRECTORY_SEPARATOR ;
        } else {
            // 如果未打包成 .phar，则直接使用文件系统路径
            $sourceFile = '';
        }
        $this->copyDir($sourceFile.$this->getDir("../../init/project"),$this->workingDir);
        $this->initReadme();
        $this->initComposer();
        $this->initFrameworkPHP();
    }


    private function getProjectName(): string
    {
        //获取当前文件夹名称
        $name  = basename(__DIR__);
        $projectName = $this->prompt("项目名称: ",$name);
        $regex = "/^[a-z0-9_\-]+$/";
        if (!preg_match($regex, $projectName)) {
            $this->echoError("项目名只能包含小写字母、数字、下划线和破折号。");
            $projectName =  $this->getProjectName();
        }
        return $projectName;
    }


}