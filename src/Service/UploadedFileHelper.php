<?php

namespace Pentatrion\UploadBundle\Service;

use DateTime;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use LogicException;
use Override;
use Pentatrion\UploadBundle\Classes\MimeType;
use Pentatrion\UploadBundle\Entity\UploadedFile;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class UploadedFileHelper implements UploadedFileHelperInterface, ServiceSubscriberInterface
{

    // comme on veut un lien qui soit directement utilisable pour l'import
    // futur, c'est nécessaire de prégénérer la miniature
    protected static array $filtersToPregenerate = [];

    #[Override]
    public static function getSubscribedServices(): array
    {
        return [
            'cache.manager' => '?'.CacheManager::class,
            'data.manager' => '?'.DataManager::class,
            'filter.manager' => '?'.FilterManager::class,
            'router' => RouterInterface::class,
            'serializer' => '?'.SerializerInterface::class,
            'resolver.app' => '?'.AbsoluteWebPathResolver::class,
        ];
    }

    public function __construct(protected $origins, protected ContainerInterface $container, protected $defaultOriginName, protected $liipFilters)
    {
    }

    public function isOriginPublic($originName): bool
    {
        return isset($this->origins[$originName]['web_prefix']);
    }

    #[Override]
    public function getAbsolutePath($uploadRelativePath = '', $originName = null): string
    {
        if ($uploadRelativePath instanceof UploadedFile) {
            $relativePath = $uploadRelativePath->getUploadRelativePath();
            $originName = $uploadRelativePath->getOrigin();
        } elseif (is_array($uploadRelativePath)) {
            $relativePath = $uploadRelativePath['uploadRelativePath'];
            $originName = $uploadRelativePath['origin'];
        } else {
            $relativePath = $uploadRelativePath;
        }

        $originName ??= $this->defaultOriginName;
        $suffix = '' !== $relativePath ? '/'.$relativePath : '';

        return $this->origins[$originName]['path'].$suffix;
    }

    // renvoie un chemin web absolu si le fichier est public.

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Override]
    public function getWebPath($uploadRelativePath, $originName = null): string
    {
        $originName ??= $this->defaultOriginName;

        if (!isset($this->origins[$originName]['web_prefix'])) {
            return $this->container->get('router')->generate('file_manager_endpoint_media_show_file', [
                'mode' => 'show',
                'origin' => $originName,
                'uploadRelativePath' => $uploadRelativePath,
            ]);
        }

        return $this->origins[$originName]['web_prefix'].'/'.$uploadRelativePath;
    }

    #[Override]
    public function getLiipPathFromFile(SplFileInfo $file, $originName = null): string
    {
        $originName ??= $this->defaultOriginName;

        $uploadRelativePath = substr(
            $file->getPathname(),
            strlen((string) $this->origins[$originName]['path']) + 1,
        );

        return $this->origins[$originName]['liip_path'].'/'.$uploadRelativePath;
    }

    #[Override]
    public function getLiipPath($uploadRelativePath, $originName = null): string
    {
        $originName ??= $this->defaultOriginName;

        return $this->origins[$originName]['liip_path'].'/'.$uploadRelativePath;
    }

    // renvoie un identifiant pour liipImagine.
    #[Override]
    public function getLiipId($uploadRelativePath, $originName = null): string
    {
        $originName ??= $this->defaultOriginName;

        return "@$originName:$uploadRelativePath";
    }

    #[Override]
    public function parseLiipId($liipId): array
    {
        $str = substr((string) $liipId, 1);
        $firstColon = strpos($str, ':');
        $origin = substr($str, 0, $firstColon);
        $uploadRelativePath = substr($str, $firstColon + 1);

        return [$uploadRelativePath, $origin];
    }

    /**
     * @return string|null
     *
     * à partir d'un webPath retrouve l'url de son miniature
     */
    #[Override]
    public function getUrlThumbnail($liipPath, $filter, array $runtimeConfig = [], $suffix = null): ?string
    {
        if (!$this->container->has('cache.manager')) {
            throw new LogicException('You can not use the "getUrlThumbnail" method if the LiipImagineBundle is not available. Try running "composer require liip/imagine-bundle".');
        }
        try {
            $cacheManager = $this->container->get('cache.manager');
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
            throw new LogicException('You can not use the "getUrlThumbnail" method if the LiipImagineBundle is not available. Try running "composer require liip/imagine-bundle".');
        }

        if (!$liipPath || !$cacheManager) {
            return null;
        }

        if (!is_null($suffix)) {
            $suffix = is_string($suffix) ? '?'.$suffix : '?'.time();
        } else {
            $suffix = '';
        }

        // On prégénère les images dont on a besoin de figer l'url (via éditeur markdown)
        // sinon liipImagine nous donne une url de redirection qui n'est pas utilisable.
        // Et donc on ne met pas de timestamp
        if (in_array($filter, $this::$filtersToPregenerate)) {
            try {
                $filterManager = $this->container->get('filter.manager');
                $dataManager = $this->container->get('data.manager');
            } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
                throw new LogicException('You can not use the "getUrlThumbnail" method if the LiipImagineBundle is not available. Try running "composer require liip/imagine-bundle".');
            }

            if (!$cacheManager->isStored($liipPath, $filter)) {
                $binary = $dataManager->find($filter, $liipPath);
                $cacheManager->store(
                    $filterManager->applyFilter($binary, $filter, $runtimeConfig),
                    $liipPath,
                    $filter
                );
            }

            return $cacheManager->resolve($liipPath, $filter).$suffix;
        } else {
            return $cacheManager->getBrowserPath($liipPath, $filter, $runtimeConfig, null, UrlGeneratorInterface::ABSOLUTE_PATH).$suffix;
        }
    }

    #[Override]
    public function getUploadedFileByLiipId($liipId): UploadedFile
    {
        [$uploadRelativePath, $origin] = $this->parseLiipId($liipId);

        return $this->getUploadedFile($uploadRelativePath, $origin);
    }

    #[Override]
    public function addAbsolutePath(UploadedFile $uploadedFile): UploadedFile
    {
        $uploadedFile->setAbsolutePath(
            $this->getAbsolutePath($uploadedFile->getUploadRelativePath(), $uploadedFile->getOrigin())
        );

        return $uploadedFile;
    }

    #[Override]
    public function getUploadedFile($uploadRelativePath, $originName = null): ?UploadedFile
    {
        $absolutePath = $this->getAbsolutePath($uploadRelativePath, $originName);
        if (!file_exists($absolutePath)) {
            return null;
        }

        $file = new SplFileInfo($absolutePath);

        $lastSlash = strrpos((string) $uploadRelativePath, '/');
        $directory = false === $lastSlash ? '' : substr((string) $uploadRelativePath, 0, $lastSlash);

        if ($file->isDir()) {
            $mimeGroup = $mimeType = null;
            $icon = 'folder.svg';
        } else {
            $mimeType = MimeTypes::getDefault()->guessMimeType($file->getPathname());
            $mimeGroup = explode('/', (string) $mimeType)[0];
            $icon = MimeType::getIconByMimeType($mimeType);
        }

        if ('image' === $mimeGroup && 'image/svg' !== $mimeType && 'image/svg+xml' !== $mimeType) {
            [$imageWidth, $imageHeight] = getimagesize($absolutePath);
        } else {
            $imageWidth = $imageHeight = null;
        }

        return (new UploadedFile())
            // identifiant unique composé de l'@origin:uploadRelativePath
            // ex: @public:uploads/projet/mon-projet/fichier.jpg
            ->setLiipId($this->getLiipId($uploadRelativePath, $originName))
            ->setFilename($file->getFilename())
            ->setDirectory($directory)
            ->setMimeType($mimeType)
            ->setMimeGroup($mimeGroup)
            ->setImageWidth($imageWidth)
            ->setImageHeight($imageHeight)
            ->setType($file->getType())
            ->setOrigin($originName)
            ->setSize($file->getSize())
            ->setUpdatedAt((new DateTime())->setTimestamp($file->getMTime()))
            ->setIcon($icon)
            ->setPublic($this->isOriginPublic($originName));
    }

    #[Override]
    public function getUploadedFilesFromDirectory($uploadDirectory, $originName = null, $mimeGroup = null, $withDirectoryInfos = false): array
    {
        $finder = (new Finder())->sortByType()->depth('== 0');

        $absPath = $this->getAbsolutePath($uploadDirectory, $originName);

        $fs = new Filesystem();
        if (!$fs->exists($absPath)) {
            $fs->mkdir($absPath);
        }

        if (!is_dir($absPath)) {
            throw new InformativeException(404, 'Le chemin spécifié n\'est pas un dossier.');
        }

        $files = [];

        $filter = function (SplFileInfo $file) use ($mimeGroup): bool {
            if ($file->isDir() || is_null($mimeGroup)) {
                return true;
            }
            $mimeType = MimeTypes::getDefault()->guessMimeType($file->getPathname());
            $fileMimeGroup = explode('/', $mimeType)[0];

            return $fileMimeGroup === $mimeGroup;
        };

        $finder->in($absPath)->filter($filter);
        foreach ($finder as $file) {
            $files[] = $this->getUploadedFile(
                ('' !== $uploadDirectory ? $uploadDirectory.'/' : '').$file->getFilename(),
                $originName
            );
        }
        $data = [
            'files' => $files,
        ];
        if ($withDirectoryInfos) {
            $data['directory'] = $this->getUploadedFile($uploadDirectory, $originName);
        }

        return $this->addAdditionalInfosToDirectoryFiles($data);
    }

    #[Override]
    public function hydrateFileWithAbsolutePath(UploadedFile $uploadedFile): string
    {
        $absolutePath = $this->getAbsolutePath($uploadedFile->getUploadRelativePath(), $uploadedFile->getOrigin());
        $uploadedFile->setAbsolutePath($absolutePath);

        return $absolutePath;
    }

    #[Override]
    public function eraseSensibleInformations(UploadedFile $uploadedFile): UploadedFile
    {
        $uploadedFile->setAbsolutePath(null);

        return $uploadedFile;
    }

    public static function getHost(): string
    {
        if (!isset($_SERVER['REQUEST_SCHEME'])) {
            return '';
        }

        return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'];
    }

    #[Override]
    public function addAdditionalInfosToDirectoryFiles(&$data): array
    {
        return $data;
    }

    #[Override]
    public function addAdditionalInfos(&$infos): array
    {
        return $infos;
    }

    #[Override]
    public static function hasGrantedAccess(UploadedFile $uploadedFile, $user): bool
    {
        return true;
    }
}
