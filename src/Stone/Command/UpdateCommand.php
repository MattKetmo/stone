<?php

namespace Stone\Command;

use Composer\Composer;
use Composer\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update mirroed repositories')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Updating proxy repositories</info>');

        $outputDir = $this->getRepositoryDirectory();

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

            $package = $this->findDevPackage($composer->getRepositoryManager(), $initialPackage->getPrettyName());

            if ($package->getSourceReference() === $initialPackage->getSourceReference()) {
                $output->writeln(sprintf('<info>Skiping</info> <comment>%s</comment> (already up to date)', $name));
                continue;
            }

            $output->writeln(sprintf('<info>Updating</info> <comment>%s</comment>', $name));
            $this->fetchPackage($composer->getDownloadManager(), $package, $targetDir, $initialPackage);

            $packages[$name] = $package;
            $repositories[] = array(
                'type' => $package->getSourceType(),
                'url'  => 'file://'.realpath($outputDir.'/'.$package->getName())
            );
        }

        $output->writeln('<info>Saving</info> updated repositories');
        $this->dumpInstalledPackages($installedPackages, $outputDir);

        $output->writeln('<info>Update packages.json</info>');
        $this->dumpPackagesJson($composer, $repositories, $outputDir);
    }
}
