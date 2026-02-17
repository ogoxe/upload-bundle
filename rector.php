<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPhpSets(php83: true)
    ->withTypeCoverageLevel(63)
    ->withDeadCodeLevel(59)
    ->withCodeQualityLevel(78)
    ->withCodingStyleLevel(27);
