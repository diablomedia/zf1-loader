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
class Zend_LoaderTest extends PHPUnit\Framework\TestCase
{
    protected $errorHandler;
    protected $error;
    protected $includePath;
    protected $loaders;

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

        $this->error        = null;
        $this->errorHandler = null;
        Zend_Loader_Autoloader::resetInstance();
    }

    public function tearDown(): void
    {
        if ($this->errorHandler !== null) {
            restore_error_handler();
        }

        // Restore original autoloaders
        $loaders = spl_autoload_functions();
        if (is_array($loaders)) {
            foreach ($loaders as $loader) {
                spl_autoload_unregister($loader);
            }
        }

        if (is_array($this->loaders)) {
            foreach ($this->loaders as $loader) {
                spl_autoload_register($loader);
            }
        }

        // Retore original include_path
        set_include_path($this->includePath);

        // Reset autoloader instance so it doesn't affect other tests
        Zend_Loader_Autoloader::resetInstance();
    }

    public function setErrorHandler()
    {
        set_error_handler(array($this, 'handleErrors'), E_USER_NOTICE);
        $this->errorHandler = true;
    }

    public function handleErrors($errno, $errstr)
    {
        $this->error = $errstr;
    }

    /**
     * Tests that a class can be loaded from a well-formed PHP file
     */
    public function testLoaderClassValid()
    {
        $dir = implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '_files', '_testDir1'));

        Zend_Loader::loadClass('Class1', $dir);

        $this->assertTrue(true);
    }

    public function testLoaderInterfaceViaLoadClass()
    {
        try {
            Zend_Loader::loadClass('Zend_Controller_DispatcherTest_Interface');
            $this->assertTrue(true);
        } catch (Zend_Exception $e) {
            $this->fail('Loading interfaces should not fail');
        }
    }

    public function testLoaderLoadClassWithDotDir()
    {
        $dirs = array('.');
        try {
            Zend_Loader::loadClass('Zend_VersionClass', $dirs);
            $this->assertTrue(true);
        } catch (Zend_Exception $e) {
            $this->fail('Loading from dot should not fail');
        }
    }

    /**
     * Tests that an exception is thrown when a file is loaded but the
     * class is not found within the file
     */
    public function testLoaderClassNonexistent()
    {
        $dir = implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '_files', '_testDir1'));

        try {
            Zend_Loader::loadClass('ClassNonexistent', $dir);
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertMatchesRegularExpression('/file(.*)does not exist or class(.*)not found/i', $e->getMessage());
        }
    }

    /**
     * Tests that an exception is thrown if the $dirs argument is
     * not a string or an array.
     */
    public function testLoaderInvalidDirs()
    {
        try {
            Zend_Loader::loadClass('Zend_Invalid_Dirs', new stdClass());
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertEquals('Directory argument must be a string or an array', $e->getMessage());
        }
    }

    /**
     * Tests that a class can be loaded from the search directories.
     */
    public function testLoaderClassSearchDirs()
    {
        $dirs = array();
        foreach (array('_testDir1', '_testDir2') as $dir) {
            $dirs[] = implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '_files', $dir));
        }

        // throws exception on failure
        Zend_Loader::loadClass('Class1', $dirs);
        Zend_Loader::loadClass('Class2', $dirs);

        $this->assertTrue(true);
    }

    /**
     * Tests that a class locatedin a subdirectory can be loaded from the search directories
     */
    public function testLoaderClassSearchSubDirs()
    {
        $dirs = array();
        foreach (array('_testDir1', '_testDir2') as $dir) {
            $dirs[] = implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '_files', $dir));
        }

        // throws exception on failure
        Zend_Loader::loadClass('Class1_Subclass2', $dirs);

        $this->assertTrue(true);
    }

    /**
     * Tests that the security filter catches illegal characters.
     */
    public function testLoaderClassIllegalFilename()
    {
        try {
            Zend_Loader::loadClass('/path/:to/@danger');
            $this->fail('Zend_Exception was expected but never thrown.');
        } catch (Zend_Exception $e) {
            $this->assertMatchesRegularExpression('/security(.*)filename/i', $e->getMessage());
        }
    }

    /**
     * Tests that loadFile() finds a file in the include_path when $dirs is null
     */
    public function testLoaderFileIncludePathEmptyDirs()
    {
        $saveIncludePath = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, array($saveIncludePath, implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '_files', '_testDir1')))));

        $this->assertTrue(Zend_Loader::loadFile('Class3.php', null));

        set_include_path($saveIncludePath);
    }

    /**
     * Tests that loadFile() finds a file in the include_path when $dirs is non-null
     * This was not working vis-a-vis ZF-1174
     */
    public function testLoaderFileIncludePathNonEmptyDirs()
    {
        $saveIncludePath = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, array($saveIncludePath, implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__), '_files', '_testDir1')))));

        $this->assertTrue(Zend_Loader::loadFile('Class4.php', implode(PATH_SEPARATOR, array('foo', 'bar'))));

        set_include_path($saveIncludePath);
    }

    /**
     * Tests that isReadable works
     */
    public function testLoaderIsReadable()
    {
        $this->assertTrue(Zend_Loader::isReadable(__FILE__));
        $this->assertFalse(Zend_Loader::isReadable(__FILE__ . '.foobaar'));

        // test that a file in include_path gets loaded, see ZF-2985
        $this->assertTrue(Zend_Loader::isReadable('Zend/Controller/FrontClass.php'), get_include_path());
    }

    /**
     * Tests that autoload works for valid classes and interfaces
     */
    public function testLoaderAutoloadLoadsValidClasses()
    {
        $this->setErrorHandler();
        $this->assertEquals('Zend_Db_Profiler_ExceptionClass', Zend_Loader::autoload('Zend_Db_Profiler_ExceptionClass'));
        $this->assertStringContainsString('deprecated', $this->error);
        $this->error = '';
        $this->assertEquals('Zend_Auth_Storage_InterfaceClass', Zend_Loader::autoload('Zend_Auth_Storage_InterfaceClass'));
        $this->assertStringContainsString('deprecated', $this->error);
    }

    /**
     * Tests that autoload returns false on invalid classes
     */
    public function testLoaderAutoloadFailsOnInvalidClasses()
    {
        $this->setErrorHandler();
        $this->assertFalse(Zend_Loader::autoload('Zend_FooBar_Magic_Abstract'));
        $this->assertStringContainsString('deprecated', $this->error);
    }

    public function testLoaderRegisterAutoloadRegisters()
    {
        if (!function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload not installed on this PHP installation');
        }

        $this->setErrorHandler();
        Zend_Loader::registerAutoload();
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloaders = spl_autoload_functions();
        $found       = false;
        foreach ($autoloaders as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    $found = true;
                    spl_autoload_unregister($function);
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Failed to register Zend_Loader_Autoloader with spl_autoload');
    }

    public function testLoaderRegisterAutoloadExtendedClassNeedsAutoloadMethod()
    {
        if (!function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload not installed on this PHP installation');
        }

        $saveIncludePath = get_include_path();
        // Set tests/ dir on include_path
        set_include_path(__DIR__ . '/../' . PATH_SEPARATOR . get_include_path());

        $this->setErrorHandler();
        Zend_Loader::registerAutoload('Zend_Loader_MyLoader');
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloaders = spl_autoload_functions();
        $expected    = array('Zend_Loader_MyLoader', 'autoload');
        $found       = false;
        foreach ($autoloaders as $function) {
            if ($expected == $function) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Failed to register Zend_Loader_MyLoader::autoload() with spl_autoload');

        spl_autoload_unregister($expected);

        set_include_path($saveIncludePath);
    }

    public function testLoaderRegisterAutoloadExtendedClassWithAutoloadMethod()
    {
        if (!function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload not installed on this PHP installation');
        }

        $saveIncludePath = get_include_path();
        // Set tests/ dir on include_path
        set_include_path(__DIR__ . '/../' . PATH_SEPARATOR . get_include_path());

        $this->setErrorHandler();
        Zend_Loader::registerAutoload('Zend_Loader_MyOverloader');
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloaders = spl_autoload_functions();
        $found       = false;
        foreach ($autoloaders as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Failed to register Zend_Loader_Autoloader with spl_autoload');

        $autoloaders = Zend_Loader_Autoloader::getInstance()->getAutoloaders();
        $found       = false;
        $expected    = array('Zend_Loader_MyOverloader', 'autoload');
        $this->assertTrue(in_array($expected, $autoloaders, true), 'Failed to register My_Loader_MyOverloader with Zend_Loader_Autoloader: ' . var_export($autoloaders, 1));

        // try to instantiate a class that is known not to be loaded
        $obj = new Zend_Loader_AutoloadableClass();

        // now it should be loaded
        $this->assertTrue(
            class_exists('Zend_Loader_AutoloadableClass'),
            'Expected Zend_Loader_AutoloadableClass to be loaded'
        );

        // and we verify it is the correct type
        $this->assertInstanceOf(
            Zend_Loader_AutoloadableClass::class,
            $obj,
            'Expected to instantiate Zend_Loader_AutoloadableClass, got ' . get_class($obj)
        );

        spl_autoload_unregister($function);

        set_include_path($saveIncludePath);
    }

    public function testLoaderRegisterAutoloadFailsWithoutSplAutoload()
    {
        if (function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload() is installed on this PHP installation; cannot test for failure');
        }

        try {
            Zend_Loader::registerAutoload();
            $this->fail('registerAutoload should fail without spl_autoload');
        } catch (Zend_Exception $e) {
        }
    }

    public function testLoaderRegisterAutoloadInvalidClass()
    {
        if (!function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload() not installed on this PHP installation');
        }

        $this->setErrorHandler();
        try {
            Zend_Loader::registerAutoload('stdClass');
            $this->fail('registerAutoload should fail without spl_autoload');
        } catch (Zend_Exception $e) {
            $this->assertEquals('The class "stdClass" does not have an autoload() method', $e->getMessage());
            $this->assertStringContainsString('deprecated', $this->error);
        }
    }

    public function testLoaderUnregisterAutoload()
    {
        if (!function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload() not installed on this PHP installation');
        }

        $saveIncludePath = get_include_path();
        // Set tests/ dir on include_path
        set_include_path(__DIR__ . '/../' . PATH_SEPARATOR . get_include_path());

        $this->setErrorHandler();
        Zend_Loader::registerAutoload('Zend_Loader_MyOverloader');
        $this->assertStringContainsString('deprecated', $this->error);

        $expected    = array('Zend_Loader_MyOverloader', 'autoload');
        $autoloaders = Zend_Loader_Autoloader::getInstance()->getAutoloaders();
        $this->assertTrue(in_array($expected, $autoloaders, true), 'Failed to register autoloader');

        Zend_Loader::registerAutoload('Zend_Loader_MyOverloader', false);
        $autoloaders = Zend_Loader_Autoloader::getInstance()->getAutoloaders();
        $this->assertFalse(in_array($expected, $autoloaders, true), 'Failed to unregister autoloader');

        foreach (spl_autoload_functions() as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    spl_autoload_unregister($function);
                    break;
                }
            }
        }

        set_include_path($saveIncludePath);
    }

    /**
     * @group ZF-6605
     */
    public function testRegisterAutoloadShouldEnableZendLoaderAutoloaderAsFallbackAutoloader()
    {
        if (!function_exists('spl_autoload_register')) {
            $this->markTestSkipped('spl_autoload() not installed on this PHP installation');
        }

        $this->setErrorHandler();
        Zend_Loader::registerAutoload();
        $this->assertStringContainsString('deprecated', $this->error);

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $this->assertTrue($autoloader->isFallbackAutoloader());

        foreach (spl_autoload_functions() as $function) {
            if (is_array($function)) {
                $class = $function[0];
                if ($class == 'Zend_Loader_Autoloader') {
                    spl_autoload_unregister($function);
                    break;
                }
            }
        }
    }

    /**
     * @group ZF-8200
     * @doesNotPerformAssertions
     */
    public function testLoadClassShouldAllowLoadingPhpNamespacedClasses()
    {
        Zend_Loader::loadClass('\Zfns\Foo', array(dirname(__FILE__) . '/Loader/_files'));
    }

    /**
     * @group ZF-7271
     * @group ZF-8913
     */
    public function testIsReadableShouldHonorStreamDefinitions()
    {
        $pharFile = dirname(__FILE__) . '/Loader/_files/Zend_LoaderTest.phar';
        $phar     = new Phar($pharFile, 0, 'zlt.phar');
        $incPath  = 'phar://zlt.phar'
                 . PATH_SEPARATOR . $this->includePath;
        set_include_path($incPath);
        $this->assertTrue(Zend_Loader::isReadable('User.php'));
        unset($phar);
    }

    /**
     * @group ZF-8913
     */
    public function testIsReadableShouldNotLockWhenTestingForNonExistantFileInPhar()
    {
        $pharFile = dirname(__FILE__) . '/Loader/_files/Zend_LoaderTest.phar';
        $phar     = new Phar($pharFile, 0, 'zlt.phar');
        $incPath  = 'phar://zlt.phar'
                 . PATH_SEPARATOR . $this->includePath;
        set_include_path($incPath);
        $this->assertFalse(Zend_Loader::isReadable('does-not-exist'));
        unset($phar);
    }

    /**
     * @group ZF-7271
     */
    public function testExplodeIncludePathProperlyIdentifiesStreamSchemes()
    {
        if (PATH_SEPARATOR != ':') {
            $this->markTestSkipped();
        }
        $path  = 'phar://zlt.phar:/var/www:.:filter://[a-z]:glob://*';
        $paths = Zend_Loader::explodeIncludePath($path);
        $this->assertSame(array(
            'phar://zlt.phar',
            '/var/www',
            '.',
            'filter://[a-z]',
            'glob://*',
        ), $paths);
    }

    /**
     * @group ZF-9100
     */
    public function testIsReadableShouldReturnTrueForAbsolutePaths()
    {
        set_include_path(dirname(__FILE__) . '../../');
        $path = dirname(__FILE__);
        $this->assertTrue(Zend_Loader::isReadable($path));
    }

    /**
     * @group ZF-9263
     * @group ZF-9166
     * @group ZF-9306
     */
    public function testIsReadableShouldFailEarlyWhenProvidedInvalidWindowsAbsolutePath()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            $this->markTestSkipped('Windows-only test');
        }
        $path = 'C:/this/file/should/not/exist.php';
        $this->assertFalse(Zend_Loader::isReadable($path));
    }
}
