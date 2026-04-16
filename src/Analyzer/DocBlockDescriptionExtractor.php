<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

/**
 * Extracts OpenAPI-friendly text from PHPDoc blocks.
 */
final class DocBlockDescriptionExtractor
{
    public static function getSummary(\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection): ?string
    {
        $parts = self::extract($reflection->getDocComment());

        return $parts['summary'];
    }

    public static function getOperationDescription(\ReflectionMethod $reflection): ?string
    {
        $parts = self::extract($reflection->getDocComment());

        return $parts['explicitDescription'] ?? $parts['longDescription'];
    }

    public static function getSchemaDescription(
        \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection,
    ): ?string {
        $parts = self::extract($reflection->getDocComment());

        return $parts['explicitDescription']
            ?? $parts['body']
            ?? $parts['tagDescription']
            ?? $parts['summary'];
    }

    public static function getParameterDescription(\ReflectionMethod $method, string $parameterName): ?string
    {
        $parts = self::extract($method->getDocComment());

        return $parts['parameters'][$parameterName] ?? null;
    }

    /**
     * @return array{
     *     summary: string|null,
     *     body: string|null,
     *     longDescription: string|null,
     *     explicitDescription: string|null,
     *     tagDescription: string|null,
     *     parameters: array<string, string>
     * }
     */
    private static function extract(string|false $docComment): array
    {
        if (false === $docComment) {
            return [
                'summary' => null,
                'body' => null,
                'longDescription' => null,
                'explicitDescription' => null,
                'tagDescription' => null,
                'parameters' => [],
            ];
        }

        $lines = self::splitLines($docComment);

        $normalizedLines = [];
        $lastIndex = \count($lines) - 1;
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (0 === $index) {
                $line = preg_replace('#^/\*\*#', '', $line) ?? $line;
            }
            if ($lastIndex === $index) {
                $line = preg_replace('#\*/$#', '', $line) ?? $line;
            }

            $line = preg_replace('#^\*\s?#', '', $line) ?? $line;
            $normalizedLines[] = trim($line);
        }

        $bodyLines = [];
        $summary = null;
        $tagDescription = null;
        $explicitDescriptionLines = [];
        $collectingExplicitDescription = false;
        $parameterDescriptions = [];
        $currentParameterName = null;

        $appendToLastParameterDescription = static function (array &$descriptions, string $parameterName, string $line): void {
            $line = trim($line);
            if ('' === $line) {
                return;
            }

            $descriptions[$parameterName] = isset($descriptions[$parameterName]) && '' !== $descriptions[$parameterName]
                ? $descriptions[$parameterName]."\n".$line
                : $line;
        };

        foreach ($normalizedLines as $line) {
            if ($collectingExplicitDescription) {
                if (preg_match('/^@\w+/', $line)) {
                    $collectingExplicitDescription = false;
                } else {
                    $explicitDescriptionLines[] = $line;
                    continue;
                }
            }

            if (null !== $currentParameterName) {
                if (preg_match('/^@\w+/', $line)) {
                    $currentParameterName = null;
                } else {
                    $appendToLastParameterDescription($parameterDescriptions, $currentParameterName, $line);
                    continue;
                }
            }

            if (preg_match('/^@summary\s+(.+)$/', $line, $matches)) {
                $summary = trim($matches[1]);
                continue;
            }

            if (preg_match('/^@description\s+(.+)$/', $line, $matches)) {
                $explicitDescriptionLines[] = trim($matches[1]);
                $collectingExplicitDescription = true;
                continue;
            }

            if (preg_match('/^@param\s+.+?\s+\$(\w+)(?:\s+(.*))?$/', $line, $matches)) {
                $currentParameterName = $matches[1];
                $appendToLastParameterDescription($parameterDescriptions, $currentParameterName, $matches[2] ?? '');
                continue;
            }

            if (preg_match('/^@\w+/', $line)) {
                continue;
            }

            $bodyLines[] = $line;
        }

        $body = self::normalizeText($bodyLines);
        $bodySummary = null;
        $longDescription = null;

        if (null !== $body) {
            $paragraphs = preg_split("/\n\s*\n/", $body, 2);
            if (false !== $paragraphs) {
                $bodySummary = trim($paragraphs[0]);
                $remaining = $paragraphs[1] ?? null;
                $longDescription = null !== $remaining ? trim($remaining) : null;
                if ('' === $longDescription) {
                    $longDescription = null;
                }
            }
        }

        return [
            'summary' => $summary ?? $bodySummary,
            'body' => $body,
            'longDescription' => $longDescription,
            'explicitDescription' => self::normalizeText($explicitDescriptionLines),
            'tagDescription' => $tagDescription,
            'parameters' => $parameterDescriptions,
        ];
    }

    /**
     * @param list<string> $lines
     */
    private static function normalizeText(array $lines): ?string
    {
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        return '' === $text ? null : $text;
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }
}
