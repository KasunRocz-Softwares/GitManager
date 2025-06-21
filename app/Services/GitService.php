<?php

namespace App\Services;

use phpseclib3\Net\SSH2;

class GitService
{
    protected $sshHost;
    protected $sshUsername;
    protected $sshPassword;
    protected $repoPath;

    public function __construct($sshHost, $sshUsername, $sshPassword, $repoPath)
    {
        $this->sshHost = $sshHost;
        $this->sshUsername = $sshUsername;
        $this->sshPassword = $sshPassword;
        $this->repoPath = $repoPath;
    }

    protected function runCommand($command)
    {
        $ssh = new SSH2($this->sshHost);

        if (!$ssh->login($this->sshUsername, $this->sshPassword)) {
            throw new \Exception('Login failed');
        }

        $command = "cd {$this->repoPath} && " . $command;
        $output = $ssh->exec($command);

        if ($ssh->getExitStatus() !== 0) {
            throw new \Exception($output);
        }

        return $output;
    }

    public function listBranches()
    {
        $this->runCommand("sudo git fetch");
        $branchesOutput = $this->runCommand("sudo git branch -l");
        return $this->parseBranches($branchesOutput);
    }

    public function checkoutBranch($branchName)
    {
        $this->runCommand("sudo git fetch");
        $this->runCommand("sudo git reset --hard");
        $this->runCommand("sudo git checkout $branchName");
        $this->runCommand("sudo git pull");
        return $this->runCommand('sudo git branch --show-current');
    }

    public function runMultipleCommands(array $commands)
    {
        $commandString = implode(' && ', $commands);
        return $this->runCommand($commandString);
    }

    protected function parseBranches($branchesOutput)
    {
        $branches = array_filter(array_map('trim', explode("\n", $branchesOutput)));

        return array_map(function ($branch) {
            return str_replace('* ', '', $branch);
        }, $branches);
    }

    /**
     * @throws \Exception
     */
    public function currentBranch(): bool|string
    {
        return $this->runCommand('sudo git branch --show-current');
    }
}
