<?php

namespace nova\commands\migrate;

use nova\commands\BaseCommand;
use nova\console\Output;

/**
 * MigrateCommand
 *
 * 将 .gitmodules 中所有指向 https://git.ankio.icu/nova-ui 的子模块
 * 迁移到 https://github.com/NovaPHPOrgUI。
 * 若子模块远程已是目标地址，则直接执行 pull 更新。
 *
 * 用法：nova migrate
 */
class MigrateCommand extends BaseCommand
{
    private const SOURCE_BASE = 'https://git.ankio.icu/nova-ui';
    private const TARGET_BASE = 'https://github.com/NovaPHPOrgUI';
    private const VISIBILITY  = 'public';

    // ─── 入口 ─────────────────────────────────────────────────────────────

    public function init(): void
    {
        // ── 检查 gh CLI ──
        if ($this->execSafe('gh --version') === '') {
            Output::error("GitHub CLI (gh) is not installed or not found in PATH.");
            Output::muted("Install it from: https://cli.github.com/");
            return;
        }

        Output::info("Source : " . self::SOURCE_BASE);
        Output::info("Target : " . self::TARGET_BASE);
        Output::divider();

        $this->migrateSubmodules();
    }

    // ─── 读取并处理 .gitmodules ───────────────────────────────────────────

    private function migrateSubmodules(): void
    {
        $gitmodulesPath = $this->workingDir . DIRECTORY_SEPARATOR . '.gitmodules';

        if (!file_exists($gitmodulesPath)) {
            Output::warn(".gitmodules not found in: {$this->workingDir}");
            return;
        }

        $sections = parse_ini_file($gitmodulesPath, true, INI_SCANNER_TYPED);
        if (empty($sections)) {
            Output::warn(".gitmodules is empty or could not be parsed.");
            return;
        }

        $processed = 0;
        $skipped   = 0;

        foreach ($sections as $section => $config) {
            $subPath = (string) ($config['path'] ?? '');
            $url     = rtrim((string) ($config['url'] ?? ''), '/');

            if ($subPath === '' || $url === '') {
                continue;
            }

            $repoName  = basename($subPath);
            $targetUrl = self::TARGET_BASE . "/{$repoName}.git";
            $absDir    = $this->workingDir . DIRECTORY_SEPARATOR . $subPath;

            Output::divider();

            // ── 已迁移：直接 pull ──
            if ($url === $targetUrl) {
                Output::info("Already migrated — pulling: $repoName");
                $this->pullRepo($absDir);
                $processed++;
                continue;
            }

            // ── 不属于源地址 → 跳过 ──
            if (!str_starts_with($url, self::SOURCE_BASE)) {
                Output::muted("Skip (not from source): $repoName  [$url]");
                $skipped++;
                continue;
            }

            Output::info("Migrating: $repoName");
            Output::muted("  from : $url");
            Output::muted("  to   : $targetUrl");

            if (!is_dir($absDir)) {
                Output::warn("Submodule directory missing, skipping: $absDir");
                $skipped++;
                continue;
            }

            $this->migrateRepo($absDir, $repoName, $targetUrl, $section);
            $processed++;
        }

        Output::divider();
        if ($skipped > 0) {
            Output::muted("Skipped: $skipped");
        }
        Output::success("Done — $processed submodule(s) processed.");
    }

    // ─── 单子模块迁移 ─────────────────────────────────────────────────────

    private function migrateRepo(
        string $dir,
        string $repoName,
        string $targetUrl,
        string $sectionName
    ): void {
        $orgRepo = 'NovaPHPOrgUI/' . $repoName;

        // ── 创建 GitHub 仓库（已存在时忽略）──
        $createOut = $this->execSafe(
            'gh repo create ' . escapeshellarg($orgRepo) . ' --' . self::VISIBILITY,
            $dir
        );
        if ($createOut !== '') {
            Output::success("Repository created: $orgRepo");
        } else {
            Output::warn("Repository $orgRepo may already exist, continuing...");
        }

        // ── 更新 git remote ──
        $this->execSafe('git remote remove origin', $dir);
        if ($this->exec('git remote add origin ' . escapeshellarg($targetUrl), $dir) === false) {
            Output::error("Failed to set remote origin for: $repoName");
            return;
        }
        Output::success("Remote origin → $targetUrl");

        // ── 推送分支 + 标签 ──
        if ($this->exec('git push -u origin HEAD --force', $dir) === false) {
            Output::warn("Branch push failed for: $repoName");
        } else {
            Output::success("Branch pushed.");
        }

        if ($this->exec('git push origin --tags', $dir) === false) {
            Output::warn("Tag push failed for: $repoName");
        } else {
            Output::success("Tags pushed.");
        }

        // ── 更新 .gitmodules 中的 URL ──
        $this->exec(
            'git config --file .gitmodules ' .
            escapeshellarg("submodule.{$sectionName}.url") . ' ' .
            escapeshellarg($targetUrl),
            $this->workingDir
        );

        // ── 更新 .git/config 中的 URL ──
        $this->exec(
            'git config --file .git/config ' .
            escapeshellarg("submodule.{$sectionName}.url") . ' ' .
            escapeshellarg($targetUrl),
            $this->workingDir
        );

        Output::success("Config updated for submodule: $sectionName");
    }

    // ─── Pull 更新 ───────────────────────────────────────────────────────

    private function pullRepo(string $dir): void
    {
        $branch = trim($this->execSafe('git branch --show-current', $dir));
        if ($branch === '') {
            Output::warn("Could not determine current branch, skipping pull.");
            return;
        }

        if ($this->exec("git pull origin {$branch}", $dir) === false) {
            Output::warn("Pull failed for branch: $branch");
        } else {
            Output::success("Pulled latest from origin/{$branch}.");
        }
    }
}

