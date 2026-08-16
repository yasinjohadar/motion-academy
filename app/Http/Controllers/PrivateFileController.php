<?php

namespace App\Http\Controllers;

use App\Services\Storage\AppStorageManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private file behind a signed, expiring URL.
 *
 * Only reached for drivers that cannot sign their own object URLs — S3 and friends
 * hand the client a signed address and the bytes never come through here.
 *
 * The route carries `signed` middleware, so Laravel has already rejected a tampered
 * or expired link before this runs. What is left is this controller's own job: the
 * signature proves the link was issued by us, it does not prove the path inside it is
 * one we are willing to serve.
 */
class PrivateFileController extends Controller
{
    public function __invoke(AppStorageManager $storage, string $disk, string $path): StreamedResponse
    {
        // A signed URL is generated from a path we chose, but signatures can be
        // replayed across parameters if the set of acceptable values is not pinned.
        abort_unless(array_key_exists($disk, (array) config('storage.logical_disks', [])), 404);

        // Traversal never reaches the filesystem: `..` in a signed link would otherwise
        // read anything the process can, `.env` included.
        abort_if(str_contains($path, '..'), 404);

        $filesystem = $storage->getDisk($disk);

        abort_unless($filesystem->exists($path), 404);

        return $filesystem->response($path);
    }
}
