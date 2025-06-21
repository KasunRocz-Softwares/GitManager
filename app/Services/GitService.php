<?php

namespace App\Services;

use Exception;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Illuminate\Support\Facades\Log;

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

    /**
     * Get SFTP connection.
     *
     * @return SFTP SFTP connection instance
     * @throws Exception If SFTP connection fails
     */
    protected function getSFTPConnection(): SFTP
    {
        $sftp = new SFTP($this->sshHost);

        if (!$sftp->login($this->sshUsername, $this->sshPassword)) {
            throw new Exception("SFTP login failed for host: {$this->sshHost}, username: {$this->sshUsername}");
        }

        return $sftp;
    }

    /**
     * Upload a directory to the remote server via SFTP.
     *
     * @param string $localPath Local path to the directory to upload
     * @param string $remotePath Remote path where the directory should be uploaded
     * @return bool True if upload was successful
     * @throws Exception If upload fails
     */
    public function uploadDirectoryViaSFTP(string $localPath, string $remotePath): bool
    {
        try {
            $sftp = $this->getSFTPConnection();

            // Ensure the remote directory exists
            $this->runCommand("mkdir -p " . escapeshellarg($remotePath));

            // Log the upload operation
            Log::info("Uploading directory via SFTP", [
                'localPath' => $localPath,
                'remotePath' => $remotePath
            ]);

            // Create a recursive iterator to traverse the directory
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $uploadedFiles = 0;
            $totalFiles = 0;

            // Count total files first
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $totalFiles++;
                }
            }

            // Reset iterator
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            // Upload each file
            foreach ($iterator as $item) {
                $relativePath = substr($item->getPathname(), strlen($localPath) + 1);
                $remoteFilePath = $remotePath . '/' . $relativePath;

                if ($item->isDir()) {
                    // Create directory on remote server
                    $this->runCommand("mkdir -p " . escapeshellarg($remoteFilePath));
                } else {
                    // Upload file
                    if ($sftp->put($remoteFilePath, $item->getPathname(), SFTP::SOURCE_LOCAL_FILE)) {
                        $uploadedFiles++;

                        // Log progress periodically
                        if ($uploadedFiles % 10 === 0 || $uploadedFiles === $totalFiles) {
                            Log::info("SFTP upload progress", [
                                'uploaded' => $uploadedFiles,
                                'total' => $totalFiles,
                                'percentage' => round(($uploadedFiles / $totalFiles) * 100, 2) . '%'
                            ]);
                        }
                    } else {
                        throw new Exception("Failed to upload file: {$item->getPathname()} to {$remoteFilePath}");
                    }
                }
            }

            Log::info("SFTP upload completed successfully", [
                'uploadedFiles' => $uploadedFiles,
                'totalFiles' => $totalFiles
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("SFTP upload failed: " . $e->getMessage(), [
                'localPath' => $localPath,
                'remotePath' => $remotePath,
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
