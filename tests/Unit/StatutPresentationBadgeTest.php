<?php

namespace Tests\Unit;

use App\Enums\StatutPresentationSequenceEnum;
use PHPUnit\Framework\TestCase;

class StatutPresentationBadgeTest extends TestCase
{
    public function test_seule_la_sequence_en_cours_porte_le_signal_clignotant(): void
    {
        $enCours = StatutPresentationSequenceEnum::EnCours->badge();

        $this->assertStringContainsString('tv-live-blink', $enCours);
        $this->assertStringNotContainsString('tv-live-blink', StatutPresentationSequenceEnum::Avenir->badge());
        $this->assertStringNotContainsString('tv-live-blink', StatutPresentationSequenceEnum::Termine->badge());
    }
}
