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

    /**
     * Swagger UI page.
     */
    #[Route('/api/docs', name: 'symfony_swagger_ui', methods: ['GET'])]
    public function swaggerUi(): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Swagger UI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
      body { margin: 0; background: #f7f7f7; }
    </style>
  </head>
  <body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
      window.onload = function () {
        SwaggerUIBundle({
          url: '/api/docs.json',
          dom_id: '#swagger-ui',
          presets: [SwaggerUIBundle.presets.apis],
          layout: 'BaseLayout'
        });
      };
    </script>
  </body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Scalar API reference page.
     */
    #[Route('/api/docs/scalar', name: 'symfony_swagger_scalar', methods: ['GET'])]
    public function scalar(): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Scalar API Reference</title>
    <style>
      body { margin: 0; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
  </head>
  <body>
    <scalar-api-reference data-url="/api/docs.json"></scalar-api-reference>
  </body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
