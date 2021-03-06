<?php

use Saltwater\Server as S;

class ServerTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        S::destroy();
    }

    protected function tearDown()
    {
        S::destroy();
    }

    public function testCache()
    {
        $path = __DIR__ . '/cache/cache.cache';

        S::bootstrap('Saltwater\Root\Root', $path);

        $navigator = clone S::$n;

        S::destroy();

        S::bootstrap('Saltwater\Root\Root', $path);

        $this->assertEquals($navigator, S::$n);

        unlink($path);

        rmdir(__DIR__ . '/cache');
    }

    public function testModuleActions()
    {
        S::bootstrap(
            'Saltwater\Root\Root',
            array('Saltwater\RedBean\RedBean', 'Saltwater\App\App')
        );

        $this->assertEquals(
            'Saltwater\RedBean\RedBean',
            get_class(S::$n->modules->get('redbean'))
        );
    }

    /**
     * @runInSeparateProcess
     *
     * @requires PHP 5.4
     */
    public function testHalt()
    {
        S::halt('404', 'Not Found');

        $this->assertEquals(404, http_response_code());
    }
}
