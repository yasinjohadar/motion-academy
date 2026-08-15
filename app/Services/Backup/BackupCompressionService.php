<?php

namespace App\Services\Backup;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class BackupCompressionService
{
    public function zipDirectory(string $sourceDir, string $zipPath): string
    {
        if (! is_dir($sourceDir)) {
            throw new RuntimeException('مجلد المصدر غير موجود للضغط.');
        }

        $dir = dirname($zipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر إنشاء ملف ZIP.');
        }

        $sourceDir = realpath($sourceDir) ?: $sourceDir;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relative = substr($filePath, strlen($sourceDir) + 1);
            $relative = str_replace('\\', '/', $relative);

            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($filePath, $relative);
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function zipFiles(array $files, string $zipPath): string
    {
        $dir = dirname($zipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر إنشاء ملف ZIP.');
        }

        foreach ($files as $name => $path) {
            if (! is_file($path)) {
                continue;
            }
            $entry = is_string($name) && ! is_numeric($name) ? $name : basename($path);
            $zip->addFile($path, $entry);
        }

        $zip->close();

        return $zipPath;
    }

    public function extract(string $archivePath, string $destination): string
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('تعذر فتح الأرشيف للاستخراج.');
        }

        $zip->extractTo($destination);
        $zip->close();

        return $destination;
    }

    public function findFile(string $directory, string $extension): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with(strtolower($file->getFilename()), '.'.$extension)) {
                return $file->getRealPath() ?: null;
            }
        }

        return null;
    }
}
