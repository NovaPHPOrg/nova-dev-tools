<?php

namespace nova\commands;

/**
 * 远程资源管理基类。
 *
 * 负责 GitHub 主机/API 地址、组织仓库列表拉取、
 * 代理读取、仓库地址拼接与通用名称处理。
 *
 * 子类只需关心组织名、仓库命名规则与业务流程。
 */
abstract class RemoteManager
{
    protected const GITHUB_BASE_URL = 'https://github.com';
    protected const GITHUB_API_BASE_URL = 'https://api.github.com';

    protected BaseCommand $baseCommand;
    protected GitCommand $command;

    /** @var array<string> 缓存的可安装名称列表 */
    protected array $data = [];

    public function __construct(BaseCommand $baseCommand)
    {
        $this->baseCommand = $baseCommand;
        $this->command = new GitCommand($baseCommand);
    }

    /**
     * 返回远程组织名称（如 NovaPHPOrg / NovaPHPOrgUI）。
     */
    abstract protected function getOrgName(): string;

    /**
     * 返回当前组织的 GitHub 仓库基础地址。
     */
    protected function getRepoBaseUrl(): string
    {
        return self::GITHUB_BASE_URL . '/' . $this->getOrgName();
    }

    /**
     * 根据仓库名构造完整 GitHub 仓库地址。
     */
    protected function buildRepoUrl(string $repoName): string
    {
        return $this->getRepoBaseUrl() . '/' . ltrim($repoName, '/');
    }

    /**
     * 构造当前组织的 GitHub 仓库列表 API 地址。
     */
    protected function buildOrgReposApiUrl(): string
    {
        return self::GITHUB_API_BASE_URL . '/orgs/' . $this->getOrgName() . '/repos?per_page=1000';
    }

    /**
     * 根据仓库名安装 Git 子模块。
     */
    protected function installRepoSubmodule(string $repoName, string $path): void
    {
        $this->command->addSubmodule($this->buildRepoUrl($repoName), $path);
    }

    /** 缓存有效期（秒）。 */
    protected const CACHE_TTL = 600;

    /**
     * 返回当前组织仓库列表的缓存文件路径。
     */
    private function repoCacheFile(): string
    {
        $key = md5($this->buildOrgReposApiUrl());
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . "nova_repo_list_{$key}.json";
    }

    /**
     * 拉取组织公开仓库列表（带文件缓存，TTL = CACHE_TTL 秒）。
     *
     * @return array<int,mixed>|null 失败返回 null，成功返回仓库数组
     */
    protected function listOrgRepos(): ?array
    {
        $cacheFile = $this->repoCacheFile();

        // 命中有效缓存直接返回
        if (
            file_exists($cacheFile) &&
            (time() - filemtime($cacheFile)) < self::CACHE_TTL
        ) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = $this->buildOrgReposApiUrl();
        $ch = curl_init($url);

        $headers = [
            'User-Agent: PHP',
            'Accept: application/vnd.github+json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        $proxy = $this->readHttpProxy();
        if ($proxy !== '') {
            $options[CURLOPT_PROXY] = $proxy;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            return null;
        }

        $data = json_decode($response, true);

        // 写入缓存（仅缓存有效数组）
        if (is_array($data)) {
            file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }

    /**
     * 从环境变量读取 HTTP/HTTPS 代理地址。
     *
     * 优先级：NOVA_HTTP_PROXY > HTTPS_PROXY > HTTP_PROXY > https_proxy > http_proxy
     */
    protected function readHttpProxy(): string
    {
        $keys = [
            'NOVA_HTTP_PROXY',
            'HTTPS_PROXY',
            'HTTP_PROXY',
            'https_proxy',
            'http_proxy',
        ];

        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * 将形如 "xxx-yyy" 的名称归一化为 "xxx" 作为目录名。
     */
    protected function getSaveName(string $name): string
    {
        if (str_contains($name, '-')) {
            $parts = explode('-', $name, 2);
            return $parts[0];
        }
        return $name;
    }
}
