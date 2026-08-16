<?php

namespace Tests\Feature;

use App\Services\Storage\AppStorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Serving a file that must not be permanently public.
 *
 * There was no way to do this before: AppStorageManager::url() only ever returned a
 * permanent address, which is right for a blog image and wrong for a backup archive.
 *
 * A signed URL is a bearer credential, so most of this file is about what happens
 * when someone holds one they should not — a tampered link, an expired one, or one
 * whose path has been edited to point somewhere else entirely.
 */
class PrivateFileTest extends TestCase
{
    use RefreshDatabase;

    protected function storage(): AppStorageManager
    {
        return app(AppStorageManager::class);
    }

    /**
     * Writes through the same manager the controller reads through, so the test cannot
     * pass or fail on a disagreement about which disk 'media' resolves to.
     */
    protected function putFile(string $path = 'reports/secret.txt', string $body = 'محتوى خاص'): void
    {
        Storage::fake('public');

        $this->storage()->getDisk('media')->put($path, $body);
    }

    // ------------------------------------------------------------------ happy path

    public function test_a_signed_link_streams_the_file(): void
    {
        $this->putFile();

        $url = $this->storage()->temporaryUrl('media', 'reports/secret.txt');

        $this->assertNotNull($url);
        $this->get($url)->assertOk();
    }

    public function test_the_link_carries_an_expiry(): void
    {
        $this->putFile();

        $url = $this->storage()->temporaryUrl('media', 'reports/secret.txt', minutes: 5);

        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    // ------------------------------------------------------------------ refusals

    public function test_an_unsigned_request_is_refused(): void
    {
        $this->putFile();

        // The whole point: the path alone must not be enough.
        $this->get('/files/media/reports/secret.txt')->assertForbidden();
    }

    public function test_a_tampered_signature_is_refused(): void
    {
        $this->putFile();

        $url = $this->storage()->temporaryUrl('media', 'reports/secret.txt');

        $this->get($url.'x')->assertForbidden();
    }

    public function test_an_expired_link_is_refused(): void
    {
        $this->putFile();

        $url = $this->storage()->temporaryUrl('media', 'reports/secret.txt', minutes: 5);

        $this->travel(6)->minutes();

        // Expiry is the difference between a temporary link and a permanent one.
        $this->get($url)->assertForbidden();
    }

    public function test_editing_the_path_inside_a_valid_link_breaks_the_signature(): void
    {
        $this->putFile();
        $this->storage()->getDisk('media')->put('reports/other.txt', 'ملف آخر');

        $url = $this->storage()->temporaryUrl('media', 'reports/secret.txt');
        $swapped = str_replace('secret.txt', 'other.txt', $url);

        $this->get($swapped)->assertForbidden();
    }

    // ------------------------------------------------------------------ traversal

    public function test_a_traversal_path_is_refused_even_when_correctly_signed(): void
    {
        Storage::fake('public');

        // Signed by us, so the middleware lets it through — the controller is the only
        // thing standing between this link and the application's own files.
        $url = URL::temporarySignedRoute('files.private', now()->addMinutes(5), [
            'disk' => 'media',
            'path' => '../../../.env',
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_an_unknown_logical_disk_is_refused_even_when_signed(): void
    {
        Storage::fake('public');

        $url = URL::temporarySignedRoute('files.private', now()->addMinutes(5), [
            'disk' => 'not-a-disk',
            'path' => 'anything.txt',
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_a_missing_file_answers_404_not_500(): void
    {
        Storage::fake('public');

        $url = $this->storage()->temporaryUrl('media', 'gone.txt');

        $this->get($url)->assertNotFound();
    }

    // ------------------------------------------------------------------ contract

    public function test_no_path_means_no_url(): void
    {
        $this->assertNull($this->storage()->temporaryUrl('media', null));
        $this->assertNull($this->storage()->temporaryUrl('media', ''));
    }

    public function test_the_default_lifetime_comes_from_config(): void
    {
        $this->putFile();
        config()->set('storage.temporary_url_minutes', 3);

        $url = $this->storage()->temporaryUrl('media', 'reports/secret.txt');

        $this->travel(4)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_permanent_urls_are_unaffected(): void
    {
        $this->putFile();

        // Control: public assets must keep their plain, cacheable address.
        $url = $this->storage()->url('media', 'reports/secret.txt');

        $this->assertStringNotContainsString('signature=', (string) $url);
    }
}
