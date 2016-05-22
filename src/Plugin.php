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
        $filesystem = new Filesystem();
        $path       = static::$paths->getPath('git_composter');
        $filesystem->ensureDirectoryExists($path);
        file_put_contents(static::$paths->getPath('git_config'), static::getConfig());
    }

    /**
     * Generate the config file.
     *
     * @since 0.1.0
     *
     * @return string Generated Config file.
     */
    public static function getConfig()
    {
        $output = '<?php' . PHP_EOL;
        $output .= '// PHP Composter configuration file.' . PHP_EOL;
        $output .= '// Do not edit, this file is generated automatically.' . PHP_EOL;
        $output .= '// Timestamp: ' . date('Y/m/d H:m:s') . PHP_EOL;
        $output .= PHP_EOL;
        $output .= 'return array(' . PHP_EOL;

        foreach (static::getGitHookNames() as $hook) {
            $entries = HookConfig::getEntries($hook);
            $output .= '    \'' . $hook . '\' => array(' . PHP_EOL;
            foreach ($entries as $priority => $methods) {
                $output .= '        ' . $priority . ' => array(' . PHP_EOL;
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
    protected static function getGitHookNames()
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
        static::$io = $io;
        if (static::$io->isVerbose()) {
            static::$io->write(_('Activating PHP Composter plugin'), true);
        }

        static::$paths = $this->initPaths($composer);

        $installer = new Installer(static::$io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);

        $filesystem = new Filesystem();
        $this->cleanUp($filesystem);
        $this->linkBootstrapFiles($filesystem);
        $this->createGitHooks($filesystem);
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
            return new Paths($extra['git-repository-root-path']);
        } else {
            return new Paths(getcwd());
        }
    }

    /**
     * Clean up previous installation.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     */
    protected function cleanUp(Filesystem $filesystem)
    {
        $composterPath = static::$paths->getPath('git_composter');
        if (static::$io->isVeryVerbose()) {
            static::$io->write(sprintf(
                _('Removing previous PHP Composter actions at %1$s'),
                $composterPath
            ), true);
        }
        $filesystem->emptyDirectory($composterPath, true);

        $composterTemplate = static::$paths->getPath('root_template');
        if (static::$io->isVeryVerbose()) {
            static::$io->write(sprintf(
                _('Removing previous PHP Composter code at %1$s'),
                $composterTemplate
            ), true);
        }
        $filesystem->emptyDirectory($composterTemplate, true);
    }

    /**
     * Symlink the bootstrapping code into the .git folder.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     */
    protected function linkBootstrapFiles(Filesystem $filesystem)
    {
        $rootTemplate      = static::$paths->getPath('root_template');
        $composterTemplate = static::$paths->getPath('git_template');

        $files = array(
            'bootstrap.php',
        );

        foreach ($files as $file) {
            if (static::$io->isVeryVerbose()) {
                static::$io->write(sprintf(
                    _('Symlinking %1$s to %2$s'),
                    $rootTemplate . $file,
                    $composterTemplate . $file
                ));
            }
            $filesystem->relativeSymlink($composterTemplate . $file, $rootTemplate . $file);
        }
    }

    /**
     * Symlink each known Git hook to the PHP Composter bootstrapping script.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     */
    protected function createGitHooks(Filesystem $filesystem)
    {

        $hooksPath     = static::$paths->getPath('root_hooks');
        $gitScriptPath = static::$paths->getPath('git_script');

        foreach ($this->getGitHookNames() as $githook) {
            $hookPath = $hooksPath . $githook;
            if (static::$io->isDebug()) {
                static::$io->write(sprintf(
                    _('Symlinking %1$s to %2$s'),
                    $hookPath,
                    $gitScriptPath
                ));
            }
            echo "getcwd = " . getcwd();
            $filesystem->relativeSymlink($gitScriptPath, $hookPath);
        }
    }
}
