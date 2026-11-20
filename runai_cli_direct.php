<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Ai\GroqProvider;

$p = $app->make(GroqProvider::class);
$msgs = [
    ['role'=>'system','content'=>'Tu es un journaliste senior pour un média TV Afrique.'],
    ['role'=>'user','content'=>'Rédige un angle journalistique de 400-600 mots sur : Sénat - session ordinaire au Gabon. Huguette Nyana Ekoume appelle au travail. Max 500 mots. UNIQUEMENT le texte brut, pas de formatage Markdown.'],
];
$full = '';
$start = microtime(true);
try {
    $p->streamChat(
        systemPrompt: 'Tu es un journaliste senior pour un média TV Afrique. Rédige en français.',
        userPrompt:  'Rédige un angle journalistique de 400-600 mots sur : Sénat - session ordinaire au Gabon. Huguette Nyana Ekoume appelle au travail. Max 500 mots. UNIQUEMENT le texte brut, pas de formatage Markdown.',
        onChunk:     function($chunk, $done) use (&$full) { if (!$done) $full .= $chunk; }
    );
    $elapsed = round(microtime(true) - $start, 1);
    $wc = str_word_count($full);
    echo "OK $elapsed s · $wc mots · len=".strlen($full)."\n";
    echo "---PREVIEW---\n".mb_substr($full,0,150)."\n---END---\n";
} catch (\Throwable $e) {
    echo "ERREUR: ".$e->getMessage()."\n";
}
