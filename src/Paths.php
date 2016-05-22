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

    const ACTIONS_FOLDER      = 'actions/';
    const BIN_FOLDER          = 'bin/';
    const COMPOSTER_FOLDER    = 'php-composter/';
    const COMPOSTER_PATH      = 'vendor/php-composter/php-composter/';
    const CONFIG              = 'config.php';
    const EXECUTABLE          = 'php-composter';
    const GIT_FOLDER          = '.git/';
    const GIT_TEMPLATE_FOLDER = 'includes/';
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
     * Paths constructor.
     *
     * @param string $pathRoot path to root of package
     */
    public function __construct($pathRoot = '')
    {
        if (empty($pathRoot)) {
            $pathRoot = getcwd();
        }
        $this->pathRoot = $pathRoot;
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
     * Initialize the paths.
     *
     * @since 0.1.0
     */
    protected function initPaths()
    {
        $this->paths['pwd']              = $this->pathRoot . DIRECTORY_SEPARATOR;
        $this->paths['root_git']         = $this->paths['pwd'] . self::GIT_FOLDER;
        $this->paths['root_hooks']       = $this->paths['root_git'] . self::HOOKS_FOLDER;
        $this->paths['vendor_composter'] = $this->paths['pwd'] . self::COMPOSTER_PATH;
        $this->paths['git_composter']    = $this->paths['root_git'] . self::COMPOSTER_FOLDER;
        $this->paths['git_script']       = $this->paths['vendor_composter'] . self::BIN_FOLDER . self::EXECUTABLE;
        $this->paths['actions']          = $this->paths['git_composter'] . self::ACTIONS_FOLDER;
        $this->paths['git_template']     = $this->paths['vendor_composter'] . self::GIT_TEMPLATE_FOLDER;
        $this->paths['root_template']    = $this->paths['git_composter'] . self::GIT_TEMPLATE_FOLDER;
        $this->paths['git_config']       = $this->paths['git_composter'] . self::CONFIG;
    }

}
