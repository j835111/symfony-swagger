<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\PhpFileLoader;

class SymfonySwaggerRoutesTest extends TestCase
{
    public function testBuiltInRoutesCanBeLoadedFromPhpRoutingResource(): void
    {
        $loader = new PhpFileLoader(
            new FileLocator(\dirname(__DIR__, 2).'/config/routes'),
            'test',
        );

        $routes = $loader->load('symfony_swagger.php');

        $this->assertNotNull($routes->get('symfony_swagger_doc'));
        $this->assertSame('/api/docs.json', $routes->get('symfony_swagger_doc')->getPath());

        $this->assertNotNull($routes->get('symfony_swagger_ui'));
        $this->assertSame('/api/docs', $routes->get('symfony_swagger_ui')->getPath());

        $this->assertNotNull($routes->get('symfony_swagger_scalar'));
        $this->assertSame('/api/docs/scalar', $routes->get('symfony_swagger_scalar')->getPath());
    }
}
