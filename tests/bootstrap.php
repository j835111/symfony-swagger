<?php

declare(strict_types=1);

require \dirname(__DIR__).'/vendor/autoload.php';

if (!class_exists(Symfony\Component\Security\Http\Attribute\IsGranted::class)) {
    eval(<<<'PHP'
namespace Symfony\Component\Security\Http\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class IsGranted
{
    public function __construct(
        public string|array|null $attribute = null,
    ) {
    }
}
PHP);
}
