<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Loader
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */


/**
 * @category   Zend
 * @package    Zend_Loader
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @group      Zend_Loader
 */
class Zend_Loader_Autoloader_ResourceTest extends PHPUnit\Framework\TestCase
{
    protected $loaders;
    protected $includePath;
    protected $autoloader;
    protected $error;
    protected $loader;

    public function setUp(): void
    {
        // Store original autoloaders
        $this->loaders = spl_autoload_functions();
        if (!is_array($this->loaders)) {
            // spl_autoload_functions does not return empty array when no
            // autoloaders registered...
            $this->loaders = array();
        }

        // Store original include_path
        $this->includePath = get_include_path();

        Zend_Loader_Autoloader::resetInstance();
        $this->autoloader = Zend_Loader_Autoloader::getInstance();

        // initialize 'error' member for tests that utilize error handling
        $this->error = null;

        $this->loader = new Zend_Loader_Autoloader_Resource(array(
            'namespace' => 'FooBar',
            'basePath'  => realpath(dirname(__FILE__) . '/_files'),
        ));
    }

    public function tearDown(): void
    {
        // Restore original autoloaders
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        foreach ($this->loaders as $loader) {
            spl_autoload_register($loader);
        }

        // Retore original include_path
        set_include_path($this->includePath);

        // Reset autoloader instance so it doesn't affect other tests
        Zend_Loader_Autoloader::resetInstance();
    }

    public function testAutoloaderInstantiationShouldRaiseExceptionWithoutNamespace()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $loader = new Zend_Loader_Autoloader_Resource(array('basePath' => dirname(__FILE__)));
    }

    public function testAutoloaderInstantiationShouldRaiseExceptionWithoutBasePath()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $loader = new Zend_Loader_Autoloader_Resource(array('namespace' => 'Foo'));
    }

    public function testAutoloaderInstantiationShouldRaiseExceptionWhenInvalidOptionsTypeProvided()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $loader = new Zend_Loader_Autoloader_Resource('foo');
    }

    public function testAutoloaderConstructorShouldAcceptZendConfigObject()
    {
        $config = new Zend_Config(array('namespace' => 'Foo', 'basePath' => dirname(__FILE__)));
        $loader = new Zend_Loader_Autoloader_Resource($config);

        // This test was testing that no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testAutoloaderShouldAllowRetrievingNamespace()
    {
        $this->assertEquals('FooBar', $this->loader->getNamespace());
    }

    public function testAutoloaderShouldAllowRetrievingBasePath()
    {
        $this->assertEquals(realpath(dirname(__FILE__) . '/_files'), $this->loader->getBasePath());
    }

    public function testNoResourceTypesShouldBeRegisteredByDefault()
    {
        $resourceTypes = $this->loader->getResourceTypes();
        $this->assertIsArray($resourceTypes);
        $this->assertEmpty($resourceTypes);
    }

    public function testInitialResourceTypeDefinitionShouldRequireNamespace()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->addResourceType('foo', 'foo');
    }

    public function testPassingNonStringPathWhenAddingResourceTypeShouldRaiseAnException()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->addResourceType('foo', array('foo'), 'Foo');
    }

    public function testAutoloaderShouldAllowAddingArbitraryResourceTypes()
    {
        $this->loader->addResourceType('models', 'models', 'Model');
        $resources = $this->loader->getResourceTypes();
        $this->assertArrayHasKey('models', $resources);
        $this->assertEquals($this->loader->getNamespace() . '_Model', $resources['models']['namespace']);
        $this->assertStringContainsString('/models', $resources['models']['path']);
    }

    public function testAutoloaderShouldAllowAddingResettingResourcePaths()
    {
        $this->loader->addResourceType('models', 'models', 'Model');
        $this->loader->addResourceType('models', 'apis');
        $resources = $this->loader->getResourceTypes();
        $this->assertStringNotContainsString('/models', $resources['models']['path']);
        $this->assertStringContainsString('/apis', $resources['models']['path']);
    }

    public function testAutoloaderShouldSupportAddingMultipleResourceTypesAtOnce()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
            'form'  => array('path' => 'forms', 'namespace' => 'Form'),
        ));
        $resources = $this->loader->getResourceTypes();
        $this->assertContains('model', array_keys($resources));
        $this->assertContains('form', array_keys($resources));
    }

    /**
     */
    public function testAddingMultipleResourceTypesShouldRaiseExceptionWhenReceivingNonArrayItem()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->addResourceTypes(array('foo' => 'bar'));
    }

    /**
     */
    public function testAddingMultipleResourceTypesShouldRaiseExceptionWhenMissingResourcePath()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->addResourceTypes(array('model' => array('namespace' => 'Model')));
    }

    public function testSetResourceTypesShouldOverwriteExistingResourceTypes()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
            'form'  => array('path' => 'forms', 'namespace' => 'Form'),
        ));

        $this->loader->setResourceTypes(array(
            'view'   => array('path' => 'views', 'namespace' => 'View'),
            'layout' => array('path' => 'layouts', 'namespace' => 'Layout'),
        ));

        $resources = $this->loader->getResourceTypes();
        $this->assertNotContains('model', array_keys($resources));
        $this->assertNotContains('form', array_keys($resources));
        $this->assertContains('view', array_keys($resources));
        $this->assertContains('layout', array_keys($resources));
    }

    public function testHasResourceTypeShouldReturnFalseWhenTypeNotDefined()
    {
        $this->assertFalse($this->loader->hasResourceType('model'));
    }

    public function testHasResourceTypeShouldReturnTrueWhenTypeIsDefined()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $this->assertTrue($this->loader->hasResourceType('model'));
    }

    public function testRemoveResourceTypeShouldRemoveResourceFromList()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
            'form'  => array('path' => 'forms', 'namespace' => 'Form'),
        ));
        $this->loader->removeResourceType('form');

        $resources = $this->loader->getResourceTypes();
        $this->assertContains('model', array_keys($resources));
        $this->assertNotContains('form', array_keys($resources));
    }

    public function testAutoloaderShouldAllowSettingDefaultResourceType()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $this->loader->setDefaultResourceType('model');
        $this->assertEquals('model', $this->loader->getDefaultResourceType());
    }

    public function testSettingDefaultResourceTypeToUndefinedTypeShouldHaveNoEffect()
    {
        $this->loader->setDefaultResourceType('model');
        $this->assertNull($this->loader->getDefaultResourceType());
    }

    public function testLoadShouldRaiseExceptionWhenNotTypePassedAndNoDefaultSpecified()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->load('Foo');
    }

    public function testLoadShouldRaiseExceptionWhenResourceTypeDoesNotExist()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->load('Foo', 'model');
    }

    public function testLoadShouldReturnObjectOfExpectedClass()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $object = $this->loader->load('ZendLoaderAutoloaderResourceClass', 'model');
        $this->assertInstanceOf(FooBar_Model_ZendLoaderAutoloaderResourceClass::class, $object);
    }

    public function testSuccessiveCallsToLoadSameResourceShouldReturnSameObject()
    {
        $this->loader->addResourceTypes(array(
            'form' => array('path' => 'forms', 'namespace' => 'Form'),
        ));
        $object = $this->loader->load('ZendLoaderAutoloaderResourceClass', 'form');
        $this->assertInstanceOf(FooBar_Form_ZendLoaderAutoloaderResourceClass::class, $object);
        $test = $this->loader->load('ZendLoaderAutoloaderResourceClass', 'form');
        $this->assertSame($object, $test);
    }

    public function testAutoloadShouldAllowEmptyNamespacing()
    {
        $loader = new Zend_Loader_Autoloader_Resource(array(
            'namespace' => '',
            'basePath'  => realpath(dirname(__FILE__) . '/_files'),
        ));
        $loader->addResourceTypes(array(
            'service' => array('path' => 'services', 'namespace' => 'Service'),
        ));
        $test = $loader->load('ZendLoaderAutoloaderResourceClass', 'service');
        $this->assertInstanceOf(Service_ZendLoaderAutoloaderResourceClass::class, $test);
    }

    public function testPassingClassOfDifferentNamespaceToAutoloadShouldReturnFalse()
    {
        $this->assertFalse($this->loader->autoload('Foo_Bar_Baz'));
    }

    public function testPassingClassWithoutBothComponentAndClassSegmentsToAutoloadShouldReturnFalse()
    {
        $this->assertFalse($this->loader->autoload('FooBar_Baz'));
    }

    public function testPassingClassWithUnmatchedResourceTypeToAutoloadShouldReturnFalse()
    {
        $this->assertFalse($this->loader->autoload('FooBar_Baz_Bat'));
    }

    public function testMethodOverloadingShouldRaiseExceptionForNonGetterMethodCalls()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->lalalalala();
    }

    public function testMethodOverloadingShouldRaiseExceptionWhenRequestedResourceDoesNotExist()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->getModel('Foo');
    }

    public function testMethodOverloadingShouldRaiseExceptionWhenNoArgumentPassed()
    {
        $this->expectException(\Zend_Loader_Exception::class);

        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $this->loader->getModel();
    }

    public function testMethodOverloadingShouldReturnObjectOfExpectedType()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $test = $this->loader->getModel('ZendLoaderAutoloaderResourceMethodOverloading');
        $this->assertInstanceOf(FooBar_Model_ZendLoaderAutoloaderResourceMethodOverloading::class, $test);
    }

    /**
     * @group ZF-7473
     */
    public function testAutoloaderShouldReceiveNamespaceWithTrailingUnderscore()
    {
        $al      = Zend_Loader_Autoloader::getInstance();
        $loaders = $al->getNamespaceAutoloaders('FooBar');
        $this->assertEmpty($loaders);
        $loaders = $al->getNamespaceAutoloaders('FooBar_');
        $this->assertNotEmpty($loaders);
        $loader = array_shift($loaders);
        $this->assertSame($this->loader, $loader);
    }

    /**
     * @group ZF-7501
     */
    public function testAutoloaderShouldTrimResourceTypePathsForTrailingPathSeparator()
    {
        $this->loader->addResourceType('models', 'models/', 'Model');
        $resources = $this->loader->getResourceTypes();
        $this->assertEquals($this->loader->getBasePath() . '/models', $resources['models']['path']);
    }

    /**
     * @group ZF-6727
     */
    public function testAutoloaderResourceGetClassPath()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $path = $this->loader->getClassPath('FooBar_Model_Class_Model');
        // if true we have // in path
        $this->assertFalse(strpos($path, '//'));
    }

    /**
     * @group ZF-8364
     * @group ZF-6727
     */
    public function testAutoloaderResourceGetClassPathReturnFalse()
    {
        $this->loader->addResourceTypes(array(
            'model' => array('path' => 'models', 'namespace' => 'Model'),
        ));
        $path = $this->loader->autoload('Something_Totally_Wrong');
        $this->assertFalse($path);
    }

    /**
     * @group ZF-10836
     */
    public function testConstructorAcceptsNamespaceKeyInAnyOrder()
    {
        // namespace is after resourceTypes - fails in ZF 1.11.1
        $data = array(
            'basePath'      => 'path/to/some/directory',
            'resourceTypes' => array(
                'acl' => array(
                    'path'      => 'acls/',
                    'namespace' => 'Acl',
                )
            ),
            'namespace' => 'My'
        );
        $loader1 = new Zend_Loader_Autoloader_Resource($data);

        // namespace is defined before resourceTypes - always worked as expected
        $data = array(
            'basePath'      => 'path/to/some/directory',
            'namespace'     => 'My',
            'resourceTypes' => array(
                'acl' => array(
                    'path'      => 'acls/',
                    'namespace' => 'Acl',
                )
            )
        );
        $loader2 = new Zend_Loader_Autoloader_Resource($data);

        // Check that autoloaders are configured the same
        $this->assertEquals($loader1, $loader2);
    }

    /**
     * @group ZF-11219
     */
    public function testMatchesMultiLevelNamespaces()
    {
        $this->loader->setNamespace('Foo_Bar')
            ->setBasePath(dirname(__FILE__) . '/_files')
            ->addResourceType('model', 'models', 'Model');
        $path = $this->loader->getClassPath('Foo_Bar_Model_Baz');
        $this->assertEquals(dirname(__FILE__) . '/_files/models/Baz.php', $path, var_export($path, 1));
    }
}
