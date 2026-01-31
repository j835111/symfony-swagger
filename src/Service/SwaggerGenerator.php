<?php

declare(strict_types=1);

namespace SymfonySwagger\Service;

/**
 * SwaggerGenerator service.
 *
 * Main service for generating Swagger/OpenAPI documentation.
 * This class now delegates to OpenApiGenerator for actual generation.
 *
 * @deprecated Use OpenApiGenerator instead. This class is kept for backward compatibility.
 */
class SwaggerGenerator
{
    private ?OpenApiGenerator $generator = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        ?OpenApiGenerator $generator = null,
    ) {
        $this->generator = $generator;
    }

    /**
     * Set the OpenApiGenerator instance.
     *
     * This is used for dependency injection when OpenApiGenerator is available.
     */
    public function setGenerator(OpenApiGenerator $generator): void
    {
        $this->generator = $generator;
    }

    /**
     * Generate Swagger/OpenAPI documentation.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        // If OpenApiGenerator is available, use it
        if (null !== $this->generator) {
            return $this->generator->generate();
        }

        // Fallback to basic structure for backward compatibility
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config['info']['title'] ?? 'API Documentation',
                'description' => $this->config['info']['description'] ?? '',
                'version' => $this->config['info']['version'] ?? '1.0.0',
            ],
            'servers' => $this->config['servers'] ?? [],
            'paths' => [],
            'components' => [
                'schemas' => [],
            ],
        ];
    }

    /**
     * Get the configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
