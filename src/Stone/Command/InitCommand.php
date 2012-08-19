<?php

/*
 * This file is part of Stone.
 *
 * (c) Matthieu Moquet <matthieu@moquet.net>
 *
 * For the full copyright and license information, please view
 * the license that is located at the bottom of this file.
 */

namespace Stone\Command;

use Composer\Json\JsonFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initialize an empty directory for repositories')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isInitDone = true;

        // Init packages.json
        $directory = $this->getRepositoryDirectory();

        $file = new JsonFile($directory.'/packages.json');

        if (!$file->exists()) {
            $output->writeln('<info>Initializing composer repository in</info> <comment>'.$directory.'</comment>');
            $file->write(array('packages' => (object) array()));
            $isInitDone = false;
        }

        // Init ~/composer/config.json
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $home = getenv('APPDATA').'/Composer';
        } else {
            $home = getenv('HOME').'/.composer';
        }

        $file = new JsonFile($home.'/config.json');
        $config = $file->exists() ? $file->read() : array() ;
        if (!isset($config['repositories'])) {
            $config['repositories'] = array();
        }

        $isRepoActived = false;
        foreach ($config['repositories'] as $repo) {
            if ($repo['type'] === 'composer' && $repo['url'] === 'file://'.$directory) {
                $isRepoActived = true;
            }
        }

        if (!$isRepoActived) {
            $output->writeln('<info>Writing stone repository in global configuration</info>');
            $config['repositories'][] = array(
                'type' => 'composer',
                'url'  => 'file://'.$directory,
            );
            $file->write($config);
            $isInitDone = false;
        }

        if ($isInitDone) {
            $output->writeln('<info>It seems stone is already configured</info>');
        }
    }
}
