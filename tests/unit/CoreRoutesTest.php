<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class CoreRoutesTest extends CIUnitTestCase
{
    public function testEveryDeclaredHttpRouteHasALoadablePublicHandler(): void
    {
        $routes=Services::routes(false);
        require APPPATH.'Config/Routes.php';
        $checked=0;
        foreach (['get','post','put','delete','patch'] as $verb) {
            foreach ($routes->getRoutes($verb) as $uri=>$handler) {
                if (!is_string($handler) || !str_contains($handler,'::')) continue;
                [$class,$method]=explode('::',$handler,2);
                $method=explode('/',$method)[0];
                $this->assertTrue(class_exists($class),strtoupper($verb).' '.$uri.': '.$class);
                $this->assertTrue(method_exists($class,$method),$uri.': '.$handler);
                $this->assertTrue((new ReflectionMethod($class,$method))->isPublic(),$uri.': handler must be public');
                $checked++;
            }
        }
        $this->assertGreaterThan(400,$checked);
    }
}
