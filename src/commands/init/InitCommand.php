<?php

namespace nova\commands\init;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\commands\plugin\PluginManager;
use nova\commands\ui\UiCommand;
use nova\console\Output;
use const nova\SUPPORTED_PHP_VERSION;

class InitCommand extends BaseCommand
{
    private NovaProject $nova;
    public function __construct($workingDir, $options)
    {
        parent::__construct($workingDir, $options);
        $this->nova = new NovaProject();
    }

    public function init(): void
    {
        Output::section("Create Nova Project");
        $this->nova->name        = $this->getProjectName();
        $this->nova->description = $this->prompt("Project description", $this->nova->description);
        $this->nova->author      = $this->prompt("Author", $this->nova->author);
        $this->nova->license     = $this->prompt("License", $this->nova->license);
        $novaUI   = $this->prompt("Use NovaUI framework? (y/n)", "n");
        $composer = $this->prompt("Use Composer? (y/n)", "n");
        $this->nova->require = ["php" => ">=" . SUPPORTED_PHP_VERSION];

        Output::section("Setting up project");
        $this->exec("git init");

        $json = $this->nova->toComposerArray();
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "package.json",
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($composer === "y") {
            file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "composer.json",
                json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->exec("composer install");
        }

        $this->initFramework();

        if ($novaUI === "y") {
            $uiCommand = new UiCommand($this->workingDir, ["init"]);
            $uiCommand->init();
        }

        $this->initServeModule();

        Output::section("Committing");
        $this->exec("git add -A");
        $this->exec("git commit -m \":tada: project init\"");

        Output::writeln();
        Output::success("Project {$this->nova->name} initialized successfully 🎉");
        Output::writeln();
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

    private function initServeModule(): void
    {
        $git = new GitCommand($this);
        $git->addSubmodule("https://github.com/NovaPHPOrg/nova-workerman","./src/nova/workerman");
    }
    private function initFramework(): void
    {
        $templateDir = $this->resolveTemplateDir('init/project');
        if ($templateDir === null) {
            Output::error("项目模板目录不存在：init/project");
            return;
        }

        if (!$this->copyDir($templateDir, $this->workingDir)) {
            Output::error("初始化项目模板失败。");
            return;
        }

        $this->initReadme();
        $this->initFrameworkPHP();
    }


    private function getProjectName(): string
    {
        //获取当前文件夹名称
        $name  = basename(getcwd());
        $projectName = $this->prompt("Project name: ",$name);
        $regex = "/^[a-z0-9_\-]+$/";
        if (!preg_match($regex, $projectName)) {
            $this->echoError("Project name can only contain lowercase letters, numbers, underscores, and dashes.");
            $projectName =  $this->getProjectName();
        }
        return $projectName;
    }


}