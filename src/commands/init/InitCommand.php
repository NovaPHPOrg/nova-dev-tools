<?php

namespace nova\commands\init;

use nova\commands\BaseCommand;

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
        $this->echoInfo("初始化项目...");
        $this->nova->name = $this->getProjectName();
        $this->nova->description = $this->prompt("请输入项目描述: ",$this->nova->description);
        $this->nova->version = $this->prompt("请输入版本号: ",$this->nova->version);
        $this->nova->author = $this->prompt("请输入作者: ", $this->nova->author);
        $this->nova->license = $this->prompt("请输入许可证: ",$this->nova->license);
        // 创建项目目录
        $projectDir = $this->workingDir . DIRECTORY_SEPARATOR . $this->nova->name;
        $this->echoInfo("创建项目目录 {$projectDir}...");
        if (!file_exists($projectDir)) {
            mkdir($projectDir);
            // 初始化git
            shell_exec("cd {$projectDir} && git init");
            // 创建nova.json
            $json = json_encode($this->nova, JSON_PRETTY_PRINT);
            file_put_contents($projectDir . DIRECTORY_SEPARATOR . "nova.json", $json);
            mkdir($projectDir . DIRECTORY_SEPARATOR . "src");
            // src目录设置为源码目录
            // 创建README.md
            $readme = <<<EOF
# {$this->nova->name}
{$this->nova->description}

# License
{$this->nova->license}
EOF;
            file_put_contents($projectDir . DIRECTORY_SEPARATOR . "README.md", $readme);


        }else{
            $this->echoError("项目目录已存在。");
            exit();
        }
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

    public function __destruct()
    {
        $this->echoSuccess("项目 {$this->nova->name} 初始化成功。");
    }
}