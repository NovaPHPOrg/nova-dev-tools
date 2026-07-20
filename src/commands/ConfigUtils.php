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
     *
     * - 数字键数组（含空洞）：按值追加去重，再重排索引
     * - 关联数组：递归合并，只补缺失键，不覆盖已有标量
     */
    public function merge(array $newConfig): void
    {
        $this->config = $this->mergeRecursive($this->config, $newConfig);
        $this->save();
    }

    private function mergeRecursive(array $base, array $over): array
    {
        if ($this->isListLike($base) && $this->isListLike($over)) {
            return array_values(array_unique(array_merge($base, $over), SORT_REGULAR));
        }

        foreach ($over as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }

            if (is_array($value) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
                continue;
            }
            // 已有标量：保留用户值
        }

        return $base;
    }

    /**
     * 数字键数组（含空洞）视为列表。framework_start 被 unset 后常见非连续键，
     * array_is_list 会误判成关联数组，导致按索引 0 覆盖而不是追加。
     */
    private function isListLike(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        foreach ($arr as $key => $_) {
            if (!is_int($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 递归删除指定的配置项并立即保存。
     * 列表按值删并重排索引；关联数组按键删。
     */
    public function remove_keys(array $removeConfig): void
    {
        $this->config = $this->removeKeysRecursive($this->config, $removeConfig);
        $this->save();
    }

    private function removeKeysRecursive(array $target, array $remove): array
    {
        if ($this->isListLike($target) && $this->isListLike($remove)) {
            return array_values(array_filter(
                $target,
                static fn ($item) => !in_array($item, $remove, true)
            ));
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