<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'mobile-app.latest_version_code' => 3,
            'mobile-app.latest_version_name' => '1.2',
            'mobile-app.min_version_code' => 2,
            'mobile-app.apk_path' => 'mobile/customer-portal.apk',
            'mobile-app.apk_sha256' => hash('sha256', 'apk-bytes'),
        ]);
    }

    public function test_version_endpoint_is_public_and_reports_the_configured_versions(): void
    {
        Storage::disk('local')->put('mobile/customer-portal.apk', 'apk-bytes');

        $this->getJson('/mobile-app/version')
            ->assertOk()
            ->assertJson([
                'latest_version_code' => 3,
                'latest_version_name' => '1.2',
                'min_version_code' => 2,
                'download_url' => route('mobile-app.download'),
                'apk_sha256' => hash('sha256', 'apk-bytes'),
            ]);
    }

    public function test_download_url_is_withheld_until_the_apk_is_uploaded(): void
    {
        $this->getJson('/mobile-app/version')
            ->assertOk()
            ->assertJson(['download_url' => null]);
    }

    public function test_download_url_is_withheld_when_the_checksum_is_missing(): void
    {
        Storage::disk('local')->put('mobile/customer-portal.apk', 'apk-bytes');
        config(['mobile-app.apk_sha256' => null]);

        $this->getJson('/mobile-app/version')
            ->assertOk()
            ->assertJson(['download_url' => null, 'apk_sha256' => null]);
    }

    public function test_download_streams_the_apk_without_a_session(): void
    {
        Storage::disk('local')->put('mobile/customer-portal.apk', 'apk-bytes');

        $response = $this->get('/mobile-app/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        $this->assertStringContainsString('customer-portal.apk', $response->headers->get('Content-Disposition'));
    }

    public function test_download_is_a_404_when_no_apk_has_been_uploaded(): void
    {
        $this->get('/mobile-app/download')->assertNotFound();
    }
}
