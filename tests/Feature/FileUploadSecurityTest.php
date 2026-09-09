<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HIGH PoC: Laravel's own "mimes" validation rule already blocks the most
 * common PHP-executable client extensions (php, php3-8, phtml, phar — see
 * ValidatesAttributes::shouldBlockPhpUpload()), so those specific names
 * can't reach TenantStorage at all through the real Livewire forms. But
 * that blocklist is not exhaustive — .pht and .phtm are both extensions a
 * default cPanel/Apache "AddHandler application/x-httpd-php .php .pht
 * .phtml" config (very common on the shared hosting this app explicitly
 * targets) will execute as PHP, and neither is in Laravel's blocklist. A
 * genuinely real image uploaded with a client filename ending in .pht
 * therefore passes mimes:jpg,jpeg,png,webp validation on content alone,
 * and TenantStorage::storeImage() previously stored it under that literal
 * client-claimed extension via getClientOriginalExtension() — the classic
 * image-polyglot upload-to-RCE vector, just via an extension Laravel's own
 * guard doesn't happen to cover.
 */
class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenant(): Tenant
    {
        return Tenant::factory()->create();
    }

    /**
     * Real JPEG bytes wrapped as an UploadedFile with its detected MIME
     * type explicitly forced to what a real production content-sniff would
     * report — Laravel's own fake()->image() reports a filename-guessed
     * MIME type in tests (see Illuminate\Http\Testing\File::getMimeType()),
     * which would misrepresent this as a non-image and mask the real bug.
     * Forcing it here is what makes guessExtension() correctly resolve to
     * "jpg" during the test, exactly as it would for a genuine image
     * uploaded in production regardless of what the client names it.
     */
    protected function realJpegNamed(string $clientName): UploadedFile
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();

        return UploadedFile::fake()->createWithContent($clientName, $bytes)->mimeType('image/jpeg');
    }

    public function test_a_real_image_with_a_malicious_client_filename_is_never_stored_with_an_executable_extension(): void
    {
        $tenant = $this->makeTenant();

        $path = TenantStorage::storeImage($this->realJpegNamed('shell.php'), 'products', $tenant);

        $this->assertStringEndsNotWith('.php', $path);
        $this->assertStringEndsNotWith('.phtml', $path);
        $this->assertStringEndsNotWith('.phar', $path);
        $this->assertMatchesRegularExpression('/\.(jpe?g|png|webp|gif|bmp)$/i', $path);
    }

    /**
     * .pht is not in Laravel's own shouldBlockPhpUpload() blocklist, but is
     * a real PHP-executable extension on common shared-hosting Apache
     * configs — this is the specific gap the fix closes, independent of
     * whatever Laravel's own upstream blocklist does or doesn't cover.
     */
    public function test_a_real_image_named_with_a_pht_extension_is_never_stored_as_pht(): void
    {
        $tenant = $this->makeTenant();

        $path = TenantStorage::storeImage($this->realJpegNamed('shell.pht'), 'products', $tenant);

        $this->assertStringEndsNotWith('.pht', $path);
        $this->assertMatchesRegularExpression('/\.(jpe?g|png|webp|gif|bmp)$/i', $path);
    }

    public function test_a_double_extension_filename_is_never_stored_with_the_dangerous_trailing_extension(): void
    {
        $tenant = $this->makeTenant();

        $path = TenantStorage::storeImage($this->realJpegNamed('photo.jpg.pht'), 'products', $tenant);

        $this->assertStringEndsNotWith('.pht', $path);
    }

    public function test_stored_image_filenames_are_server_generated_not_client_controlled(): void
    {
        $tenant = $this->makeTenant();

        $path = TenantStorage::storeImage($this->realJpegNamed('../../../../etc/passwn.jpg'), 'products', $tenant);

        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('passwn', $path);
        $this->assertStringStartsWith("tenants/{$tenant->uuid}/products/", $path);
    }

    /**
     * Simulates what a real production request looks like once it reaches
     * the mimes validation rule: genuinely real JPEG bytes, with the MIME
     * type explicitly forced to what a real fileinfo-based content sniff
     * would report (Laravel's own fake()->image() reports a filename-
     * guessed MIME type in tests, which isn't representative of production
     * content-sniffing behavior) — so this exercises the full real
     * save() flow, not just the storage helper in isolation. Uses .pht
     * rather than .php since Laravel's own validator already blocks a
     * client-claimed .php extension outright (see
     * ValidatesAttributes::shouldBlockPhpUpload()) — .pht is the real gap
     * this fix closes, and is what actually reaches TenantStorage.
     */
    public function test_uploaded_product_image_end_to_end_via_the_real_form(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($image);
        $jpegBytes = ob_get_clean();

        $file = UploadedFile::fake()->createWithContent('shell.pht', $jpegBytes)->mimeType('image/jpeg');

        \Livewire\Livewire::test('pages::tenant.products.index')
            ->call('openCreate')
            ->set('name', 'Test Product')
            ->set('type', 'ready_to_sell')
            ->set('selling_price', '50')
            ->set('low_stock_threshold', '5')
            ->set('image', $file)
            ->call('save');

        $product = \App\Models\Product::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->where('name', 'Test Product')->firstOrFail();

        $this->assertStringEndsNotWith('.pht', $product->image_path);
    }
}
