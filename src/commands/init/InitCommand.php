<?php

namespace nova\commands\init;

use nova\commands\BaseCommand;
use nova\commands\plugin\PluginManager;
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

        $dirs = [
            "src",
            "src/app", //应用程序目录
            "src/public", //公共目录
            "src/nova",//nova运行目录
            //   "src/nova/framework",//nova框架目录
            "src/nova/plugin",//nova插件目录
            "src/runtime",//nova运行时目录
            "tests",//测试目录
        ];
        foreach ($dirs as $dir) {
            $dir = $this->getDir($dir);
            mkdir($this->workingDir . DIRECTORY_SEPARATOR . $dir, 0777, true);
            // 创建.gitkeep
            file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . ".gitkeep", "");
        }

        $this->initFramework();
        $this->echoSuccess("项目 {$this->nova->name} 初始化成功。");
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
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "README.md", $readme);
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
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . "composer.json", $composer);
    }

    private function initIgnore()
    {
        $ignore = <<<EOF
/vendor
composer.lock
/src/runtime
/src/nova
EOF;
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . ".gitignore", $ignore);
        shell_exec("git add . && git commit -m ':tada:  init {$this->nova->name}'");
    }

    private function initConfig()
    {
        $config = <<<EOF
<?php
return [
    'debug'=>true,//当前是否为调试模式
    'timezone'=>'Asia/Shanghai',//时区
    'default_route'=>true,//启用默认路由，nova默认根据url自动解析到AnyModule/AnyController/AnyMethod方法，如果设置为false，则需要手动配置路由
    'cache_driver' => 'nova\framework\cache\ApcuCacheDriver',//如果apcu不可用，则默认使用文件缓存
    'render_engine' => 'nova\framework\render\SmartyRender',//默认使用smarty模板引擎
    'domain'=>[
        '0.0.0.0',//允许访问的域名
    ],
    'version'=>'',//版本号
    'versionCode'=>1,//版本号
    'db'=>[
        'type'=>'mysql',
        'host'=>'localhost',
        'port'=>3306,
        'username'=>'root',
        'password'=>'root',
        'db'=>'test',
        'charset'=>'utf8mb4',
    ]
];
EOF;
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . $this->getDir("src/config.php"), $config);
    }

    private function initPublic(){
        $index = <<<EOF
<?php
namespace app;
if(file_exists(__DIR__ . '/../vendor/autoload.php')){
    require __DIR__ . '/../vendor/autoload.php';
}
include __DIR__ . '/../nova/framework/bootstrap.php';
EOF;
        file_put_contents($this->workingDir . DIRECTORY_SEPARATOR . $this->getDir("src/public/index.php"), $index);

    }
    private function initFrameworkPHP(){
        $plugin = new PluginManager($this);
        $plugin->addSubmodule("https://github.com/NovaPHPOrg/nova-framework","./src/nova/framework");
    }
    private function initFramework()
    {
        $this->initReadme();
        $this->initComposer();
        $this->initPublic();
        $this->initConfig();
        $this->initFrameworkPHP();
        $this->initIgnore();
    }

    private function getProjectName(): string
    {
        $projectName = $this->prompt("项目名称: ");
        $regex = "/^[a-z0-9_\-]+$/";
        if (!preg_match($regex, $projectName)) {
            $this->echoError("项目名只能包含小写字母、数字、下划线和破折号。");
            $projectName =  $this->getProjectName();
        }
        return $projectName;
    }


}