<?php

namespace App\Http\Controllers;

use App\Services\Storage\AppStorageManager;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private file behind a signed, expiring URL.
 *
 * Only reached while files live on this server — cloud accounts hand the client
 * a presigned address and the bytes never come through here.
 *
 * The route carries `signed` middleware, so Laravel has already rejected a tampered
 * or expired link before this runs. What is left is this controller's own job: the
 * signature proves the link was issued by us, it does not prove the path inside it is
 * one we are willing to serve.
 */
class PrivateFileController extends Controller
{
    public function __invoke(AppStorageManager $storage, string $disk, string $path): BinaryFileResponse|StreamedResponse
    {
        // A signed URL is generated from a path we chose, but signatures can be
        // replayed across parameters if the set of acceptable values is not pinned.
        abort_unless(AppStorageManager::isKnownDisk($disk), 404);

        // Traversal never reaches the filesystem: a `..` segment in a signed link
        // would otherwise read anything the process can, `.env` included. A name
        // that merely contains two dots (clip..final.mp4) is fine.
        $segments = explode('/', str_replace('\\', '/', $path));
        abort_if(in_array('..', $segments, true) || in_array('.', $segments, true), 404);

        $filesystem = $storage->getDisk($disk);

        abort_unless($filesystem->exists($path), 404);

        // Local files go out as a BinaryFileResponse, which honours HTTP Range —
        // without it a lesson video cannot be seeked.
        if ($filesystem instanceof FilesystemAdapter && $filesystem->getAdapter() instanceof LocalFilesystemAdapter) {
            return response()->file($filesystem->path($path));
        }

        return $filesystem->response($path);
    }
}
