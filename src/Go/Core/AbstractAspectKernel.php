<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use Go\Instrument\ClassLoading\UniversalClassLoader;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\AopProxyTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;

use TokenReflection;

/**
 * Abstract aspect kernel is used to prepare an application to work with aspects.
 *
 * Realization of this class should return the path for application loader, so when the kernel has finished its work,
 * it will pass the control to the application loader.
 */
abstract class AbstractAspectKernel
{
    /**
     * Kernel options
     *
     * @var array
     */
    protected $options = array();

    /**
     * Protected constructor is used to prevent direct creation, but allows customization if needed
     */
    protected function __construct() {}

    /**
     * Returns the single instance of kernel
     *
     * @return AbstractAspectKernel
     */
    public static function getInstance()
    {
        static $instance = null;
        if (!$instance) {
            $instance = new static();
        }
        return $instance;
    }

    /**
     * Init the kernel and make adjustments
     *
     * @param array $options Associative array of options for kernel
     */
    public function init(array $options = array())
    {
        $this->options = array_merge_recursive($this->options, $options);

        $this->initLibraryLoader();

        SourceTransformingLoader::registerFilter();
        foreach ($this->registerTransformers() as $sourceTransformer) {
            SourceTransformingLoader::addTransformer($sourceTransformer);
        }
        SourceTransformingLoader::load($this->getApplicationLoaderPath());
    }

    /**
     * Returns the path to the application autoloader file, typical autoload.php
     *
     * @return string
     */
    abstract protected function getApplicationLoaderPath();

    /**
     * Returns list of source transformers, that will be applied to the PHP source
     *
     * @return array|SourceTransformer[]
     */
    protected function registerTransformers()
    {
        return array(
            new FilterInjectorTransformer(__DIR__, __DIR__, SourceTransformingLoader::getId()),
            new AopProxyTransformer(
                new TokenReflection\Broker(
                    new TokenReflection\Broker\Backend\Memory()
                )
            ),
        );
    }

    /**
     * Init library autoloader.
     *
     * We cannot use any standard autoloaders in the application level because we will rewrite them on fly.
     * This will also reduce the time for library loading and prevent cyclic errors when source is loaded.
     */
    protected function initLibraryLoader()
    {
        // Default autoload paths for library
        $autoloadOptions = array(
            'autoload' => array(
                'Go'              => realpath(__DIR__ . '/../../'),
                'TokenReflection' => realpath(__DIR__ . '/../../../vendor/andrewsville/php-token-reflection/')
            )
        );
        $options = array_merge_recursive($autoloadOptions, $this->options);

        /**
         * Separate class loader for core should be used to load classes,
         * so UniversalClassLoader is moved to the custom namespace
         */
        require_once __DIR__ . '../Instrument/ClassLoading/UniversalClassLoader.php';

        $loader = new UniversalClassLoader();
        $loader->registerNamespaces($options['autoload']);
        $loader->register();
    }
}
