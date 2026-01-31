<?php

declare(strict_types=1);

namespace SymfonySwagger;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * SymfonySwaggerBundle.
 *
 * Main bundle class for Symfony Swagger/OpenAPI integration.
 */
class SymfonySwaggerBundle extends Bundle
{
    /**
     * Returns the bundle path.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
