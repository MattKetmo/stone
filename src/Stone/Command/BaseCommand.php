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

/**
 * @author Matthieu Moquet <matthieu@moquet.net>
 */
abstract class BaseCommand extends Command
{
    /**
     * Create a composer model.
     *
     * @param string $localConfig The local configuration file
     *
     * @return Composer
     */
    protected function getComposer($localConfig = null)
    {
        $config = array();

        // Get local configuration
        if (null !== $localConfig) {
            $file = new JsonFile($localConfig);

            if ($file->exists()) {
                $config = $file->read();
            }
        }

        // Hack: retrieve global configuration to remove stone repository by its key
        $file = new JsonFile($this->getComposerHome().'/config.json');
        if ($file->exists()) {
            $data = $file->read();

            if (isset($data['repositories'])) {
                foreach ($data['repositories'] as $key => $repository) {
                    if ($repository['type'] === 'composer' && $repository['url'] === 'file://'.$this->getRepositoryDirectory()) {
                        $config = array_merge($config, array('repositories' => array($key => false)));
                    }
                }
            }
        }


        return Factory::create(new NullIO(), $config);
    }

    /**
     * Retrieves Composer global configuration directory.
     *
     * @see Factory::createConfig()
     *
     * @return string
     */
    protected function getComposerHome()
    {
        if (!$home = getenv('COMPOSER_HOME')) {
            if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
                $home = getenv('APPDATA') . '/Composer';
            } else {
                $home = getenv('HOME') . '/.composer';
            }
        }

        return $home;
    }

    /**
     * Get default directory where are downloaded repositories.
     *
     * @return string
     */
    protected function getRepositoryDirectory()
    {
        $dir = $this->getComposerHome().'/stone';

        // Ensure directory exists
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
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
     * Get the packages to install (ie. composer's requires).
     *
     * @param Composer $composer Composer model
     */
    protected function getPackagesToInstall(Composer $composer)
    {
        $manager = $composer->getRepositoryManager();
        $requires = $composer->getPackage()->getRequires();

        $packages = array();
        foreach ($requires as $link) {
            $target = $link->getTarget();

            // skip php extension dependencies
            if ('php' === $target || 0 === stripos($target, 'ext-')) {
                continue;
            }

            $package = $this->findDevPackage($manager, $target);

            if (null === $package) {
                throw new \RuntimeException('Can\'t find dev package for '.$target);
            }

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
     * Dump packages.json file used by composer repository.
     *
     * @param Composer $composer     The Composer model
     * @param array    $repositories The repositories to scan
     * @param string   $directory    The output directory
     */
    protected function dumpPackagesJson(Composer $composer, array $repositories, $directory)
    {
        $repoJson = new JsonFile($directory.'/packages.json');
        $manager = $composer->getRepositoryManager();

        $packages = array();
        foreach ($repositories as $config) {
            $repository = $manager->createRepository($config['type'], $config);

            foreach ($repository->getPackages() as $package) {
                // skip aliases
                if ($package instanceof AliasPackage) {
                    continue;
                }

                $packages[] = $package;
            }
        }

        $repo = array('packages' => array());
        $dumper = new ArrayDumper();
        foreach ($packages as $package) {
            $name = $package->getPrettyName();
            $version = $package->getPrettyVersion();

            $repo['packages'][$name][$version] = $dumper->dump($package);
        }

        // Keep previous packages information
        if ($repoJson->exists()) {
            $data = $repoJson->read();
            foreach ($data['packages'] as $name => $info) {
                if (!isset($repo['packages'][$name])) {
                    $repo['packages'][$name] = $info;
                }
            }
        }

        $repoJson->write($repo);
    }
}
