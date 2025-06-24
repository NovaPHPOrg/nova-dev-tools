<?php

namespace nova\commands\init;

/**
 * Nova项目配置类
 * 基于Composer配置的抽象类，用于管理项目配置信息
 */
class NovaProject
{
    /**
     * 项目名称
     */
    public string $name = "nova-project";
    
    /**
     * 项目描述
     */
    public string $description = "A Nova project";
    
    /**
     * 项目版本
     */
    public string $version = "1.0.0";
    
    /**
     * 作者信息
     */
    public string $author = "Nova Team <ankio@ankio.net>";
    
    /**
     * 许可证
     */
    public string $license = "MIT";
    
    /**
     * 项目类型 (project/library)
     */
    public string $type = "project";
    
    /**
     * 关键词
     */
    public array $keywords = ["nova", "php", "framework"];
    
    /**
     * 项目主页
     */
    public string $homepage = "";
    
    /**
     * 支持信息
     */
    public array $support = [
        "issues" => "",
        "source" => "",
        "docs" => ""
    ];


    public array $config = [
        "vendor-dir" => "/src/vendor",
    ];
    
    /**
     * 最低稳定性
     */
    public string $minimumStability = "stable";
    
    /**
     * 偏好稳定版本
     */
    public bool $preferStable = true;
    
    /**
     * 依赖包
     */
    public array $require = [
        "php" => ">=8.0"
    ];
    
    /**
     * 开发依赖
     */
    public array $requireDev = [];
    
    /**
     * 自动加载配置
     */
    public array $autoload = [
        "psr-4" => [
            "app\\" => "src/app"
        ],
        "psr-0" => [],
        "classmap" => [],
        "files" => []
    ];
    
    /**
     * 开发环境自动加载
     */
    public array $autoloadDev = [
        "psr-4" => [],
        "psr-0" => [],
        "classmap" => [],
        "files" => []
    ];
 
    
    /**
     * 源代码目录
     */
    public string $source = "/src";
    
    /**
     * 获取Composer配置数组
     */
    public function toComposerArray(): array
    {
        $composer = [
            "name" => $this->getComposerName(),
            "description" => $this->description,
            "version" => $this->version,
            "type" => $this->type,
            "keywords" => $this->keywords,
            "license" => $this->license,
            "authors" => $this->parseAuthors(),
            "require" => $this->require,
            "require-dev" => $this->requireDev,
            "autoload" => $this->autoload,
            "autoload-dev" => $this->autoloadDev,
            "config" => $this->config,
            "minimum-stability" => $this->minimumStability,
            "prefer-stable" => $this->preferStable
        ];
        
        // 添加可选字段
        if (!empty($this->homepage)) {
            $composer["homepage"] = $this->homepage;
        }
        
        if (!empty($this->support)) {
            $composer["support"] = $this->support;
        }
        
        return $composer;
    }
    
    /**
     * 获取Composer包名
     */
    protected function getComposerName(): string
    {
        return "app/" . $this->name;
    }
    
    /**
     * 解析作者信息
     */
    protected function parseAuthors(): array
    {
        // 简单的作者解析，支持 "Name <email>" 格式
        if (preg_match('/^(.+?)\s*<(.+?)>$/', $this->author, $matches)) {
            return [
                [
                    "name" => trim($matches[1]),
                    "email" => trim($matches[2])
                ]
            ];
        }
        
        return [
            [
                "name" => $this->author
            ]
        ];
    }
}