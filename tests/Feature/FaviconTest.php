<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_pages_link_the_branded_favicon_formats(): void
    {
        foreach (['/', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (['favicon.svg', 'favicon-32x32.png', 'favicon.ico', 'apple-touch-icon.png'] as $asset) {
                $this->assertStringContainsString(asset($asset), $html);
            }
        }
    }

    public function test_favicon_assets_are_present_and_non_empty(): void
    {
        foreach (['favicon.svg', 'favicon-32x32.png', 'favicon.ico', 'apple-touch-icon.png'] as $asset) {
            $path = public_path($asset);

            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path), "$asset must not be empty.");
        }
    }
}
