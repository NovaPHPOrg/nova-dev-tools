<?php

namespace nova\commands\init;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\commands\plugin\PluginManager;
use nova\commands\ui\UiCommand;
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
        $novaUI = $this->prompt("使用NovaUI框架(y/n): ","n");
        $composer = $this->prompt("使用Composer(y/n): ","n");
        $this->nova->require = ["php"=>">=".SUPPORTED_PHP_VERSION ];
        // 创建项目目录
        // 初始化git
        shell_exec("git init");
        // 创建nova.json
        $json = $this->nova->toComposerArray();
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "package.json", json_encode($json,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ));
        if($composer == "y"){
            file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "composer.json", json_encode($json,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            shell_exec("composer install");
        }
        $this->initFramework();

        if($novaUI == "y"){
            $uiCommand = new UiCommand($this->workingDir,["init"]);
            $uiCommand->init();
        }

        shell_exec("git add -A ");
        shell_exec("git commit -m \":tada: project init\"");

        $this->echoSuccess("项目 {$this->nova->name} 初始化成功。");
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
        $this->initFrameworkPHP();
    }


    private function getProjectName(): string
    {
        //获取当前文件夹名称
        $name  = basename(getcwd());
        $projectName = $this->prompt("项目名称: ",$name);
        $regex = "/^[a-z0-9_\-]+$/";
        if (!preg_match($regex, $projectName)) {
            $this->echoError("项目名只能包含小写字母、数字、下划线和破折号。");
            $projectName =  $this->getProjectName();
        }
        return $projectName;
    }


}