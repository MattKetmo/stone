<?php

namespace Stone\Command;

use Composer\Composer;
use Composer\Downloader\DownloadManager;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    /**
     * Create a composer model.
     *
     * @param string $localConfig
     *
     * @return Composer
     */
    protected function getComposer($localConfig = null)
    {
        return Factory::create(new NullIO(), $localConfig);
    }

    /**
     * @see Factory::createConfig()
     */
    protected function getRepositoryDirectory()
    {
        // load main Composer configuration
        if (!$home = getenv('COMPOSER_STONE_HOME')) {
            if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
                $home = getenv('APPDATA') . '/Composer/Stone';
            } else {
                $home = getenv('HOME') . '/.composer/stone';
            }
        }

        // Ensure directory exists
        if (!is_dir($home)) {
            @mkdir($home, 0777, true);
        }

        return $home;
    }

    /**
     * Get installed packages from the installed.json file.
     *
     * @param Composer $composer  Composer model
     * @param string   $directory The installed repositories directory
     *
     * @return array Array of PackageInterface
     */
    protected function getInstalledPackages($directory)
    {
        $file = new JsonFile($directory.'/installed.json');

        if (!$file->exists()) {
            return array();
        }

        $loader = new ArrayLoader();

        $packages = array();
        foreach ($file->read() as $config) {
            $package = $loader->load($config);
            $packages[$package->getPrettyName()] = $package;
        }

        return $packages;
    }

    /**
     * Dump installed packages to installed.json file.
     *
     * @param array  $packages  List of packages
     * @param string $directory Directory where packages are saved
     */
    protected function dumpInstalledPackages(array $packages, $directory)
    {
        $file = new JsonFile($directory.'/installed.json');
        $dumper = new ArrayDumper();

        $data = array();
        foreach ($packages as $package) {
            $data[] = $dumper->dump($package);
        }

        $file->write($data);
    }

    /**
     * Get the packages to install.
     *
     * Returns Composer's requires
     *
     * @param Composer $composer Composer model
     */
    protected function getPackagesToInstall(Composer $composer)
    {
        $manager = $composer->getRepositoryManager();
        $requires = $composer->getPackage()->getRequires();

        $packages = array();
        foreach ($requires as $link) {
            $package = $this->findDevPackage($manager, $link->getTarget());
            $packages[$package->getPrettyName()] = $package;
        }

        return $packages;
    }

    /**
     * Find the dev-master package.
     *
     * @param RepositoryManager $manager Composer repository manger
     * @param string            $name    Package's name
     *
     * @return PackageInterface The found package
     */
    protected function findDevPackage(RepositoryManager $manager, $name)
    {
        return $manager->findPackage($name, '9999999-dev');
    }

    /**
     * Download or update a package.
     *
     * @param Composer         $composer
     * @param PackageInterface $package
     * @param OutputInterface  $output
     * @param string           $targetDir
     * @param PackageInterface $initialPackage
     */
    protected function fetchPackage(DownloadManager $manager, PackageInterface $package, $targetDir, PackageInterface $initialPackage = null)
    {
        // Better to download the sources
        $manager->setPreferSource(true);

        if (null !== $initialPackage) {
            $manager->update($initialPackage, $package, $targetDir);
        } else {
            $manager->download($package, $targetDir);
        }
    }

    /**
     * Dump Satis configuration file.
     *
     * @param array  $repositories The installed packages
     * @param string $file         The configuration file
     */
    protected function dumpSatisConfig(array $repositories, $file)
    {
        $file = new JsonFile($file);

        $config = array(
            'name' => 'Stone repositories',
            'homepage' => 'http://packages.example.org',
            'repositories' => $repositories,
            'require-all' => true
        );

        $file->write($config);
    }
}
