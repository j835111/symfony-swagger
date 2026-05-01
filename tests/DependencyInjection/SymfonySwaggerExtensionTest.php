<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SymfonySwagger\DependencyInjection\SymfonySwaggerExtension;
use SymfonySwagger\Service\Describer\OperationDescriber;
use SymfonySwagger\Service\OpenApiGenerator;

class SymfonySwaggerExtensionTest extends TestCase
{
    public function testLoadRegistersParametersAndServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new SymfonySwaggerExtension();

        $extension->load([[]], $container);

        $this->assertTrue($container->hasParameter('symfony_swagger.config'));
        $this->assertTrue($container->hasParameter('symfony_swagger.enabled'));
        $this->assertTrue($container->hasParameter('symfony_swagger.output_path'));
        $this->assertTrue($container->hasParameter('symfony_swagger.analysis.max_depth'));

        $this->assertSame(true, $container->getParameter('symfony_swagger.enabled'));
        $this->assertSame(5, $container->getParameter('symfony_swagger.analysis.max_depth'));

        $this->assertTrue($container->hasDefinition(OpenApiGenerator::class));

        $operationDescriberArguments = $container->getDefinition(OperationDescriber::class)->getArguments();
        $this->assertSame('%symfony_swagger.config%', $operationDescriberArguments[3]);
    }
}
