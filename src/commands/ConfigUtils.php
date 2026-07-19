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
     * 合并更新配置并立即保存。
     * 关联数组：深合并（新值覆盖同键）；列表：追加去重；标量：覆盖。
     */
    public function merge(array $newConfig): void
    {
        $this->config = $this->mergeRecursive($this->config, $newConfig);
        $this->save();
    }

    private function mergeRecursive(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }

            if (is_array($value) && is_array($base[$key])) {
                if (array_is_list($base[$key]) && array_is_list($value)) {
                    $base[$key] = array_values(array_unique(array_merge($base[$key], $value), SORT_REGULAR));
                } else {
                    $base[$key] = $this->mergeRecursive($base[$key], $value);
                }
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * 递归删除指定的配置项并立即保存。
     * 关联数组：按键删除；列表：按值删除（与 merge 的追加语义对称）。
     */
    public function remove_keys(array $removeConfig): void
    {
        $this->config = $this->removeKeysRecursive($this->config, $removeConfig);
        $this->save();
    }

    private function removeKeysRecursive(array $target, array $remove): array
    {
        if (array_is_list($target) && array_is_list($remove)) {
            $target = array_values(array_filter(
                $target,
                static fn ($item) => !in_array($item, $remove, true)
            ));
            return $target;
        }

        foreach ($remove as $key => $value) {
            if (!array_key_exists($key, $target)) {
                continue;
            }

            if (is_array($value) && is_array($target[$key])) {
                if ($value === []) {
                    unset($target[$key]);
                    continue;
                }

                $target[$key] = $this->removeKeysRecursive($target[$key], $value);
                if ($target[$key] === []) {
                    unset($target[$key]);
                }
                continue;
            }

            if ($value === true || $value === $target[$key]) {
                unset($target[$key]);
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