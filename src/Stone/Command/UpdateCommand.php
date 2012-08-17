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

class UpdateCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update mirroed repositories')
            ->addArgument('output-dir', InputArgument::REQUIRED, 'Location of downloaded repositories')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputDir = $input->getArgument('output-dir');

        // Create composer model
        $composer = $this->getComposer();

        // Retrieves installed packages
        $installedPackages = $this->getInstalledPackages($outputDir);

        // Retrieves all requires and download them
        $repositories = array();
        $packages = array();
        foreach ($installedPackages as $initialPackage) {
            $name = $initialPackage->getPrettyName();
            $targetDir = $outputDir.'/'.$name;

            $output->writeln(sprintf('<info>Updating</info> <comment>%s</comment>', $name));

            $package = $this->findDevPackage($composer->getRepositoryManager(), $initialPackage->getPrettyName());

            // @todo check if packages version are aifferent
            $this->fetchPackage($composer->getDownloadManager(), $package, $targetDir, $initialPackage);

            $packages[$name] = $package;
        }

        $output->writeln('<info>Saving</info> updated repositories');
        $this->dumpInstalledPackages($installedPackages, $outputDir);
    }
}