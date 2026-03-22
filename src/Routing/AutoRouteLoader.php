<?php

declare(strict_types=1);

namespace SymfonySwagger\Routing;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use SymfonySwagger\Controller\SwaggerDocController;

class AutoRouteLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly bool $enabled = true,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $collection = $this->inner->load($resource, $type);

        if (!$this->enabled || !($collection instanceof RouteCollection)) {
            return $collection;
        }

        $collection->addCollection($this->createDocumentationRoutes());

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }

    private function createDocumentationRoutes(): RouteCollection
    {
        $collection = new RouteCollection();

        $collection->add('symfony_swagger_doc', new Route(
            '/api/docs.json',
            ['_controller' => SwaggerDocController::class.'::documentation'],
            [],
            [],
            '',
            [],
            ['GET'],
        ));

        $collection->add('symfony_swagger_ui', new Route(
            '/api/docs',
            ['_controller' => SwaggerDocController::class.'::swaggerUi'],
            [],
            [],
            '',
            [],
            ['GET'],
        ));

        $collection->add('symfony_swagger_scalar', new Route(
            '/api/docs/scalar',
            ['_controller' => SwaggerDocController::class.'::scalar'],
            [],
            [],
            '',
            [],
            ['GET'],
        ));

        $collection->addResource(new FileResource(__FILE__));
        $collection->addResource(new FileResource((new \ReflectionClass(SwaggerDocController::class))->getFileName()));

        return $collection;
    }
}
