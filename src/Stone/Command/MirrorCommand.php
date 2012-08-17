<?php

namespace Stone\Command;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Loader\RootPackageLoader;
use Composer\Package\PackageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('update-old', null, InputOption::VALUE_NONE, 'Also update previous downloaded packages')
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
        $composer = Factory::create(new NullIO(), $filename);

        // Retrieves installed packages
        $installedFile = new JsonFile($outputDir.'/installed.json');
        $installedPackages = $installedFile->exists() ? $this->getInstalledPackages($composer, $installedFile) : array();
        $packagesToInstall = $this->getPackagesToInstall($composer);

        if ($input->getOption('update-old')) {
            $packagesToInstall += $installedPackages;
        }

        // Retrieves all requires and download them
        $repositories = array();
        $packages = array();
        foreach ($packagesToInstall as $package) {
            $name = $package->getPrettyName();
            $targetDir = $outputDir.'/'.$name;

            $this->fetchPackage($composer, $package, $output, $targetDir, $installedPackages);

            $packages[] = $package;
            $repositories[] = array(
                'type' => $package->getSourceType(),
                'url'  => 'file://'.realpath($targetDir)
            );
        }

        $output->writeln('<info>Saving</info> installed repositories');
        $this->dumpInstalledPackages($installedFile, $packages);

        $output->writeln('<info>Dumping</info> satis config to <comment>satis.json</comment>');
        $file = new JsonFile('satis.json');
        $this->dumpSatisConfig($file, $repositories);
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

    /**
     * Get the packages to install.
     *
     * Returns Composer's requires
     *
     * @param Composer $composer Composer model
     */
    private function getPackagesToInstall(Composer $composer)
    {
        $manager = $composer->getRepositoryManager();
        $requires = $composer->getPackage()->getRequires();

        $packages = array();
        foreach ($requires as $link) {
            $package = $manager->findPackage($link->getTarget(), '9999999-dev');
            $packages[$package->getPrettyName()] = $package;
        }

        return $packages;
    }

    /**
     * Download or update a package.
     *
     * @param Composer         $composer
     * @param PackageInterface $package
     * @param OutputInterface  $output
     * @param string           $targetDir
     * @param array            $installedPackages
     */
    private function fetchPackage(Composer $composer, PackageInterface $package, OutputInterface $output, $targetDir, array $installedPackages = array())
    {
        $name = $package->getPrettyName();
        $manager = $composer->getDownloadManager();
        $manager->setPreferSource(true);

        if (isset($installedPackages[$name])) {
            // Updating
            $output->writeln(sprintf('<info>Updating</info> <comment>%s</comment>', $name));
            $manager->update($installedPackages[$name], $package, $targetDir);
        } else {
            // Downloading
            $output->writeln(sprintf('<info>Downloading</info> <comment>%s</comment>', $name));
            $manager->download($package, $targetDir);
        }
    }

    /**
     * Dump installed packages to installed.json.
     *
     * @param JsonFile $file     installed.json file
     * @param array    $packages List of packages
     */
    private function dumpInstalledPackages(JsonFile $file, array $packages)
    {
        $dumper = new ArrayDumper();
        $data = array();
        foreach ($packages as $package) {
            $data[] = $dumper->dump($package);
        }
        $file->write($data);
    }

    /**
     * Dump Satis configuration file.
     *
     * @param JsonFile $file         The configuration file
     * @param array    $repositories The repositories installed
     */
    private function dumpSatisConfig(JsonFile $file, array $repositories)
    {
        $config = array(
            'name' => 'Stone repositories',
            'homepage' => 'http://packages.example.org',
            'repositories' => $repositories,
            'require-all' => true
        );

        $file->write($config);
    }
}
