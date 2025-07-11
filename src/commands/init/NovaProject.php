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



    public array $config = [
        "vendor-dir" => "/src/vendor",
    ];
    

    /**
     * 依赖包
     */
    public array $require = [];

    /**
     * 自动加载配置
     */
    public array $autoload = [
        "psr-4" => [
            'app\\' => "src/app"
        ],
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
            "license" => $this->license,
            "authors" => $this->parseAuthors(),
            "require" => $this->require,
            "autoload" => $this->autoload,
            "config" => $this->config,
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
        return  $this->name;
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