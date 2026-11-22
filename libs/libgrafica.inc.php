<?php
declare(strict_types=1);


namespace Alphano\Libs;
require_once __DIR__ . '/alphano-exceptions.inc.php';

use Alphano\Database\PDOMySQLDriver;
use RuntimeException;

/* ------------------------------------------------------------------
 *  Percorsi immagini di fallback
 * ----------------------------------------------------------------*/
const MAGIC_IMG    = '/assets/placeholder.svg';
const APPROVED_IMG = '/assets/placeholder.svg';
/* ================================================================
 *  IMAGE UTILS
 * ==============================================================*/

/**
 * Ridimensiona mantenendo il rapporto d’aspetto (GD obbligatorio).
 * Fallback: copia “as-is” se GD non supportato.
 *
 * @throws RuntimeException
 */
function resizeImage(string $srcPath, int $newWidth, string $destPath): bool
{
    if (!file_exists($srcPath)) {
        throw new \AlphanoStorageException("File non trovato: $srcPath");
    }

    $info = @getimagesize($srcPath);
    if (!$info || count($info) < 3) {
        throw new \AlphanoStorageException("Formato/size non letto: $srcPath");
    }
    list($width, $height, $type) = $info;

    $data = @file_get_contents($srcPath);
    if ($data === false) {
        throw new \AlphanoStorageException("Impossibile leggere: $srcPath");
    }

    $srcImg = @imagecreatefromstring($data);
    if (!$srcImg) {
        alphanoEnsureImageDir($destPath);
        return alphanoCopyImageFallback($srcPath, $destPath);
    }

    $scale = $width > $newWidth ? ($newWidth / $width) : 1;
    $tW    = (int) round($width  * $scale);
    $tH    = (int) round($height * $scale);
    $thumb = imagecreatetruecolor($tW, $tH);

    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    if (!imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $tW, $tH, $width, $height)) {
        imagedestroy($srcImg);
        imagedestroy($thumb);
        throw new \AlphanoStorageException("Errore resample: $srcPath");
    }
    imagedestroy($srcImg);

    alphanoEnsureImageDir($destPath, $thumb);

    $ok = writeImage($type, $thumb, $destPath);
    imagedestroy($thumb);

    if (!$ok) {
        throw new \AlphanoStorageException("writeImage fallito: $destPath");
    }

    return true;
}

/** Ensure the destination directory exists, releasing a GD resource on failure. */
function alphanoEnsureImageDir(string $destPath, ?\GdImage $thumb = null): void
{
    $dir = dirname($destPath);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        if ($thumb !== null) { imagedestroy($thumb); }
        throw new \AlphanoStorageException("Impossibile creare dir: $dir");
    }
}

/** Direct-copy fallback when GD cannot decode the source. */
function alphanoCopyImageFallback(string $srcPath, string $destPath): bool
{
    if (!@copy($srcPath, $destPath)) {
        throw new \AlphanoStorageException("Copy fallback fallito: $srcPath → $destPath");
    }
    return true;
}

/**
 * Scrive un’immagine GD su file, selezionando il formato giusto.
 */
function writeImage(int $type, \GdImage $image, string $filename): bool
{
    if (file_exists($filename)) {
        @unlink($filename);
    }

    return match ($type) {
        IMAGETYPE_GIF  => imagegif($image,  $filename),
        IMAGETYPE_JPEG => imagejpeg($image, $filename, 85),
        IMAGETYPE_PNG  => imagepng($image,  $filename),
        IMAGETYPE_WBMP => imagewbmp($image, $filename),
        default        => false,
    };
}
