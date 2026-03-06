<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Controller;

use Exception;
use Pentatrion\UploadBundle\Classes\ExtendedZip;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Pentatrion\UploadBundle\Service\FileHelper;
use Pentatrion\UploadBundle\Service\UploadedFileHelperInterface;
use Pentatrion\UploadBundle\Service\Urlizer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class UploadController extends AbstractController implements ServiceSubscriberInterface
{
    public function __construct(
        private readonly UploadedFileHelperInterface $uploadedFileHelper,
        private readonly LoggerInterface $logger,
        private readonly NormalizerInterface $normalizer,
        private readonly FileHelper $fileHelper,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function getFiles(Request $request): JsonResponse
    {
        $directory = $request->request->get('directory');
        $origin = $request->request->get('origin');
        $mimeGroup = $request->request->get('mimeGroup');

        return $this->json($this->normalizer->normalize($this->uploadedFileHelper->getUploadedFilesFromDirectory(
            $directory,
            $origin,
            $mimeGroup,
            true // with directory infos
        )));
    }

    #[Route(path: '/file-manager-endpoint/media-show-file', name: 'file_manager_endpoint_media_show_file')]
    public function showFile(mixed $mode, mixed $origin, mixed $uploadRelativePath): BinaryFileResponse
    {
        $uploadedFile = $this->uploadedFileHelper->getUploadedFile($uploadRelativePath, $origin);
        $absolutePath = $this->uploadedFileHelper->getAbsolutePath($uploadRelativePath, $origin);

        if (!$this->uploadedFileHelper::hasGrantedAccess($uploadedFile, $this->getUser())) {
            throw new InformativeException(403, "Vous n'avez pas les droits suffisants pour voir le contenu de ce fichier !!");
        }

        $disposition = 'show' === $mode ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        $binaryFileResponse = $this->file($absolutePath, null, $disposition);

        // bug sinon cela télécharge l'image au lieu de l'afficher !
        if ('image/svg' === $binaryFileResponse->getFile()->getMimeType()) {
            $binaryFileResponse->headers->set('Content-Type', 'image/svg+xml');
        }

        return $binaryFileResponse;
    }

    public function downloadFile(Request $request): BinaryFileResponse
    {
        $liipIds = $request->request->all()['files'];
        $files = [];
        $user = $this->getUser();

        foreach ($liipIds as $liipId) {
            $uploadedFile = $this->uploadedFileHelper->getUploadedFileByLiipId($liipId);
            $this->uploadedFileHelper->addAbsolutePath($uploadedFile);

            if (!$this->uploadedFileHelper::hasGrantedAccess($uploadedFile, $user)) {
                throw new InformativeException(403, 'Le fichier appartient à un projet qui ne vous concerne pas !!');
            }

            $files[] = $uploadedFile;
        }

        $archiveTempPath = ExtendedZip::createArchiveFromFiles($files);

        return $this->file($archiveTempPath, 'archive.zip');
    }

    /**
     * @throws ExceptionInterface
     */
    public function editFileRequest(Request $request): JsonResponse
    {
        $infos = $request->request->all();
        $readOnly = $request->request->getBoolean('readOnly');

        if (empty($infos['newFilename']) || '.' === $infos['newFilename'][0]) {
            throw new InformativeException(401, "Le nom de fichier n'est pas valide");
        }

        $extension = strtolower(pathinfo((string) $infos['newFilename'], PATHINFO_EXTENSION));
        $filenameWithoutExtension = pathinfo((string) $infos['newFilename'], PATHINFO_FILENAME);

        $newFilename = Urlizer::urlize($filenameWithoutExtension);

        if ('' !== $extension) {
            $newFilename .= '.' . $extension;
        }

        $oldCompletePath = $this->uploadedFileHelper->getAbsolutePath($infos['uploadRelativePath'], $infos['origin']);

        $newRelativePath = $infos['directory'].'/'.$newFilename;
        $newCompletePath = $this->uploadedFileHelper->getAbsolutePath($newRelativePath, $infos['origin']);

        if (!$readOnly) {
            try {
                $filesystem = new Filesystem();
                $filesystem->rename($oldCompletePath, $newCompletePath);
            } catch (Exception) {
                throw new InformativeException(401, 'Impossible de renommer le fichier : '.$infos['filename'].'. Vérifiez que le nom est bien unique.');
            }
        } else {
            throw new InformativeException(401, 'Impossible de renommer le fichier : '.$infos['filename'].' car vous n\'avez pas les droits nécessaires.');
        }

        return $this->json([
            'file' => $this->normalizer->normalize($this->uploadedFileHelper->getUploadedFile($newRelativePath, $infos['origin'])),
        ]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function cropFile(Request $request): JsonResponse
    {
        $uploadRelativePath = $request->request->get('uploadRelativePath');
        $origin = $request->request->get('origin');

        $angle = (float) $request->request->get('rotate');
        $x = (float) $request->request->get('x');
        $y = (float) $request->request->get('y');
        if ($x < 0) {
            $x = 0;
        }

        if ($y < 0) {
            $y = 0;
        }

        $width = (float) $request->request->get('width');
        $height = (float) $request->request->get('height');
        $finalWidth = (float) $request->request->get('finalWidth');
        $finalHeight = (float) $request->request->get('finalHeight');

        try {
            $this->fileHelper->cropImage($uploadRelativePath, $origin, $x, $y, $width, $height, $finalWidth, $finalHeight, $angle);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        }

        return $this->json([
            'file' => $this->normalizer->normalize($this->uploadedFileHelper->getUploadedFile($uploadRelativePath, $origin)),
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $liipIds = $request->request->all()['files'];
        $errors = [];

        foreach ($liipIds as $liipId) {
            $uploadedFile = $this->uploadedFileHelper->getUploadedFileByLiipId($liipId);
            if (!$this->uploadedFileHelper::hasGrantedAccess($uploadedFile, $this->getUser())) {
                $errors[] = $uploadedFile->getFilename();
            } else {
                $this->fileHelper->delete($uploadedFile->getUploadRelativePath(), $uploadedFile->getOrigin());
            }
        }

        if ([] !== $errors) {
            throw new InformativeException(401, 'Impossible de supprimer le(s) fichier(s) : '.implode(', ', $errors)." car vous n'avez pas les droits suffisants.");
        }

        return $this->json([
            'success' => true,
        ]);
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route(path: '/add-directory', name:'media_add_directory')]
    public function addDirectory(Request $request): JsonResponse
    {
        $infos = $request->request->all();

        $filename = Urlizer::urlize($infos['filename']);
        if (strlen($filename) > 128) {
            throw new InformativeException(500, 'Le nom du dossier est trop long.');
        }

        $completePath = $this->uploadedFileHelper->getAbsolutePath(
            $infos['directory'].'/'.$filename,
            $infos['origin']
        );

        try {
            $filesystem = new FileSystem();
            $filesystem->mkdir($completePath);
        } catch (Exception) {
            throw new InformativeException(401, 'Impossible de créer le dossier');
        }

        return $this->json([
            'directory' => $this->normalizer->normalize($this->uploadedFileHelper->getUploadedFile($infos['directory'].'/'.$filename, $infos['origin'])),
        ]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $fileFromRequest = $request->files->get('file');
        $destRelDir = $request->request->get('directory');
        $origin = $request->request->get('origin');

        try {
            $violations = $this->fileHelper->validateFile($fileFromRequest);
            if ($violations !== []) {
                throw new InformativeException(415, implode('\n', $violations));
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        }

        $uploadedFile = $this->fileHelper->uploadFile(
            $fileFromRequest,
            $destRelDir,
            $origin,
        );

        return $this->json([
            'data' => $this->normalizer->normalize($uploadedFile),
        ]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function chunkFile(Request $request): JsonResponse
    {
        $filesystem = new Filesystem();

        $destRelDir = $request->query->get('directory');
        $origin = $request->query->get('origin');
        $tempLiipId = $request->query->get('liipId');

        $uid = $request->query->get('resumableIdentifier');
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$uid;

        $filename = $request->query->get('resumableFilename');
        $totalSize = $request->query->getInt('resumableTotalSize');
        $totalChunks = $request->query->getInt('resumableTotalChunks');

        $chunkFilename = 'chunk.part'.$request->query->getInt('resumableChunkNumber');

        // on teste simplement si la portion de fichier a déjà été uploadée.
        if ('GET' === $request->getMethod()) {
            $chunkPath = $tempDir.DIRECTORY_SEPARATOR.$chunkFilename;

            // le fichier n'existe pas, on signale qu'il faudra donc l'uploader.
            if (!$filesystem->exists($chunkPath)) {
                return new JsonResponse('', Response::HTTP_NO_CONTENT);
            }

        // le fichier existe, on vérifiera comme lors d'un upload si on ne peut pas
        // déjà assembler le fichier
        } else {
            // on upload la portion de fichier.
            $fileFromRequest = $request->files->get('file');
            try {
                // if (rand(0,3) === 3) {
                //     throw new \Exception("random error");
                // }
                $this->fileHelper->uploadChunkFile($fileFromRequest, $tempDir, $chunkFilename);
            } catch (Exception) {
                throw new InformativeException(500, 'Impossible de copier le fragment');
            }
        }

        try {
            $uploadedFile = $this->fileHelper->createFileFromChunks($tempDir, $filename, $totalSize, $totalChunks, $destRelDir, $origin);
        } catch (InformativeException $err) {
            throw $err;
        } catch (Exception|NotFoundExceptionInterface|ContainerExceptionInterface) {
            throw new InformativeException(500, "Impossible d'assembler les fragments en fichier");
        }

        if ($uploadedFile !== null) {
            return $this->json([
                'file' => $this->normalizer->normalize($uploadedFile),
                'oldLiipId' => $tempLiipId,
            ]);
        } else {
            return $this->json([
                'message' => 'GET' === $request->getMethod() ? 'chunk already exist' : 'chunk uploaded',
            ]);
        }
    }
}
