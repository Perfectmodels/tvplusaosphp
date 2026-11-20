<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\VeilleRemixController;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

$user = User::find(1);
if (!$user) { echo "ERREUR User #1 introuvable\n"; exit(1); }
Auth::setUser($user);
$sid = 53;

$ctrl = $app->make(VeilleRemixController::class);

echo "=== TEST 1) POST store (draft initial, sujet #$sid) ===\n";
$req = Request::create('/veille/remixes', 'POST', [
    'subject_id' => $sid,
    'type'  => 'angle',
    'tone'  => 'journaliste',
    'language' => 'fr',
    'title' => 'TEST-SCRIPT : Sénat Nyana Ekoume (draft ctrl)',
    'content' => '<p>Paragraphe 1 test store initial.</p><p>Paragraphe 2 script CLI.</p>',
    'version' => ['source' => 'script_cli'],
]);
$resp = $ctrl->store($req);
echo "Status: ".$resp->getStatusCode()."\n";
echo "Content-Type: ".($resp->headers->get('content-type'))."\n";
$j1 = json_decode($resp->getContent(), true);
echo "JSON: ".json_encode($j1, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
$rid = $j1['remix_id'] ?? null;
if (!$rid) { echo "ERREUR: Pas de remix_id\n"; exit(2); }
echo "OK remix_id=$rid\n\n";

echo "=== TEST 2) PUT update ($rid, v2 source AI) ===\n";
$upd = Request::create("/veille/remixes/$rid", 'PUT', [
    'type'  => 'angle',
    'tone'  => 'scoop_tv',
    'language' => 'fr',
    'title' => 'TEST-SCRIPT : Sénat (édition v2 scoop TV)',
    'content' => '<p>Paragraphe 1 V2 - ACCROCHE STYLE JT 20h : URGENT — SÉNAT GABON.</p><p>Paragraphe 2 V2 : Développement scoop avec sources exclusives.</p>',
    'version' => ['source' => 'generate'],
]);
$resp2 = $ctrl->update($upd, \App\Models\VeilleRemix::findOrFail($rid));
$j2 = json_decode($resp2->getContent(), true);
echo "Status: ".$resp2->getStatusCode()."\n";
echo "JSON: ".json_encode($j2, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
echo "OK versions_count=".($j2['versions_count']??'N/A')."\n\n";

echo "=== TEST 3) PATCH validate (status → validated, redirect 302 sujet #$sid) ===\n";
$validReq = Request::create("/veille/remixes/$rid/validate", 'PATCH');
$resp3 = $ctrl->validateRemix($validReq, \App\Models\VeilleRemix::findOrFail($rid));
echo "Status: ".$resp3->getStatusCode()."\n";
$loc = $resp3->headers->get('location');
echo "Location: $loc\n";
$expected = route('veille.sujets.show', $sid);
echo "Expected: $expected\n";
echo "MATCH: ".(str_starts_with($loc, $expected) ? '✅ OK' : '❌ KO')."\n";

// Clean up db
echo "\n=== (CLEANUP) DELETE TEST REMIX $rid ??? y/N : NON, on laisse pour smoke test navigateur ===\n";
echo "DB row OK. Tu peux le supprimer via le bouton UI onglet Remixes.\n";
exit(0);
