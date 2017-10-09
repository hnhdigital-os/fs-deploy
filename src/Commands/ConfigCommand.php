<?php

namespace HnhDigital\GitDeploy\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class ConfigCommand extends Command
{
    /**
     * @var InputInterface
     */
    private $input = [];

    /**
     * @var OutputInterface
     */
    private $output = [];

    /**
     * @var Helper
     */
    private $helper;

    /**
     * Current working directory.
     *
     * @var string
     */
    private $cwd;

    /**
     * @var string
     */
    private $config_path = '.gitdeploy';

    /**
     * Current configuration.
     *
     * @var array
     */
    private $config = [];

    /**
     * Original configuration.
     *
     * @var array
     */
    private $original_config = [];

    /**
     * @var Flysystems
     */
    private $flysystems = [
        //['azure', 'Windows Azure Blob storage'],
        //['copy', 'Copy.com storage'],
        //['dropbox', 'Dropbox storage'],
        //['eventable', 'EventableFilesystem'],
        //['gridfs', 'MongoDB GridFS'],
        //['phpcr', 'PHPCR'],
        //['rackspace', 'Rackspace Cloud Files'],
        ['s3', 'S3 storage '],
        //['sftp', 'SFTP'],
        //['vfs', 'VFS'],
        //['webdav', 'WebDAV storage'],
        //['ziparchive', 'Zip'],
    ];

    private $s3_options = [
        'confirm'      => 'Confirm before deployment',
        'profile' => 'Profile name',
        'method'       => 'Method',
        'key'          => 'Key',
        'secret'       => 'Secret',
        'region'       => 'Region',
        'bucket'       => 'Bucket',
        'local_path'   => 'Source path',
        'remote_path'  => 'Destination path',
    ];

    /**
     * Configuration for command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('config')
            ->setDescription('Setup deployments');
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
        $this->input = $input;
        $this->output = $output;
        $this->checkConfig();
        $this->newOrEditExisting();
    }

    /**
     * Check this current working directory.
     *
     * @return void
     */
    private function checkConfig()
    {
        $this->helper = $this->getHelper('question');
        $this->cwd = getcwd();

        // Does not run if not a git repo.
        if (!file_exists($this->cwd.'/.git')) {
            throw new \Exception('This folder does not contain a git repository');
        }

        // Read the existing configuration.
        if (file_exists($this->cwd.'/'.$this->config_path)) {
            $this->original_config = $this->config = Yaml::parse(file_get_contents($this->cwd.'/'.$this->config_path));
        }

        if (file_exists($this->cwd.'/.gitignore')) {
            $gitignore_contents = file_get_contents($this->cwd.'/.gitignore');
            if (stripos($gitignore_contents, $this->config_path) === false) {
                $gitignore_contents = rtrim($gitignore_contents)."\n/".$this->config_path;
                file_put_contents($this->cwd.'/.gitignore', $gitignore_contents);
            }
        }

        if (!isset($this->config['deployments'])) {
            $this->config['deployments'] = [];
        }
    }

    /**
     * New filesystem or provide choice of new or edit existing.
     *
     * @return void
     */
    private function newOrEditExisting()
    {
        if (count($this->config['deployments']) == 0) {
            $this->output->writeln('');
            $this->output->writeln('<info>Let\'s get started</info>');

            return $this->newDeployment();
        }

        $this->output->writeln('');
        $this->output->writeln('Available options:');
        $this->output->writeln('[1] <info>New deployment</info>');
        $options_count = 1;
        foreach ($this->config['deployments'] as $deployment) {
            $options_count++;
            $this->output->writeln('['.$options_count.'] <comment>Edit: ['.$deployment['method'].'] '.$this->displayDeploymentLine($deployment).'</comment>');
        }

        $save_option = $options_count + 1;
        $save_exit_option = $options_count + 2;
        $exit_option = $options_count + 3;

        $this->output->writeln('['.$save_option.'] <options=underscore>Save changes</>');
        $this->output->writeln('['.$save_exit_option.'] <options=underscore>Save & exit</>');
        $this->output->writeln('['.$exit_option.'] <fg=red>Exit</>');

        $this->output->writeln('');
        $question = new Question('Choose option: ');
        $question->setValidator(function ($value) use ($options_count) {
            if (trim($value) == '') {
                throw new \Exception('The selected option cannot be empty');
            }
            if (!is_numeric($value)) {
                throw new \Exception('The selected option should be numeric.');
            }
            if ($value < 1 || $value > $options_count + 3) {
                throw new \Exception('The selected option should be between 1 and '.($options_count + 3).'.');
            }

            return $value;
        });
        $mode = $this->helper->ask($this->input, $this->output, $question);

        if ($mode == '1') {
            return $this->newDeployment();
        } elseif ($mode == $save_option) {
            $this->saveConfig();
            $this->newOrEditExisting();
        } elseif ($mode == $save_exit_option) {
            $this->saveConfig();

            exit(0);
        } elseif ($mode == $exit_option) {
            exit(0);
        }

        $deployment_id = $options_count - 2;

        $this->output->writeln('');

        $method = 'flysystem'.ucfirst($this->config['deployments'][$deployment_id]['method']);

        $this->displayDeployment($deployment_id);
        $this->output->writeln('');
        $this->output->writeln('Available options:');
        $this->output->writeln('[1] <info>Edit</info>');
        $this->output->writeln('[2] <fg=red>Delete</>');
        $this->output->writeln('[3] <comment>Cancel</comment>');
        $question = new Question('Choose option? (default: edit): ', '1');

        $question->setValidator(function ($value) use ($options_count) {
            if (trim($value) == '') {
                throw new \Exception('The selected option cannot be empty');
            }
            if (!is_numeric($value)) {
                throw new \Exception('The selected option should be numeric.');
            }
            if ($value < 1 || $value > 3) {
                throw new \Exception('That is not a valid option');
            }

            return $value;
        });
        $option = $this->helper->ask($this->input, $this->output, $question);

        if ($option == '1') {
            $this->$method($deployment_id);
        } elseif ($option == '2') {
            unset($this->config['deployments'][$deployment_id]);
        }

        $this->newOrEditExisting();
    }

    /**
     * Display the deployment information on a single line.
     *
     * @param array $deployment
     *
     * @return string
     */
    private function displayDeploymentLine($deployment)
    {
        switch ($deployment['method']) {
            case 's3':
                return array_get($deployment, 'local_path', '').' => '.$deployment['bucket'].'.'.$deployment['region'].':'.array_get($deployment, 'remote_path', '');
                break;
        }

        return '<error>Not a valid method</error>';
    }

    /**
     * Display the deployment information.
     *
     * @param int $deployment_id
     *
     * @return void
     */
    private function displayDeployment($deployment_id)
    {
        $deployment = $this->config['deployments'][$deployment_id];
        $config = [];

        // Filesystem options for each method.
        switch ($deployment['method']) {
            case 's3':
                $config = $this->s3_options;
        }

        $longest_title = 0;
        foreach ($config as $key => $title) {
            if ($longest_title < strlen($title)) {
                $longest_title = strlen($title);
            }
        }

        // Show configuration options nicely.
        foreach ($config as $key => $title) {
            if (array_key_exists($key, $deployment)) {
                $this->output->writeln(str_pad($title.':', $longest_title + 1, ' ', STR_PAD_LEFT).' '.$deployment[$key]);
            }
        }
    }

    /**
     * Create a new deployment.
     *
     * @return void
     */
    private function newDeployment()
    {
        // List of available filesystems.
        $this->output->writeln('');
        $this->output->writeln('Available filesystems:');
        foreach ($this->flysystems as $count => list($key, $name)) {
            $this->output->writeln('['.($count + 1).'] '.$name);
        }
        $this->output->writeln('');

        $options_count = count($this->flysystems);

        // Choose filesystem from list.
        $question = new Question('Select filesystem [1-'.$options_count.']: ');
        $question->setValidator(function ($value) use ($options_count) {
            if (trim($value) == '') {
                throw new \Exception('The filesystem choice cannot be empty');
            }

            if (!is_numeric($value)) {
                throw new \Exception('The filesystem choice should be numeric.');
            }

            if ($value < 1 || $value > $options_count) {
                throw new \Exception('The filesystem choice should be between 1 and '.$options_count.'.');
            }

            return $value;
        });
        $filesystem_id = $this->helper->ask($this->input, $this->output, $question);

        $this->output->writeln('');
        $this->output->writeln('<info>'.$this->flysystems[$filesystem_id - 1][1].'</info>');
        $this->output->writeln('');

        $method = 'flysystem'.ucfirst($this->flysystems[$filesystem_id - 1][0]);

        // Run specific method's filesystem configuration.
        $this->$method();

        $this->newOrEditExisting();
    }

    /**
     * Placeholder for the Copy.com filesystem.
     *
     * @return void
     */
    private function flysystemCopy()
    {
    }

    /**
     * Placeholder for the Dropbox filesystem.
     *
     * @return void
     */
    private function flysystemDropbox()
    {
    }

    /**
     * Placeholder for the EventableFilesystem.
     *
     * @return void
     */
    private function flysystemEventable()
    {
    }

    /**
     * Placeholder for the GridFS filesystem.
     *
     * @return void
     */
    private function flysystemGridFs()
    {
    }

    /**
     * Placeholder for the Rackspace filesystem.
     *
     * @return void
     */
    private function flysystemRackspace()
    {
    }

    /**
     * Configure a AWS S3 filesystem.
     *
     * @return void
     */
    private function flysystemS3($deployment_id = false)
    {
        // Defaults.
        $config = $default_config = [
            'confirm'     => 'N',
            'profile'     => '',
            'method'      => 's3',
            'key'         => '',
            'secret'      => '',
            'region'      => 'us-east-1',
            'bucket'      => '',
            'local_path'  => '/',
            'remote_path' => '/',
        ];

        // Existing deployment.
        if ($deployment_id !== false) {
            $config = $this->config['deployments'][$deployment_id];
        }

        // Profile name.
        $default = ' (default: '.array_get($config, 'profile', $default_config['profile']).')';
        $question = new Question('Enter profile name'.$default.': ', array_get($config, 'profile'));
        $config['profile'] = $this->helper->ask($this->input, $this->output, $question);

        // Profile name.
        $default = ' (default: '.array_get($config, 'confirm', $default_config['confirm']).')';
        $question = new Question('Confirm before deployment'.$default.': ', array_get($config, 'confirm'));
        $config['confirm'] = $this->helper->ask($this->input, $this->output, $question);

        // Access key.
        $default = ' (default: '.array_get($config, 'key', $default_config['key']).')';
        $question = new Question('Enter access key ID'.$default.': ', array_get($config, 'key'));
        $config['key'] = $this->helper->ask($this->input, $this->output, $question);

        // Secret key.
        $default = ' (default: '.array_get($config, 'secret', $default_config['secret']).')';
        $question = new Question('Enter secret access key'.$default.': ', array_get($config, 'secret'));
        $config['secret'] = $this->helper->ask($this->input, $this->output, $question);

        // Region.
        $default = ' (default: '.array_get($config, 'region', $default_config['region']).')';
        $question = new Question('Enter region (default: '.$default.'): ', array_get($config, 'region', $default_config['region']));
        $config['region'] = $this->helper->ask($this->input, $this->output, $question);

        // Bucket.
        $default = ' (default: '.array_get($config, 'bucket', $default_config['bucket']).')';
        $question = new Question('Enter bucket name'.$default.': ', array_get($config, 'bucket', $default_config['bucket']));
        $config['bucket'] = $this->helper->ask($this->input, $this->output, $question);

        // Local path.
        $question = new Question('Enter source path (default: '.array_get($config, 'local_path', '/').'): ', array_get($config, 'local_path', '/'));
        $config['local_path'] = $this->helper->ask($this->input, $this->output, $question);

        if (substr($config['local_path'], 0, 1) !== '/') {
            $config['local_path'] = '/'.$config['local_path'];
        }

        // Remote path.
        $question = new Question('Enter destination source (default: '.array_get($config, 'remote_path', '/').'): ', array_get($config, 'remote_path', '/'));
        $config['remote_path'] = $this->helper->ask($this->input, $this->output, $question);

        if (substr($config['remote_path'], 0, 1) !== '/') {
            $config['remote_path'] = '/'.$config['remote_path'];
        }

        // Repalce existing configuration.
        if ($deployment_id !== false) {
            $this->config['deployments'][$deployment_id] = $config;

            return;
        }

        // Add new deployment.
        $this->config['deployments'][] = $config;
    }

    /**
     * Placeholder for the Sftp filesystem.
     *
     * @return void
     */
    private function flysystemSftp()
    {
    }

    /**
     * Placeholder for the Webdav filesystem.
     *
     * @return void
     */
    private function flysystemWebdav()
    {
    }

    /**
     * Placeholder for the VFS filesystem.
     *
     * @return void
     */
    private function flysystemVfs()
    {
    }

    /**
     * Placeholder for the ZipArchive filesystem.
     *
     * @return void
     */
    private function flysystemZiparchive()
    {
    }

    /**
     * Save configuration.
     *
     * @return void
     */
    private function saveConfig()
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Configuration saved!</info>');
        $this->output->writeln('');

        return file_put_contents($this->cwd.'/'.$this->config_path, Yaml::dump($this->config));
    }
}
