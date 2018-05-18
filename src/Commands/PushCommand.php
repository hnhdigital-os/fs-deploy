<?php

namespace HnhDigital\FsDeploy\Commands;

use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class PushCommand extends Command
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
     * Current configuration.
     *
     * @var array
     */
    private $config = [];

    /**
     * @var string
     */
    private $config_path = '.fsdeploy';

    /**
     * Configuration for command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('push')
            ->setDescription('Run the configured deployments')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Profile.');
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
        $this->deploy();
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

        // This git repo does not have a .fsdeploy
        if (!file_exists($this->cwd.'/'.$this->config_path)) {
            throw new \Exception('This folder has not been setup for FsDeploy');
        }

        // Read the existing configuration.
        if (file_exists($this->cwd.'/'.$this->config_path)) {
            $this->original_config = $this->config = Yaml::parse(file_get_contents($this->cwd.'/'.$this->config_path));
        }

        if (!isset($this->config['deployments'])) {
            $this->config['deployments'] = [];
        }
    }

    /**
     * Run each of the configured deployments.
     *
     * @return void
     */
    private function deploy()
    {
        foreach ($this->config['deployments'] as $deployment) {
            // Deployment profile specified.
            if ($this->input->getOption('profile') !== '' && !is_null($this->input->getOption('profile'))) {
                if (!array_has($deployment, 'profile') || $this->input->getOption('profile') !== $deployment['profile']) {
                    continue;
                }
            }

            // Visual.
            $this->output->writeln('Processing <info>['.$deployment['method'].'] '.$this->displayDeploymentLine($deployment).'</info>');

            // Deployment requires confirmation.
            if (array_has($deployment, 'confirm')) {
                if (stripos($deployment['confirm'], 'Y') !== false) {
                    $question = new Question('Confirm deployment [y/N]: ', 'N');
                    $answered = false;

                    while (!$answered) {
                        $answer = $this->helper->ask($this->input, $this->output, $question);

                        if (stripos($answer, 'N') !== false || stripos($answer, 'Y') !== false) {
                            $answered = true;
                        }
                    }

                    if (stripos($answer, 'N') !== false) {
                        $this->output->writeln('Skipping.');
                        continue;
                    }
                }
            }

            // Setup filesystems.
            $local = new Filesystem(new LocalAdapter($this->cwd.$deployment['local_path']));
            $remote = new Filesystem($this->getAdapter($deployment));

            $this->output->writeln('Synchronizing files.');

            $this->output->writeln('');

            // Load local content list.
            $contents = $local->listContents('', true);

            $this->output->writeln('Found <info>'.count($contents).' files in local</info>');

            $this->output->writeln('');

            // Check files and sync.
            foreach ($contents as $entry) {
                if ($entry['type'] == 'file') {
                    $display_path = $entry['path'];
                    if (mb_strlen($entry['path']) > 57) {
                        $display_path = '...'.substr($display_path, -57);
                    }

                    $this->output->write(sprintf('[ %s ] [ %s ] %s', $local->getTimestamp($entry['path']), str_pad($local->getSize($entry['path']), 10, ' ', STR_PAD_LEFT), str_pad($display_path, 60, ' ')));
                    $update = false;

                    if (!$remote->has($entry['path'])) {
                        $update = true;
                    } elseif ($local->getTimestamp($entry['path']) > $remote->getTimestamp($entry['path'])
                        || $local->getSize($entry['path']) != $remote->getSize($entry['path'])) {
                        $update = true;
                    }

                    if ($update) {
                        $this->output->write(' [ --> ]');
                        $remote->put($entry['path'], $local->read($entry['path']));
                    }

                    $this->output->writeln('');
                }
            }

            $this->output->writeln('');
        }
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
        $text = '<error>Not a valid method</error>';

        switch ($deployment['method']) {
            case 's3':
                $text = array_get($deployment, 'local_path', '').' => '.$deployment['bucket'].'.'.$deployment['region'].':'.array_get($deployment, 'remote_path', '');
                break;
        }

        if (array_has($deployment, 'profile')) {
            $text = '['.strtoupper($deployment['profile']).'] '.$text;
        }

        return $text;
    }

    /**
     * Get adapter for the given deployment method.
     *
     * @param array $deployment
     *
     * @return mixed
     */
    private function getAdapter($deployment)
    {
        switch ($deployment['method']) {
            case 's3':
                $client = \Aws\S3\S3Client::factory([
                    'credentials' => [
                        'key'    => $deployment['key'],
                        'secret' => $deployment['secret'],
                    ],
                    'region'  => $deployment['region'],
                    'version' => 'latest',
                ]);

                return new AwsS3Adapter($client, $deployment['bucket'], $deployment['remote_path']);
        }
    }
}
