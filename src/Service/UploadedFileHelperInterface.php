<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Service;

use Pentatrion\UploadBundle\Entity\UploadedFile;
use SplFileInfo;

interface UploadedFileHelperInterface
{
    public function getAbsolutePath(mixed $uploadRelativePath, mixed $originName = null): string;

    public function getWebPath(mixed $uploadRelativePath, mixed $originName = null): string;

    public function getLiipPath(?string $uploadRelativePath, mixed $originName = null): string;

    public function getLiipId(mixed $uploadRelativePath, mixed $originName = null): string;

    public function getLiipPathFromFile(SplFileInfo $file, mixed $originName = null);

    public function parseLiipId(string $liipId): array;

    public function getUrlThumbnail(mixed $liipPath, mixed $filter, array $runtimeConfig = [], mixed $suffix = null);

    public function getUploadedFile(mixed $uploadRelativePath, mixed $originName = null): ?UploadedFile;

    public function getUploadedFileByLiipId(string $liipId): UploadedFile;

    public function getUploadedFilesFromDirectory(mixed $uploadDirectory, mixed $originName, mixed $mimeGroup = null, bool $withDirectoryInfos = false): array;

    public function addAbsolutePath(UploadedFile $uploadedFile): UploadedFile;

    public function hydrateFileWithAbsolutePath(UploadedFile $uploadedFile): string;

    public function eraseSensibleInformations(UploadedFile $uploadedFile): UploadedFile;

    public static function hasGrantedAccess(UploadedFile $uploadedFile, $user);

    public function addAdditionalInfos(&$infos);

    public function addAdditionalInfosToDirectoryFiles(array &$data);
}
