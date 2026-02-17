<?php

namespace Pentatrion\UploadBundle\Classes;

use Pentatrion\UploadBundle\Entity\UploadedFile;
use ZipArchive;

class ExtendedZip extends ZipArchive
{

    // Member function to add a whole file system subtree to the archive
    public function addTree($dirname, $localName = ''): void
    {
        if ($localName)
            $this->addEmptyDir($localName);
        $this->_addTree($dirname, $localName);
    }

    // Internal function, to recurse
    protected function _addTree(string $dirname, ?string $localName): void
    {
        $dir = opendir($dirname);
        while ($filename = readdir($dir)) {
            // Discard . and ..
            if ($filename === '.' || $filename === '..')
                continue;

            // Proceed according to type
            $path = $dirname . '/' . $filename;
            $localPath = $localName ? ($localName . '/' . $filename) : $filename;
            if (is_dir($path)) {
                // Directory: add & recurse
                $this->addEmptyDir($localPath);
                $this->_addTree($path, $localPath);
            } elseif (is_file($path)) {
                // File: just add
                $this->addFile($path, $localPath);
            }
        }
        closedir($dir);
    }

    // Helper function
    // Attention, plante si aucun fichier.
    public static function zipTree($dirname, $zipFilename, $flags = 0, $localName = ''): void
    {
        $zip = new self();
        $zip->open($zipFilename, $flags);
        $zip->addTree($dirname, $localName);
        $zip->close();
    }


    public static function createArchiveFromFiles($files): string
    {
        /** @var UploadedFile $firstFile */
        $firstFile = $files[0];

        $archiveTempPath = sys_get_temp_dir() . '/archive-' . uniqid() . '.zip';

        if (count($files) === 1 && $firstFile->getType() === 'dir') {
            ExtendedZip::zipTree($firstFile->getAbsolutePath(), $archiveTempPath, ZipArchive::CREATE);
        } else {

            $zip = new self();
            $zip->open($archiveTempPath, ZipArchive::CREATE);

            foreach ($files as $file) {
                /** @var UploadedFile $file */

                if ($file->getType() === 'file') {
                    $zip->addFile($file->getAbsolutePath(), $file->getFilename());
                } elseif ($file->getType() === 'dir') {
                    $zip->addTree($file->getAbsolutePath(), $file->getFilename());
                }
            }
            $zip->close();
        }

        return $archiveTempPath;
    }
}
