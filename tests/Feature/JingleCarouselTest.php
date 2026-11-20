<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JingleCarouselTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_site_pages_do_not_embed_jingle_videos(): void
    {
        $home = $this->get('/')->assertOk();
        $home->assertDontSee('jingles/', false)
            ->assertDontSee('tv-jingle-player', false)
            ->assertDontSee('Jingles des émissions', false)
            ->assertDontSee('.mp4', false);

        $login = $this->get('/login')->assertOk();
        $login->assertDontSee('jingles/', false)
            ->assertDontSee('tv-jingle-player', false)
            ->assertDontSee('Jingles des émissions', false)
            ->assertDontSee('<video', false);

        $this->assertDirectoryDoesNotExist(public_path('jingles'));
    }
}
