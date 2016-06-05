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
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Package\PackageInterface;

/**
 * Class Plugin.
 *
 * This main class activates and sets up the PHP Composter system within the package's .git folder.
 *
 * @since   0.1.0
 *
 * @package PHPComposter\PHPComposter
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * Instance of the IO interface.
     *
     * @var IOInterface
     *
     * @since 0.1.0
     */
    protected static $io;

    /**
     * Instance of the Paths class
     *
     * @var Paths
     *
     * * @since 0.3.0
     */
    protected static $paths;

    /**
     * Instance of the Composer class
     *
     * @var Composer
     *
     * * @since 0.3.0
     */
    protected static $composer;

    /**
     * Instance of the Installer class
     *
     * @var Installer
     *
     * * @since 0.3.0
     */
    protected static $installer;

    /**
     * Instance of the Plugin class
     * @var Plugin
     */
    protected static $plugin;

    /**
     * Get the event subscriber configuration for this plugin.
     *
     * @return array<string,string> The events to listen to, and their associated handlers.
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => 'persistConfig',
            ScriptEvents::POST_UPDATE_CMD  => 'persistConfig',
        );
    }

    /**
     * Persist the stored configuration.
     *
     * @since 0.1.0
     *
     * @param Event $event Event that was triggered.
     */
    public static function persistConfig(Event $event)
    {
        $composer = $event->getComposer();
        static::$installer->configurePackageHooks($composer->getPackage());

        static::$plugin->createPackageHook($composer, $composer->getPackage());

        $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        foreach ($packages as $package)
        {
            /** @var PackageInterface $package */
            static::$plugin->createPackageHook($composer, $package);
        }
    }

    /**
     * Create git hooks for package.
     *
     * @param Composer $composer
     * @param PackageInterface $package
     */
    protected function createPackageHook(Composer $composer, PackageInterface $package)
    {
        if ($this->isPackageUnderGit($composer, $package)) {
            $filesystem = new Filesystem();
            $this->cleanUp($filesystem, $package);
        }

        $packageType = $this->getPackageType($composer, $package);
        if (!empty($packageType))
        {
            $this->createGitHooks($filesystem, $package);

            $configPath = static::$paths->getHookConfigPath($package);
            static::$io->write(
                sprintf(_('Create hooks config for package "%1$s" type %2$s'), $package->getPrettyName(), $packageType),
                true,
                IOInterface::VERBOSE
            );
            file_put_contents($configPath, $this->getConfig($packageType));
        }
    }

    /**
     * Is package under git and need code checker.
     *
     * @param Composer $composer
     * @param PackageInterface $package
     * @return bool
     */
    protected function isPackageUnderGit(Composer $composer, PackageInterface $package)
    {
        if ($composer->getPackage() === $package) {
            return true;
        }
        $installPath = $composer->getInstallationManager()->getInstallPath($package);
        return is_file($installPath . DIRECTORY_SEPARATOR. Paths::GIT_FOLDER . 'config');
    }

    /**
     * Get package type.
     *
     * @param Composer $composer
     * @param PackageInterface $package
     * @return string package type. Value '' means that package not need code cheсker
     */
    protected function getPackageType(Composer $composer, PackageInterface $package)
    {
        $packageType = '';
        $composerExtra = $composer->getPackage()->getExtra();
        $extra = $package->getExtra();
        if (static::isPackageUnderGit($composer, $package)) 
        {
            $packageType = Installer::PACKAGE_TYPE_DEFAULT;
        }
        if(isset($extra['code-checker-type'])) {
            $packageType = $extra['code-checker-type'];
        }
        $packageName = $package->getPrettyName();
        if (isset($composerExtra['code-checker-types']) && isset($composerExtra['code-checker-types'][$packageName]))
        {
            $packageType = $composerExtra['code-checker-types'][$packageName];
        }
        return $packageType;
    }

    /**
     * Generate the config file.
     *
     * @since 0.1.0
     * @param string $packageType
     * @return string Generated Config file.
     */
    public function getConfig($packageType)
    {
        $output = '<?php' . PHP_EOL;
        $output .= '// PHP Composter configuration file.' . PHP_EOL;
        $output .= '// Do not edit, this file is generated automatically.' . PHP_EOL;
        $output .= '// Timestamp: ' . date('Y/m/d H:m:s') . PHP_EOL;
        $output .= PHP_EOL;
        $output .= 'return array(' . PHP_EOL;

        foreach ($this->getGitHookNames() as $hook) {
            $entries = HookConfig::getEntries($hook);
            $output .= '    \'' . $hook . '\' => array(' . PHP_EOL;
            foreach ($entries as $priority => $methodsByType) {
                $output .= '        \'' . $priority . '\' => array(' . PHP_EOL;
                
                $methods = array();
                if (isset($methodsByType[$packageType])) {
                    $methods = $methodsByType[$packageType];
                } else if (isset($methodsByType[Installer::PACKAGE_TYPE_DEFAULT])) {
                    $methods = $methodsByType[Installer::PACKAGE_TYPE_DEFAULT];
                }

                foreach ($methods as $method) {
                    $output .= '            \'' . $method . '\',' . PHP_EOL;
                }
                $output .= '        ),' . PHP_EOL;
            }
            $output .= '    ),' . PHP_EOL;
        }

        $output .= ');' . PHP_EOL;

        return $output;
    }

    /**
     * Get an array with all known Git hooks.
     *
     * @since 0.1.0
     *
     * @return array Array of strings.
     */
    protected function getGitHookNames()
    {
        return array(
            'applypatch-msg',
            'pre-applypatch',
            'post-applypatch',
            'pre-commit',
            'prepare-commit-msg',
            'commit-msg',
            'post-commit',
            'pre-rebase',
            'post-checkout',
            'post-merge',
            'post-update',
            'pre-auto-gc',
            'post-rewrite',
            'pre-push',
        );
    }

    /**
     * Activate the Composer plugin.
     *
     * @since 0.1.0
     *
     * @param Composer    $composer Reference to the Composer instance.
     * @param IOInterface $io       Reference to the IO interface.
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        static::$plugin = $this;
        static::$composer = $composer;
        static::$io = $io;
        if (static::$io->isVerbose()) {
            static::$io->write(_('Activating PHP Composter plugin'), true);
        }

        static::$paths = $this->initPaths($composer);

        static::$installer = new Installer(static::$io, $composer, 'library', null, null, static::$paths);
        $composer->getInstallationManager()->addInstaller(static::$installer);
    }

    /**
     * Initialization Paths for git hooks.
     * Use the parameter extra.git-repository-root-path to specify the root package path.
     * The default is the directory where the file composer.json.
     *
     * @param Composer $composer Reference to the Composer instance.
     * @return Paths Reference to the Paths instance
     */
    protected function initPaths(Composer $composer)
    {
        $extra = $composer->getPackage()->getExtra();
        if(!empty($extra['git-repository-root-path'])) {
            return new Paths($composer, $extra['git-repository-root-path']);
        } else {
            return new Paths($composer, getcwd());
        }
    }

    /**
     * Clean up previous installation.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     * @param PackageInterface $package
     */
    protected function cleanUp(Filesystem $filesystem, PackageInterface $package)
    {
        $hooksPath     = static::$paths->getHookPath($package);
        if (is_dir($hooksPath) && !$filesystem->isDirEmpty($hooksPath)) {
            static::$io->write(
                sprintf(_('Removing previous PHP Composter actions for package %1$s'), $package->getPrettyName()),
                true,
                IOInterface::VERBOSE
            );
            $filesystem->emptyDirectory($hooksPath, false);
        }
    }

    /**
     * Create git hook php-script with correct path to vendor/autoload.php.
     *
     * @param $gitScriptPath path to template git hook
     * @param $hookPath path to git hook file witch need to create
     */
    protected  function copyGitHookFile(Filesystem $filesystem, $gitScriptPath, $hookPath)
    {
        $vendorDir = static::$paths->getPath('vendor');
        $relativePath = $filesystem->findShortestPath($hookPath, $vendorDir);

        copy($gitScriptPath, $hookPath);
        file_put_contents($hookPath, str_replace('vendor', $relativePath, file_get_contents($hookPath)));
        chmod($hookPath, 0755);
    }

    /**
     * Symlink each known Git hook to the PHP Composter bootstrapping script.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     * @param PackageInterface $package
     */
    protected function createGitHooks(Filesystem $filesystem, PackageInterface $package)
    {

        $hooksPath     = static::$paths->getHookPath($package);
        $gitScriptPath = static::$paths->getPath('git_script');

        $filesystem->ensureDirectoryExists($hooksPath);

        foreach ($this->getGitHookNames() as $githook) {
            $hookPath = $hooksPath . $githook;
            if (static::$io->isDebug()) {
                static::$io->write(sprintf(
                    _('Copy %1$s to %2$s'),
                    $hookPath,
                    $gitScriptPath
                ));
            }
            $this->copyGitHookFile($filesystem, $gitScriptPath, $hookPath);
        }
    }
}
