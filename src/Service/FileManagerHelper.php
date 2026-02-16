<?php

namespace Pentatrion\UploadBundle\Service;

class FileManagerHelper implements FileManagerHelperInterface
{
    public function __construct(protected $origins)
    {
    }

    public function completeEntryPoints($entryPoints = []): array
    {
        $completeEntryPoints = [];
        foreach ($entryPoints as $entryPoint) {
            $originName = $entryPoint['origin'] ?? 'public';
            $completeEntryPoints[] = array_merge([
                'directory' => '',
                'origin' => $originName,
                'readOnly' => false,
                'icon' => 'famfm-folder',
                'label' => 'Répertoire principal',
                'webPrefix' => $this->origins[$originName]['web_prefix'] ?? null,
            ], $entryPoint);
        }

        return $completeEntryPoints;
    }

    public function completeConfig($baseConfig = []): array
    {
        $completeEntryPoints = $this->completeEntryPoints($baseConfig['entryPoints']);

        $fileUpload = isset($baseConfig['fileUpload']) && is_array($baseConfig['fileUpload'])
            ? $baseConfig['fileUpload']
            : [];
        $fileUpload = array_merge([
            'maxFileSize' => 10 * 1024 * 1024,
            'fileType' => [
                'text/*',
                'image/*', // image/vnd.adobe.photoshop  image/x-xcf
                'video/*',
                'audio/*',
            ],
        ], $fileUpload);

        unset($baseConfig['entryPoints']);
        unset($baseConfig['fileUpload']);

        return array_merge([
            'endPoint' => '/media-manager',
            'fileValidation' => null,
            'entryPoints' => $completeEntryPoints,
            'fileUpload' => $fileUpload,
            'themePrefix' => 'penta',
            'form' => [
                'filter' => 'small',
                'type' => 'image',
            ],
        ], $baseConfig);
    }
}
