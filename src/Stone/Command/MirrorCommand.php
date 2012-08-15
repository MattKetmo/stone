<?php

namespace Stone\Command;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class MirrorCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('mirror')
            ->setDescription('Mirror repositories')
            ->addArgument('file', InputArgument::REQUIRED, 'Json file to use')
            ->addArgument('output-dir', InputArgument::REQUIRED, 'Location where to download repositories')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('file');
        $ouputDir = $input->getArgument('output-dir');

        if (!file_exists($filename)) {
            throw new \LogicException(sprintf('File %s doen\'t exist', $filename));
        }

        $io = new NullIO();
        $composer  = Factory::create($io, $filename);

        $rm = $composer->getRepositoryManager();
        $dm = $composer->getDownloadManager();
        $dm->setPreferSource(true);

        $repositories = array();
        $root = $composer->getPackage();
        foreach ($root->getRequires() as $link) {
            // Lookup for the last dev package
            $package = $rm->findPackage($link->getTarget(), '9999999-dev');

            $name = $package->getPrettyName();
            $targetDir = $ouputDir.'/'.$name;

            $output->writeln(sprintf('<info>Downloading</info> <comment>%s</comment>', $name));
            $dm->download($package, $targetDir);

            $repositories[] = array(
                'type' => $package->getSourceType(),
                'url'  => 'file://'.realpath($targetDir)
            );
        }

        $output->writeln('<info>Dumping</info> satis config to <comment>satis.json</comment>');
        $file = new JsonFile('satis.json');
        $config = array(
            'name' => 'Stone repositories',
            'homepage' => 'http://packages.example.org',
            'repositories' => $repositories,
            'require-all' => true
        );

        $file->write($config);
    }
}
