<?php

namespace HnhDigital\GitDeploy\Commands;

use GuzzleHttp\Client as Guzzle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends Command
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $source_url = 'https://hnhdigital-os.github.io/git-deploy';

    /**
     * @var string
     */
    protected $source_phar = 'git-deploy.phar';

    /**
     * @var string
     */
    protected $local_phar_file;

    /**
     * @var string
     */
    protected $local_phar_file_basename;

    /**
     * @var string
     */
    protected $temp_directory;

    /**
     * @var string
     */
    protected $release;

    /**
     * @var string
     */
    protected $new_version;

    /**
     * @var string
     */
    protected $old_version;

    /**
     * @var string
     */
    protected $backup_extension = '-old.phar';

    /**
     * @var string
     */
    protected $backup_path;

    /**
     * @var string
     */
    protected $restore_path;

    /**
     * Configuration for command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Self-update this utility');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        if ($this->newVersionAvailable()) {
            $this->setLocalPharFile();
            $this->setTempDirectory();
            $this->backupPhar();
            $this->downloadPhar();
            $this->replacePhar();
            $this->output->writeln('<info>You are now running the latest version: '.$this->release.'-'.$this->new_version.'</info>');
        } else {
            $this->output->writeln('<info>You are already up-to-date: '.$this->release.'-'.$this->new_version.'</info>');
        }
    }

    /**
     * Check if there is a new version available.
     *
     * @return void
     */
    protected function newVersionAvailable()
    {
        global $application;

        list($this->release, $this->old_version) = explode('-', $application->getVersion());

        $this->new_version = trim($this->download($this->source_url.'/'.$this->release));

        return $this->old_version !== $this->new_version;
    }

    /**
     * Perform an rollback to previous version.
     *
     * @return bool
     */
    public function rollback()
    {
        if (!$this->restorePhar()) {
            return false;
        }

        return true;
    }

    /**
     * Set backup extension for old phar versions.
     *
     * @param string $extension
     */
    public function setBackupExtension($extension)
    {
        $this->backup_extension = $extension;

        return $this;
    }

    /**
     * Get backup extension for old phar versions.
     *
     * @return string
     */
    public function getBackupExtension()
    {
        return $this->backup_extension;
    }

    /**
     * Get local phar file.
     *
     * @return string
     */
    public function getLocalPharFile()
    {
        return $this->local_phar_file;
    }

    /**
     * Get local phar file.
     *
     * @return string
     */
    public function getLocalPharFileBasename()
    {
        return $this->local_phar_file_basename;
    }

    /**
     * Get local phar file.
     *
     * @return string
     */
    public function getTempDirectory()
    {
        return $this->temp_directory;
    }

    /**
     * Get local phar file.
     *
     * @return string
     */
    public function getTempPharFile()
    {
        return $this->getTempDirectory()
            .'/'
            .sprintf('%s.phar.temp', $this->getLocalPharFileBasename());
    }

    /**
     * Set backup path for old phar versions.
     *
     * @param string $file_path
     */
    public function setBackupPath($file_path)
    {
        $path = realpath(dirname($file_path));

        if (!is_dir($path)) {
            throw new \Exception(sprintf(
                'The backup directory does not exist: %s.', $path
            ));
        }

        if (!is_writable($path)) {
            throw new \Exception(sprintf(
                'The backup directory is not writeable: %s.', $path
            ));
        }

        $this->backup_path = $file_path;

        return $this;
    }

    /**
     * Get backup path for old phar versions.
     *
     * @return string
     */
    public function getBackupPath()
    {
        return $this->backup_path;
    }

    /**
     * Set path for the backup phar to rollback/restore from.
     *
     * @param string $file_path
     */
    public function setRestorePath($file_path)
    {
        $path = realpath(dirname($file_path));

        if (!file_exists($path)) {
            throw new \Exception(sprintf(
                'The restore phar does not exist: %s.', $path
            ));
        }

        if (!is_readable($path)) {
            throw new \Exception(sprintf(
                'The restore file is not readable: %s.', $path
            ));
        }
        $this->restore_path = $file_path;
    }

    /**
     * Get path for the backup phar to rollback/restore from.
     *
     * @return string
     */
    public function getRestorePath()
    {
        return $this->restore_path;
    }

    /**
     * Backup Phar.
     *
     * @return self
     */
    protected function backupPhar()
    {
        $result = copy($this->getLocalPharFile(), $this->getBackupPharFile());

        if ($result === false) {
            $this->cleanupAfterError();
            throw new \Exception(sprintf(
                'Unable to backup %s to %s.',
                $this->getLocalPharFile(),
                $this->getBackupPharFile()
            ));
        }

        return $this;
    }

    /**
     * Download Phar.
     *
     * @return self
     */
    protected function downloadPhar()
    {
        $version = $this->release == 'snapshot' ? 'snapshot' : $this->new_version;

        $download_path = $this->source_url.'/download/'.$version.'/'.$this->source_phar;

        $file_contents = $this->download($download_path, $this->getTempPharFile());

        if (!file_exists($this->getTempPharFile())) {
            throw new \Exception(
                'Creation of download file failed.'
            );
        }

        try {
            $this->validatePhar($this->getTempPharFile());
        } catch (\Exception $e) {
            restore_error_handler();
            $this->cleanupAfterError();
            throw $e;
        }
    }

    /**
     * Validate Phar.
     *
     * @return self
     */
    protected function validatePhar($phar)
    {
        chmod($phar, fileperms($this->getLocalPharFile()));

        return $this;
    }

    /**
     * Set local phar file.
     *
     * @param string $local_phar_file
     *
     * @return self
     */
    protected function setLocalPharFile($local_phar_file = null)
    {
        if (!is_null($local_phar_file)) {
            $local_phar_file = realpath($local_phar_file);
        } else {
            $local_phar_file = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        }

        if (!file_exists($local_phar_file)) {
            throw new \Exception(sprintf(
                'The set phar file does not exist: %s.', $local_phar_file
            ));
        }

        if (!is_writable($local_phar_file)) {
            throw new \Exception(sprintf(
                'The current phar file is not writeable and cannot be replaced: %s.',
                $local_phar_file
            ));
        }

        $this->local_phar_file = $local_phar_file;
        $this->local_phar_file_basename = basename($local_phar_file, '.phar');

        return $this;
    }

    /**
     * Set temporary directory.
     *
     * @return self
     */
    protected function setTempDirectory()
    {
        $temp_directory = dirname($this->getLocalPharFile());

        if (!is_writable($temp_directory)) {
            throw new \Exception(sprintf(
                'The directory is not writeable: %s.', $temp_directory
            ));
        }

        $this->temp_directory = $temp_directory;

        return $this;
    }

    /**
     * Replace Phar.
     *
     * @return self
     */
    protected function replacePhar()
    {
        rename($this->getTempPharFile(), $this->getLocalPharFile());

        return $this;
    }

    /**
     * Set temporary directory.
     *
     * @return self
     */
    protected function restorePhar()
    {
        $backup = $this->getRestorePharFile();

        if (!file_exists($backup)) {
            throw new \Exception(sprintf(
                'The backup file does not exist: %s.', $backup
            ));
        }

        $this->validatePhar($backup);

        return rename($backup, $this->getLocalPharFile());
    }

    /**
     * Get backup phar file.
     *
     * @return self
     */
    protected function getBackupPharFile()
    {
        if (null !== $this->getBackupPath()) {
            return $this->getBackupPath();
        }

        return $this->getTempDirectory()
            .'/'
            .sprintf('%s%s', $this->getLocalPharFileBasename(), $this->getBackupExtension());
    }

    /**
     * Get restore phar file.
     *
     * @return self
     */
    protected function getRestorePharFile()
    {
        if (null !== $this->getRestorePath()) {
            return $this->getRestorePath();
        }

        return $this->getTempDirectory()
            .'/'
            .sprintf('%s%s', $this->getLocalPharFileBasename(), $this->getBackupExtension()
        );
    }

    /**
     * Download a file.
     *
     * @param string      $url
     * @param null|string $output_path
     *
     * @return void
     */
    public static function download($url, $output_path = null)
    {
        $is_temp = false;
        if (is_null($output_path)) {
            $output_path = tempnam('/tmp', 'git-deploy');
            $is_temp = true;
        }
        $client = new Guzzle();

        try {
            $response = $client->get($url, ['sink' => $output_path]);
        } catch (\Exception $exception) {
            throw new \Exception(sprintf(
                'Download error: %s.', $exception->getResponse()
            ));

            exit(1);
        }

        $contents = file_get_contents($output_path);

        if ($is_temp) {
            unlink($output_path);
        }

        return $contents;
    }
}
