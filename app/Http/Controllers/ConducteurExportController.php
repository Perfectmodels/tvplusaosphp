<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conducteur;
use App\Services\ConducteurPdfExporter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConducteurExportController extends Controller
{
    /** Conducteur technique de régie, A4 paysage. */
    public function export(Conducteur $conducteur): StreamedResponse
    {
        return $this->download($conducteur, ConducteurPdfExporter::TECHNIQUE, 'Conducteur_Regie');
    }

    /** Texte destiné à être lu par le présentateur, A4 portrait. */
    public function script(Conducteur $conducteur): StreamedResponse
    {
        return $this->download($conducteur, ConducteurPdfExporter::SCRIPT, 'Script_Presentateur');
    }

    /** Dossier unique mêlant pages régie et pages de lecture. */
    public function dossier(Conducteur $conducteur): StreamedResponse
    {
        return $this->download($conducteur, ConducteurPdfExporter::COMPLET, 'Dossier_Conducteur');
    }

    private function download(Conducteur $conducteur, string $variant, string $prefix): StreamedResponse
    {
        Gate::authorize('view', $conducteur);
        abort_unless((string) $conducteur->type === 'emission', 404);

        $pdf = app(ConducteurPdfExporter::class)->export($conducteur, $variant);
        $name = Str::slug((string) ($conducteur->emission?->nom ?: $conducteur->nom ?: 'emission')) ?: 'emission';
        $filename = sprintf(
            '%s_%s_%s.pdf',
            $prefix,
            $name,
            $conducteur->date_edition?->format('Ymd') ?? 'sans_date'
        );

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
