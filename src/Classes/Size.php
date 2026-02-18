<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Classes;

class Size
{
    public static function getHumanSize($size): string
    {
        if ($size <= 0) {
            return '';
        }

        $units = ['octets', 'Ko', 'Mo', 'Go', 'To', 'Po'];
        $factor = (int) floor(log($size, 1024));

        return sprintf('%.1f %s', $size / 1024 ** $factor, $units[$factor]);
    }
}
