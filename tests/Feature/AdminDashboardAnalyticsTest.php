<?php

namespace Tests\Feature;

use App\Models\DigitalPublication;
use App\Models\User;
use App\Services\AdminDashboardAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_dashboard_uses_zero_counts_instead_of_example_values(): void
    {
        $today = today()->toDateString();
        $data = app(AdminDashboardAnalyticsService::class)->build(['from' => $today, 'to' => $today]);

        $this->assertSame(0, $data['cards'][0]['value']);
        $this->assertSame(0, $data['cards'][2]['value']);
        $this->assertSame(0, $data['statutsTotal']);
        $this->assertCount(7, $data['trend']);
        $this->assertSame([], $data['recentPublications']);
        $this->assertSame([], $data['emissions']);
    }

    public function test_only_effectively_dated_publications_count_as_published(): void
    {
        $author = User::factory()->create();
        DigitalPublication::query()->create([
            'title' => 'Article publié',
            'content' => 'Contenu de vérification',
            'platform' => 'site',
            'status' => 'published',
            'created_by' => $author->id,
            'published_at' => now(),
        ]);
        DigitalPublication::query()->create([
            'title' => 'Statut publié sans date',
            'content' => 'Contenu de vérification',
            'platform' => 'site',
            'status' => 'published',
            'created_by' => $author->id,
            'published_at' => null,
        ]);

        $today = today()->toDateString();
        $data = app(AdminDashboardAnalyticsService::class)->build(['from' => $today, 'to' => $today]);

        $this->assertSame(1, $data['cards'][2]['value']);
        $this->assertSame(2, $data['statutsTotal']);
        $this->assertCount(1, $data['recentPublications']);
        $this->assertSame($author->name, $data['recentPublications'][0]['author']);
        $this->assertSame(1, $data['trend'][6]['published']);
    }
}
