<?php

namespace Stone\Command;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Loader\RootPackageLoader;
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

        // Create composer model
        $io = new NullIO();
        $composer = Factory::create($io, $filename);
        $rm = $composer->getRepositoryManager();
        $dm = $composer->getDownloadManager();
        $dm->setPreferSource(true);

        // Retrieves installed packages
        $installedFile = new JsonFile($ouputDir.'/installed.json');
        $intalledPackages = $this->getInstalledPackages($composer, $installedFile);

        // Retrieves all requires and download them
        $repositories = array();
        $packages = array();
        foreach ($composer->getPackage()->getRequires() as $link) {
            // Lookup for the last dev package
            $package = $rm->findPackage($link->getTarget(), '9999999-dev');

            $name = $package->getPrettyName();
            $targetDir = $ouputDir.'/'.$name;

            if (isset($intalledPackages[$name])) {
                // Updating
                $output->writeln(sprintf('<info>Updating</info> <comment>%s</comment>', $name));
                $dm->update($intalledPackages[$name], $package, $targetDir);
            } else {
                // Downloading
                $output->writeln(sprintf('<info>Downloading</info> <comment>%s</comment>', $name));
                $dm->download($package, $targetDir);
            }

            $packages[] = $package;
            $repositories[] = array(
                'type' => $package->getSourceType(),
                'url'  => 'file://'.realpath($targetDir)
            );
        }

        // Dump installed packages to installed.json
        $dumper = new ArrayDumper();
        $data = array();
        foreach ($packages as $package) {
            $data[] = $dumper->dump($package);
        }
        $installedFile->write($data);

        // Dump Satis configuration file
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

    /**
     * Get installed packages.
     *
     * @param Composer $composer Composer model
     * @param JsonFile $file     The installed.json file
     *
     * @return array Array of PackageInterface
     */
    private function getInstalledPackages(Composer $composer, JsonFile $file)
    {
        $manager = $composer->getRepositoryManager();
        $config = $composer->getConfig();
        $loader = new RootPackageLoader($manager, $config);

        $packages = array();
        foreach ($file->read() as $config) {
            $package = $loader->load($config);
            $packages[$package->getPrettyName()] = $package;
        }

        return $packages;
    }
}
