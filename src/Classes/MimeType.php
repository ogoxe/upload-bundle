<?php

namespace Pentatrion\UploadBundle\Classes;

class MimeType
{
    public static function getIconByMimeType($mimeType): string
    {
        $mimeTypeExploded = explode('/', $mimeType);

        return match ($mimeTypeExploded[0]) {
            'image' => match ($mimeTypeExploded[1]) {
                'jpeg' => 'image-jpg.svg',
                'png' => 'image-png.svg',
                'webp' => 'image-webp.svg',
                'svg+xml', 'svg' => 'image-svg+xml.svg',
                'vnd.adobe.photoshop' => 'application-photoshop.svg',
                'x-xcf' => 'image-x-compressed-xcf.svg',
                default => 'image.svg',
            },
            'video' => 'video-x-generic.svg',
            'audio' => 'application-ogg.svg',
            'font' => 'application-pgp-signature.svg',
            'application' => match ($mimeTypeExploded[1]) {
                'pdf' => 'application-pdf.svg',
                'illustrator' => 'application-illustrator.svg',
                'json' => 'application-json.svg',
                'vnd.oasis.opendocument.spreadsheet' => 'libreoffice-oasis-spreadsheet.svg',
                'vnd.oasis.opendocument.text' => 'libreoffice-oasis-master-document.svg',
                'vnd.openxmlformats-officedocument.wordprocessingml.document', 'msword' => 'application-msword-template.svg',
                'vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'vnd.ms-excel' => 'application-vnd.ms-excel.svg',
                'zip' => 'application-x-archive.svg',
                default => 'application-vnd.appimage.svg',
            },
            'text' => match ($mimeTypeExploded[1]) {
                'x-php' => 'text-x-php.svg',
                'x-java' => 'text-x-javascript.svg',
                'css' => 'text-css.svg',
                'html' => 'text-html.svg',
                'xml' => 'text-xml.svg',
                default => 'text.svg',
            },
            default => 'unknown.svg',
        };
    }
}
