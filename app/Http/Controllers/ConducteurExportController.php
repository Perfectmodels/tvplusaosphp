<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conducteur;
use App\Services\ConducteurPdfExporter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ConducteurExportController extends Controller
{
    /**
     * Export imprimable : script présentateur + données complètes du conducteur d'émission.
     */
    public function export(Conducteur $conducteur)
    {
        Gate::authorize('view', $conducteur);
        abort_if((string) $conducteur->type !== 'emission', 404);

        $pdf = app(ConducteurPdfExporter::class)->export($conducteur);

        $nom = Str::slug((string) ($conducteur->nom ?? 'conducteur')) ?: 'conducteur';
        $filename = sprintf('Conducteur_%s_%s.pdf', $nom, $conducteur->date_edition?->format('Ymd') ?? 'sans_date');

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
