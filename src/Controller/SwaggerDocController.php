<?php

declare(strict_types=1);

namespace SymfonySwagger\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonySwagger\Service\OpenApiGenerator;

/**
 * Built-in documentation controller.
 *
 * This controller is auto-registered by the bundle and provides
 * the OpenAPI documentation endpoint out of the box.
 */
class SwaggerDocController extends AbstractController
{
    public function __construct(
        private readonly OpenApiGenerator $openApiGenerator
    ) {
    }

    /**
     * Generates OpenAPI JSON documentation.
     */
    #[Route('/api/docs.json', name: 'symfony_swagger_doc', methods: ['GET'])]
    public function documentation(): Response
    {
        $openApiDoc = $this->openApiGenerator->generate();

        return new JsonResponse($openApiDoc, Response::HTTP_OK, [
            'Content-Type' => 'application/json',
        ]);
    }
}
