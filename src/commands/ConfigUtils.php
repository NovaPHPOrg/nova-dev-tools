<?php

namespace nova\commands;

class ConfigUtils
{
    private array $config = [];
    private string $file;

    public function __construct()
    {
        $this->file = "./src/config.php";
        $this->config = include $this->file;
    }

    public function __destruct()
    {
        $content = "<?php\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($this->file, $content);
    }

    /**
     * 判断键名是否需要作为单个键处理
     */
    private function isLiteralKey(string $key): bool
    {
        return isset($this->config[$key]) || str_contains($key, '.');
    }

    /**
     * 设置配置项
     * @param string $key 键名
     * @param mixed $value 值
     */
    public function set(string $key, $value): void
    {
        if ($this->isLiteralKey($key)) {
            $this->config[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        $current = $value;
    }

    /**
     * 获取配置项
     * @param string|null $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        if ($this->isLiteralKey($key)) {
            return $this->config[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $current = $this->config;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return $default;
            }
            $current = $current[$k];
        }
        return $current;
    }

    /**
     * 删除配置项
     * @param string $key 键名
     */
    public function delete(string $key): void
    {
        if ($this->isLiteralKey($key)) {
            unset($this->config[$key]);
            return;
        }

        $keys = explode('.', $key);
        $current = &$this->config;
        $lastKey = array_pop($keys);

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return;
            }
            $current = &$current[$k];
        }

        unset($current[$lastKey]);
    }

    /**
     * 向数组配置项添加元素
     * @param string $key 键名
     * @param mixed $value 要添加的值
     */
    public function push(string $key, $value): void
    {
        $current = $this->get($key);
        if (!is_array($current)) {
            $current = [];
        }
        $current[] = $value;
        $this->set($key, $current);
    }

    /**
     * 从数组配置项移除元素
     * @param string $key 键名
     * @param mixed $value 要移除的值
     */
    public function remove(string $key, $value): void
    {
        $current = $this->get($key);
        if (!is_array($current)) {
            return;
        }
        
        $current = array_filter($current, function($item) use ($value) {
            return $item !== $value;
        });
        $this->set($key, array_values($current));
    }

    /**
     * 检查配置项是否存在
     * @param string $key 键名
     * @return bool
     */
    public function has(string $key): bool
    {
        if ($this->isLiteralKey($key)) {
            return isset($this->config[$key]);
        }

        $keys = explode('.', $key);
        $current = $this->config;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return false;
            }
            $current = $current[$k];
        }
        return true;
    }

    /**
     * 递归合并配置数组
     * @param array $target 目标数组
     * @param array $source ���数组
     * @return array
     */
    private function mergeArrays(array $target, array $source): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value) && isset($target[$key]) && is_array($target[$key])) {
                $target[$key] = $this->mergeArrays($target[$key], $value);
            } else {
                $target[$key] = $value;
            }
        }
        return $target;
    }

    /**
     * 合并更新配置
     * @param array $newConfig 新的配置数组
     * @param bool $recursive 是否递归合并，默认为true
     * @return void
     */
    public function merge(array $newConfig, bool $recursive = true): void
    {
        if ($recursive) {
            $this->config = $this->mergeArrays($this->config, $newConfig);
        } else {
            $this->config = array_merge($this->config, $newConfig);
        }
    }

    /**
     * 从文件更新配置
     * @param string $file 配置文件路径
     * @param bool $recursive 是否递归合并，默认为true
     * @return bool
     */
    public function mergeFromFile(string $file, bool $recursive = true): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        $newConfig = include $file;
        if (!is_array($newConfig)) {
            return false;
        }

        $this->merge($newConfig, $recursive);
        return true;
    }

    /**
     * 递归删除指定的配置项
     * @param array $removeConfig 要删除的配置结构
     * @return void
     */
    public function remove_keys(array $removeConfig): void
    {
        $this->config = $this->removeKeysRecursive($this->config, $removeConfig);
    }

    /**
     * 递归处理删除操作
     * @param array $target 目标数组
     * @param array $remove 要删除的结构
     * @return array
     */
    private function removeKeysRecursive(array $target, array $remove): array
    {
        foreach ($remove as $key => $value) {
            if (isset($target[$key])) {
                if (is_array($value) && is_array($target[$key])) {
                    // 如果值是空数组，删除整个键
                    if (empty($value)) {
                        unset($target[$key]);
                    } else {
                        // 递归删除子元素
                        $target[$key] = $this->removeKeysRecursive($target[$key], $value);
                        // 如果删除后数组为空，则删除该键
                        if (empty($target[$key])) {
                            unset($target[$key]);
                        }
                    }
                } else {
                    // 如果值相同或值为true，删除该键
                    if ($value === true || $value === $target[$key]) {
                        unset($target[$key]);
                    }
                }
            }
        }
        return $target;
    }
}