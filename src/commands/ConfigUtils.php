<?php

namespace nova\commands;

class ConfigUtils
{
    private array $config;
    private string $file;

    /**
     * @param string $file 主配置文件路径
     */
    public function __construct(string $file = "./src/config.php")
    {
        $this->file = $file;
        if (file_exists($file)) {
            $this->config = include $this->file;
        } else {
            $this->config = [];
        }
    }

    /**
     * 显式保存配置到文件。
     */
    public function save(): void
    {
        $content = "<?php\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($this->file, $content);
    }

    /**
     * 合并更新配置并立即保存
     */
    public function merge(array $newConfig): void
    {
        $this->config = array_replace_recursive($this->config, $newConfig);
        $this->save();
    }

    /**
     * 递归删除指定的配置项并立即保存
     */
    public function remove_keys(array $removeConfig): void
    {
        $this->config = $this->removeKeysRecursive($this->config, $removeConfig);
        $this->save();
    }

    private function removeKeysRecursive(array $target, array $remove): array
    {
        foreach ($remove as $key => $value) {
            if (isset($target[$key])) {
                if (is_array($value) && is_array($target[$key])) {
                    if (empty($value)) {
                        unset($target[$key]);
                    } else {
                        $target[$key] = $this->removeKeysRecursive($target[$key], $value);
                        if (empty($target[$key])) {
                            unset($target[$key]);
                        }
                    }
                } else {
                    if ($value === true || $value === $target[$key]) {
                        unset($target[$key]);
                    }
                }
            }
        }
        return $target;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
        $this->save();
    }
}