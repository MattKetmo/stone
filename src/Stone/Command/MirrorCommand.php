<?php

namespace Stone\Command;

use Composer\Composer;
use Composer\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class MirrorCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('mirror')
            ->setDescription('Mirror repositories')
            ->addArgument('file', InputArgument::REQUIRED, 'Json file to use')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename  = $input->getArgument('file');
        $outputDir = $this->getRepositoryDirectory();

        if (!file_exists($filename)) {
            throw new \LogicException(sprintf('File %s doen\'t exist', $filename));
        }

        $output->writeln(sprintf('<info>Mirroring repositories from <comment>%s</comment></info>', $filename));

        // Create composer model
        $composer = $this->getComposer($filename);

        // Retrieves installed packages
        $installedPackages = $this->getInstalledPackages($outputDir);
        $packagesToInstall = $this->getPackagesToInstall($composer);

        // Retrieves all requires and download them
        $repositories = array();
        $packages = array();
        foreach ($packagesToInstall as $package) {
            $name = $package->getPrettyName();
            $targetDir = $outputDir.'/'.$name;

            $initialPackage = isset($installedPackages[$name]) ? $installedPackages[$name] : null;

            $output->writeln(sprintf(
                '<info>%s</info> <comment>%s</comment>', $initialPackage ? 'Updating' : 'Downloading', $name
            ));

            $this->fetchPackage($composer->getDownloadManager(), $package, $targetDir, $initialPackage);

            $packages[$name] = $package;

            // Satis repositories
            $repositories[] = array(
                'type' => $package->getSourceType(),
                'url'  => 'file://'.realpath($targetDir)
            );
        }

        $installedPackages = array_merge($installedPackages, $packages);

        $output->writeln('<info>Saving</info> installed repositories');
        $this->dumpInstalledPackages($installedPackages, $outputDir);

        $output->writeln('<info>Dumping</info> satis config to <comment>satis.json</comment>');
        $this->dumpSatisConfig($repositories, $outputDir.'/satis.json');
    }
}
