<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Service;

interface FileManagerHelperInterface
{
    public function completeConfig(mixed $baseConfig = []): array;
}
