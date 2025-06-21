<?php

namespace App\Services;

use Exception;
use phpseclib3\Net\SSH2;

/**
 * Service for interacting with Git repositories via SSH.
 */
class GitService
{
    /**
     * @var string SSH host address
     */
    protected string $sshHost;

    /**
     * @var string SSH username
     */
    protected string $sshUsername;

    /**
     * @var string SSH password
     */
    protected string $sshPassword;

    /**
     * @var string Repository path on the remote server
     */
    protected string $repoPath;

    /**
     * @var SSH2|null SSH connection instance
     */
    protected ?SSH2 $ssh = null;

    /**
     * Constructor.
     *
     * @param string $sshHost     SSH host address
     * @param string $sshUsername SSH username
     * @param string $sshPassword SSH password
     * @param string $repoPath    Repository path on the remote server
     */
    public function __construct(string $sshHost, string $sshUsername, string $sshPassword, string $repoPath)
    {
        $this->sshHost = $sshHost;
        $this->sshUsername = $sshUsername;
        $this->sshPassword = $sshPassword;
        $this->repoPath = $repoPath;
    }

    /**
     * Get SSH connection.
     *
     * @return SSH2 SSH connection instance
     * @throws Exception If SSH connection fails
     */
    protected function getSSHConnection(): SSH2
    {
        if ($this->ssh === null) {
            $this->ssh = new SSH2($this->sshHost);

            if (!$this->ssh->login($this->sshUsername, $this->sshPassword)) {
                throw new Exception("SSH login failed for host: {$this->sshHost}, username: {$this->sshUsername}");
            }
        }

        return $this->ssh;
    }

    /**
     * Run a command on the remote server.
     *
     * @param string $command Command to run
     * @return string Command output
     * @throws Exception If command execution fails
     */
    protected function runCommand(string $command): string
    {
        $ssh = $this->getSSHConnection();

        $fullCommand = "cd {$this->repoPath} && " . $command;
        $output = $ssh->exec($fullCommand);

        if ($ssh->getExitStatus() !== 0) {
            throw new Exception("Command execution failed: {$fullCommand}\nOutput: {$output}");
        }

        return $output;
    }

    /**
     * List all branches in the repository.
     *
     * @return array List of branch names
     * @throws Exception If command execution fails
     */
    public function listBranches(): array
    {
        $this->runCommand("sudo git fetch");
        $branchesOutput = $this->runCommand("sudo git branch -l");
        return $this->parseBranches($branchesOutput);
    }

    /**
     * Checkout a branch in the repository.
     *
     * @param string $branchName Name of the branch to checkout
     * @return string Current branch name after checkout
     * @throws Exception If command execution fails
     */
    public function checkoutBranch(string $branchName): string
    {
        if (empty($branchName)) {
            throw new Exception("Branch name cannot be empty");
        }

        $this->runCommand("sudo git fetch");
        $this->runCommand("sudo git reset --hard");
        $this->runCommand("sudo git checkout " . escapeshellarg($branchName));
        $this->runCommand("sudo git pull");
        return $this->runCommand('sudo git branch --show-current');
    }

    /**
     * Run multiple Git commands in sequence.
     *
     * @param array $commands Array of commands to run
     * @return string Combined output of all commands
     * @throws Exception If command execution fails
     */
    public function runMultipleCommands(array $commands): string
    {
        if (empty($commands)) {
            throw new Exception("Commands array cannot be empty");
        }

        $commandString = implode(' && ', $commands);
        return $this->runCommand($commandString);
    }

    /**
     * Parse the output of the git branch command.
     *
     * @param string $branchesOutput Output of git branch command
     * @return array Array of branch names
     */
    protected function parseBranches(string $branchesOutput): array
    {
        $branches = array_filter(array_map('trim', explode("\n", $branchesOutput)));

        return array_map(function ($branch) {
            return str_replace('* ', '', $branch);
        }, $branches);
    }

    /**
     * Get the current branch name.
     *
     * @return string Current branch name
     * @throws Exception If command execution fails
     */
    public function currentBranch(): string
    {
        return $this->runCommand('sudo git branch --show-current');
    }
}
