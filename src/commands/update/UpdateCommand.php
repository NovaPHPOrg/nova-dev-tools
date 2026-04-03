<?php

namespace nova\commands\update;

use nova\commands\BaseCommand;
use nova\commands\GitCommand;
use nova\console\Output;

class UpdateCommand extends BaseCommand
{

    public function init(): void
    {
        $git = new GitCommand($this);
        $git->updateSubmodules();
        // 获取当前分支
        $currentBranch = trim($this->exec('git branch --show-current'));
        if (!$currentBranch) {
            Output::warn("Could not determine current branch in './'.");
        }

        Output::info("Current branch in './': '$currentBranch'");

        // 执行 git pull 拉取远程更新
        if (!$this->exec('git pull origin ' . $currentBranch)) {
            Output::warn("Failed to pull from origin in './'.");
        } else {
            Output::success("Successfully pulled updates for submodule './' on branch '$currentBranch'.");
        }

    }
}