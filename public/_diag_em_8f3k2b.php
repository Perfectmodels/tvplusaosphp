<?php
if (($_GET['k'] ?? '') !== 'tvpdiag2026') { http_response_code(403); exit('forbidden'); }
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
header('Content-Type: text/plain; charset=utf-8');
Illuminate\Support\Facades\DB::beginTransaction();
try {
    $e = App\Models\Emission::create([
        'nom' => 'Diag Test Emission',
        'type' => 'jt',
        'heure_debut' => '12:00',
        'heure_fin' => '12:30',
        'duree_minutes' => 30,
        'statut' => 'Brouillon',
        'is_brouillon' => true,
        'is_active' => true,
        'jours_diffusion' => [],
        'langues' => ['Français'],
    ]);
    echo "OK id=" . $e->id . " slug=" . $e->slug . "\n";
} catch (Throwable $ex) {
    echo "ERR_CLASS=" . get_class($ex) . "\n";
    echo "ERR_MSG=" . $ex->getMessage() . "\n";
    echo "FILE=" . $ex->getFile() . ":" . $ex->getLine() . "\n";
}
Illuminate\Support\Facades\DB::rollBack();
echo "ROLLED_BACK\n";
