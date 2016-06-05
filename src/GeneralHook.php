<?php
/**
 * Git General Hooks Management through Composer
 *
 * @package php-composter/php-composter
 */

namespace PHPComposter\PHPComposter;

class GeneralHook {

    /**
     * Run hook.
     *
     * @param array $argv arguments from command line which run hook
     */
    public function run($argv)
    {
        $hookName = basename($argv[0]);
        $root = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..';

        // Read the configuration file.
        $configPath = Paths::getHookConfigPathForInclude();
        $config = include $configPath;

        $this->runHooks($hookName, $root, $config);
    }

    /**
     * Run hooks $hookName for $root directory according to $config.
     *
     * @param $hookName
     * @param $root
     * @param $config
     * @throws RuntimeException
     */
    public function runHooks($hookName, $root, $config)
    {
        // Iterate over hookName methods.
        if (array_key_exists($hookName, $config)) {

            $actions = $config[$hookName];

            // Sort by priority.
            ksort($actions);

            // Launch each method.
            foreach ($actions as $calls) {
                foreach ($calls as $call) {

                    // Make sure we could parse the call correctly.
                    $array = explode('::', $call);
                    if (count($array) !== 2) {
                        throw new RuntimeException(
                            sprintf(
                                _('Configuration error in PHP Composter data, could not parse method "%1$s"'),
                                $call
                            )
                        );
                    }
                    list($class, $method) = $array;

                    // Instantiate a new action object and call its method.
                    $object = new $class($hookName, $root);
                    $object->init();
                    $object->$method();
                    $object->shutdown();
                    unset($object);
                }
            }
        }
    }
}