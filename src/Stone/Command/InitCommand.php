<?php

/*
 * This file is part of Stone.
 *
 * (c) Matthieu Moquet <matthieu@moquet.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stone\Command;

use Composer\Json\JsonFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Matthieu Moquet <matthieu@moquet.net>
 */
class InitCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initialize an empty directory for repositories')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->getComposer();
        $config = $composer->getConfig();

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
        $file = new JsonFile($this->getComposerHome().'/config.json');
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
