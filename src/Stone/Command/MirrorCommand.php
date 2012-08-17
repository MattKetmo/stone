<?php

namespace Stone\Command;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Loader\RootPackageLoader;
use Composer\Package\Loader;
use Composer\Package\PackageInterface;
use Composer\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MirrorCommand extends BaseCommand
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
        $filename  = $input->getArgument('file');
        $outputDir = $input->getArgument('output-dir');

        if (!file_exists($filename)) {
            throw new \LogicException(sprintf('File %s doen\'t exist', $filename));
        }

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