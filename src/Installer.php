<?php
/**
 * Git Hooks Management through Composer.
 *
 * @package   PHPComposter\PHPComposter
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      http://www.brightnucleus.com/
 * @copyright 2016 Alain Schlesser, Bright Nucleus
 */

namespace PHPComposter\PHPComposter;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\IO\IOInterface;
use Composer\Composer;
use Composer\Util\Filesystem;
use Composer\Installer\BinaryInstaller;
use InvalidArgumentException;

/**
 * Class Installer.
 *
 * The Installer class tells Composer where to install each package of type `php-composter-action`.
 *
 * @since   0.1.0
 *
 * @package PHPComposter\PHPComposter
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Installer extends LibraryInstaller
{

    const EXTRA_KEY = 'php-composter-hooks';
    const PREFIX    = 'php-composter-';
    const TYPE      = 'php-composter-action';
    const PACKAGE_TYPE_DEFAULT = 'default';

    /** @var Paths Reference to the Paths instance */
    protected $paths;

    /**
     * Installer constructor.
     *
     * @see LibraryInstaller::__construct
     * @param IOInterface $io
     * @param Composer $composer
     * @param string $type
     * @param Filesystem|null $filesystem
     * @param BinaryInstaller|null $binaryInstaller
     *
     * @param Paths|null $paths Reference to the Paths instance
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library',
                                Filesystem $filesystem = null, BinaryInstaller $binaryInstaller = null,
                                Paths $paths = null)
    {
        $this->paths = $paths ? $paths : new Paths();
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);
    }

    /**
     * Get the installation path of the package.
     *
     * @since 0.1.0
     *
     * @param PackageInterface $package The package to install.
     *
     * @return string Relative installation path.
     * @throws InvalidArgumentException If the package name does not match the required pattern.
     */
    /*
    public function getInstallPath(PackageInterface $package)
    {
        return $this->paths->getPath('actions') . $this->getSuffix($package);
    }
    */

    /**
     * Install the package.
     *
     * @since 0.1.0
     *
     * @param InstalledRepositoryInterface $repo    The repository from where the package was fetched.
     * @param PackageInterface             $package The package to install.
     *
     * @throws InvalidArgumentException If the package name does not match the required pattern.
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->io->write(sprintf(_('Install package "%1$s"'), $package->getPrettyName()), true, IOInterface::VERBOSE);
        /*
        $path = $this->getInstallPath($package);
        if ($this->io->isVerbose()) {
            $this->io->write(sprintf(
                _('Symlinking PHP Composter action %1$s'),
                $path
            ), true);
        }
        */

        parent::install($repo, $package);
        $this->configurePackageHooks($package);
    }

    /**
     * Configure package hooks
     *
     * @param PackageInterface $package
     */
    public function configurePackageHooks(PackageInterface $package)
    {
        $this->io->write(sprintf(_('Configure package "%1$s"'), $package->getPrettyName()), true, IOInterface::VERBOSE);

        foreach ($this->getHooks($package) as $prioritizedHook => $methods) {
            $array = explode('.', $prioritizedHook);
            if (count($array) > 1) {
                list($priority, $hook) = $array;
            } else {
                $hook     = $array[0];
                $priority = 10;
            }

            if (!is_array($methods)) {
                $methods = array(Installer::PACKAGE_TYPE_DEFAULT => $methods);
            }
            foreach($methods as $packageType => $method) {
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf(
                        _('Adding method "%1$s" to hook "%2$s" with priority %3$s for package typr %4$s'),
                        $method,
                        $hook,
                        $priority,
                        $packageType
                    ), true);
                }
                HookConfig::addEntry($hook, $packageType, $method, $priority);
            }
        }
    }

    /**
     * Check whether the package is already installed.
     *
     * @todo  This should be made smarter to not always reinstall from scratch.
     *
     * @since 0.1.0
     *
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Always reinstall all PHP Composter actions.
        return false;
    }

    /**
     * Whether the installer supports a given package type.
     *
     * @since 0.1.0
     *
     * @param $packageType
     *
     * @return bool
     */
    public function supports($packageType)
    {
        return self::TYPE === $packageType;
    }

    /**
     * Get the package name suffix.
     *
     * @since 0.1.0
     *
     * @param PackageInterface $package Package to inspect.
     *
     * @return string Suffix of the package name.
     * @throws InvalidArgumentException If the package name does not match the required pattern.
     */
    /*
    protected function getSuffix(PackageInterface $package)
    {
        $result = (array)explode('/', $package->getPrettyName());
        if (count($result) !== 2) {
            throw new InvalidArgumentException(sprintf(
                _('Unable to install PHP Composter action, could '
                  . 'not extract action name from package "%1$s"'),
                $package->getPrettyName()
            ));
        }

        list($vendor, $name) = $result;
        $prefixLength = mb_strlen(self::PREFIX);
        $prefix       = mb_substr($name, 0, $prefixLength);

        if (self::PREFIX !== $prefix) {
            throw new InvalidArgumentException(sprintf(
                _('Unable to install PHP Composter action, actions '
                  . 'should always start their package name with '
                  . '"<vendor>/%1$s"'),
                self::PREFIX
            ));
        }

        return mb_substr($name, $prefixLength);
    }
    */

    /**
     * Get the hooks configuration from package extra data.
     *
     * @since 0.2.0
     *
     * @param PackageInterface $package Package to inspect.
     *
     * @return array Array of prioritized hooks.
     */
    protected function getHooks(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if ( ! array_key_exists(self::EXTRA_KEY, $extra)) {
            return array();
        }

        return $extra[self::EXTRA_KEY];
    }
}
