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

/**
 * Abstract Class BaseAction.
 *
 * This class should be extended by each new action.
 *
 * @since   0.1.3
 *
 * @package PHPComposter\PHPComposter
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class BaseAction
{

    /**
     * Root folder of the package.
     *
     * @var string
     *
     * @since 0.1.3
     */
    protected $root;

    /**
     * Hook that was triggered.
     *
     * @var string
     *
     * @since 0.1.3
     */
    protected $hook;

    /**
     * Instantiate a BaseAction object.
     *
     * @since 0.1.3
     *
     * @param string $hook The name of the hook that was triggered.
     * @param string $root Absolute path to the root folder of the package.
     */
    public function __construct($hook, $root)
    {
        $this->root = $root;
        $this->hook = $hook;
    }

    /**
     * Initialize the action.
     *
     * @since 0.1.3
     */
    public function init()
    {
        // Do nothing. Can be overridden by extending classes.
    }

    /**
     * Shut the action down.
     *
     * @since 0.1.3
     */
    public function shutdown()
    {
        // Do nothing. Can be overridden by extending classes.
    }

    /**
     * Recursively iterate over folders and look for $pattern.
     *
     * @since 0.1.3
     *
     * @param string $pattern Pattern to look for.
     * @param int    $flags   Optional. Flags to PHP glob() function. Defaults to 0.
     *
     * @return mixed
     */
    protected function recursiveGlob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {

            // Avoid scanning vendor folder.
            if ($dir === $this->root . '/vendor') {
                continue;
            }

            $files = array_merge($files, $this->recursiveGlob($dir . '/' . basename($pattern), $flags));
        }

        return $files;
    }

    /**
     * Get the file sthat have been staged for the current commit.
     *
     * @since 0.1.3
     *
     * @var string $pattern Grep pattern to filter the staged files against.
     */
    protected function getStagedFiles($pattern)
    {
        $filter = empty($pattern) ? '' : ' | grep ' . $pattern;
        
        ob_start();
        $result = shell_exec('git diff-index --name-only --diff-filter=ACMR ' . $this->getAgainst() . $filter);
        ob_clean();

        $files = explode("\n", $result);

        $files = array_filter($files, function ($file) {
            return ! empty($file);
        });

        array_walk($files, function (&$file) {
            $file = $this->root . DIRECTORY_SEPARATOR . $file;
        });

        return $files;
    }

    /**
     * Get the tree object to check against.
     *
     * @since 0.1.3
     */
    protected function getAgainst()
    {
        ob_start();
        $result = shell_exec('git rev-parse --verify --quiet HEAD');
        ob_clean();
        // Check if we're on a semi-secret empty tree
        if ($result) {
            return 'HEAD';
        } else {
            // Initial commit: diff against an empty tree object
            return '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
        }
    }
}
