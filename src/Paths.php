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

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

/**
 * Class Paths.
 *
 * This static class generates and distributes all the paths used by PHP Composter.
 *
 * @since   0.1.0
 *
 * @package PHPComposter\PHPComposter
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Paths
{

    //const ACTIONS_FOLDER      = 'actions/';
    const COMPOSTER_FOLDER    = 'php-composter/';
    const COMPOSTER_PATH      = 'vendor/php-composter/php-composter/';
    const CONFIG              = 'config.php';
    const EXECUTABLE          = 'general-hook';
    const GIT_FOLDER          = '.git/';
    const HOOKS_FOLDER        = 'hooks/';

    /**
     * @var string path to root of package
     *
     * * @since 0.3.0
     */
    protected $pathRoot = '';

    /**
     * Internal storage of all required paths.
     *
     * @var array
     *
     * @since 0.1.0
     */
    protected $paths = array();

    /**
     * Instance of the Filesystem class
     *
     * @var Filesystem
     *
     * * @since 0.3.0
     */
    protected $filesystem;

    /**
     * Instance of the Composer class
     *
     * @var Composer
     *
     * * @since 0.3.0
     */
    protected $composer;

    /**
     * Paths constructor.
     *
     * @param \Composer\Composer $composer
     * @param string $pathRoot path to root of package
     */
    public function __construct(Composer $composer, $pathRoot = '')
    {
        if (empty($pathRoot)) {
            $pathRoot = getcwd();
        } else {
            $pathRoot = getcwd() . DIRECTORY_SEPARATOR . $pathRoot;
        }
        $this->pathRoot = $pathRoot;
        $this->composer = $composer;
        $this->filesystem = new Filesystem();
    }

    /**
     * Get a specific path by key.
     *
     * @since 0.1.0
     *
     * @param string $key Key of the path to retrieve.
     *
     * @return string Path associated with the key. Empty string if not found.
     */
    public function getPath($key)
    {
        if (empty($this->paths)) {
            $this->initPaths();
        }

        if (array_key_exists($key, $this->paths)) {
            return $this->paths[$key];
        }

        return '';
    }

    /**
     * Get package absolute install path
     *
     * @param $package
     * @return string
     */
    protected function getPackageInstallPath($package)
    {
        $installPath = $this->composer->getInstallationManager()->getInstallPath($package);
        if (!$this->filesystem->isAbsolutePath($installPath)) {
            $installPath = getcwd() . DIRECTORY_SEPARATOR . $installPath;
        }
        return $installPath;
    }

    /**
     * Get package path to hooks config file.
     *
     * @param PackageInterface $package
     * @return string
     */
    public function getHookConfigPath(PackageInterface $package)
    {
        if ($this->composer->getPackage() === $package) {
            return $this->getPath('git_config');
        } else {
            $installPath = $this->getPackageInstallPath($package);
            return $installPath . DIRECTORY_SEPARATOR . self::GIT_FOLDER . self::HOOKS_FOLDER . self::CONFIG;
        }
    }

    /**
     * Get package path to git hooks directory.
     *
     * @param PackageInterface $package
     * @return string
     */
    public function getHookPath(PackageInterface $package)
    {
        if ($this->composer->getPackage() === $package) {
            return $this->getPath('root_hooks');
        } else {
            $installPath = $this->getPackageInstallPath($package);
            return $installPath . DIRECTORY_SEPARATOR . self::GIT_FOLDER . self::HOOKS_FOLDER;
        }
    }

    /**
     * Get path to hooks config to include in hook script
     *
     * @return string
     */
    public static function getHookConfigPathForInclude()
    {
        return getcwd() . DIRECTORY_SEPARATOR . self::GIT_FOLDER . self::HOOKS_FOLDER . self::CONFIG;
    }

    /**
     * Initialize the paths.
     *
     * @since 0.1.0
     */
    protected function initPaths()
    {
        /** @var \Composer\Config $config */
        $config = $this->composer->getConfig();
        $vendorDir = $config->get('vendor-dir');
        $this->paths['pwd']              = $this->pathRoot . DIRECTORY_SEPARATOR;
        $this->paths['root_git']         = $this->paths['pwd'] . self::GIT_FOLDER;
        $this->paths['root_hooks']       = $this->paths['root_git'] . self::HOOKS_FOLDER;
        $this->paths['vendor']           = $vendorDir;
        $this->paths['vendor_composter'] = $this->paths['pwd'] . self::COMPOSTER_PATH;
        $this->paths['git_composter']    = $this->paths['root_git'] . self::COMPOSTER_FOLDER;
        $this->paths['git_script']       = $this->paths['vendor_composter'] . self::HOOKS_FOLDER . self::EXECUTABLE;
        //$this->paths['actions']          = $this->paths['git_composter'] . self::ACTIONS_FOLDER;
        $this->paths['git_config']       = $this->paths['root_hooks'] . self::CONFIG;
    }

}
