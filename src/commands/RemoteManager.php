<?php

namespace nova\commands;

use nova\console\Output;

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
     * 校验 GitHub 仓库列表响应结构。
     *
     * 预期格式：list<array{name:string,...}>
     */
    private function isValidRepoListPayload(array $data): bool
    {
        if (!array_is_list($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['name']) || !is_string($item['name'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 从环境变量读取 GitHub Token。
     *
     * 优先级：NOVA_GITHUB_TOKEN > GITHUB_TOKEN > GH_TOKEN
     */
    protected function readGithubToken(): string
    {
        $keys = [
            'NOVA_GITHUB_TOKEN',
            'GITHUB_TOKEN',
            'GH_TOKEN',
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
     * 拉取组织公开仓库列表（带文件缓存，TTL = CACHE_TTL 秒）。
     *
     * @return array<int,mixed>|null 失败返回 null，成功返回仓库数组
     */
    protected function listOrgRepos(): ?array
    {
        $cacheFile = $this->repoCacheFile();

        // 命中有效缓存直接返回（仅接受有效仓库列表）
        if (
            file_exists($cacheFile) &&
            (time() - filemtime($cacheFile)) < self::CACHE_TTL
        ) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && $this->isValidRepoListPayload($cached)) {
                return $cached;
            }

            // 旧缓存结构异常（如限流错误对象），丢弃并走网络拉取。
            @unlink($cacheFile);
        }

        $url = $this->buildOrgReposApiUrl();
        $ch = curl_init($url);

        $headers = [
            'User-Agent: PHP',
            'Accept: application/vnd.github+json',
        ];

        $token = $this->readGithubToken();
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

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
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            Output::error("Failed to request GitHub API: $error");
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            Output::error("Invalid response from GitHub API.");
            return null;
        }

        if ($statusCode >= 400) {
            $msg = isset($data['message']) && is_string($data['message'])
                ? $data['message']
                : "HTTP $statusCode";

            if (
                $statusCode === 403 &&
                str_contains(strtolower($msg), 'rate limit') &&
                $token === ''
            ) {
                $msg .= ' Set NOVA_GITHUB_TOKEN (or GITHUB_TOKEN) to increase limits.';
            }

            Output::error("GitHub API error: $msg");
            return null;
        }

        if (!$this->isValidRepoListPayload($data)) {
            $msg = isset($data['message']) && is_string($data['message'])
                ? $data['message']
                : 'Unexpected API payload shape.';
            Output::error("Failed to parse repository list: $msg");
            return null;
        }

        // 写入缓存（仅缓存有效仓库列表）
        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));

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
