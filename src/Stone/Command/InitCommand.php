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
        $directory = $this->getRepositoryDirectory();

        $file = new JsonFile($directory.'/packages.json');

        if ($file->exists()) {
            $output->writeln('<info>Nothing to initialize</info>');

            return;
        }

        $output->writeln('<info>Initializing composer repository in</info> <comment>'.$directory.'</comment>');

        $file->write(array('packages' => (object) array()));
    }
}
