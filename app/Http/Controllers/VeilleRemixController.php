<?php

namespace App\Http\Controllers;

use App\Models\VeilleSubject;
use App\Models\VeilleRemix;
use App\Contracts\AiProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VeilleRemixController extends Controller
{
    public static $TYPES = [
        'resume'        => ['label' => 'Résumé court',      'words' => '150-250 mots'],
        'angle'         => ['label' => 'Angle journalistique','words' => '400-600 mots'],
        'script_tv'     => ['label' => 'Script TV / ANT',   'words' => '800-1200 mots, format ANT'],
        'article'       => ['label' => 'Article complet',   'words' => '800-1500 mots'],
        'social'        => ['label' => 'Post social',       'words' => '280 caractères'],
        'titres'        => ['label' => '5 Titres alternatifs','words' => '5 propositions punchy'],
    ];

    public static $TONES = [
        'journaliste'   => 'Ton journaliste neutre et objectif (style AFP)',
        'scoop_tv'      => 'Ton scoop télévisuel percutant (style JT 20h)',
        'formel'        => 'Ton formel / institutionnel',
        'decontracte'   => 'Ton décontracté, accessible grand public',
        'documentaire'  => 'Ton documentaire / enquête approfondie',
        'jeune'         => 'Ton punchy jeune public (Gen Z)',
    ];

    public const TYPES = [];
    public const TONES = [];

    protected function systemPromptForType(string $type, string $tone, string $language = 'fr'): string
    {
        $types = self::$TYPES;
        $tones = self::$TONES;
        $def = $types[$type] ?? $types['angle'];
        $toneTxt = $tones[$tone] ?? $tones['journaliste'];
        $langTxt = match ($language) {
            'en' => 'en anglais',
            default => 'en français',
        };

        $intro = "Tu es un journaliste senior de rédaction TV Afrique (TV+A OS). Produis UNIQUEMENT le contenu demandé {$langTxt}, ton: {$toneTxt}. N'ajoute AUCUN commentaire avant/après. N'utilise PAS de markdown sauf si explicitement demandé. Respecte strictement le format et la longueur.";

        return match ($type) {
            'resume'   => "{$intro}\n\nBut : Résumer un article de presse.\nFormat : 1 paragraphe continu, {$def['words']}.\nRègle : Commencer par l'information la plus importante (pyramide inversée). Inclure qui/quoi/quand/où/pourquoi si connus.",
            'angle'    => "{$intro}\n\nBut : Proposer un angle journalistique original pour un reportage TV.\nFormat : 1 titre punchy suivi de {$def['words']} organisés en 3 parties (contexte / enjeux / angle reportage) avec 2 idées d'intervenants à interviewer.",
            'script_tv'=> "{$intro}\n\nBut : Produire un script d'antenne (ANT) pour un sujet de JT.\nFormat (strict) :\n[GÉNÉRIQUE / INTRO]\n(1 phrase accroche)\n\n[SUJET - Plan A - Images]\n(texte off 30s ~90 mots)\n\n[SUJET - Plan B - Paroles]\n(texte off 30s)\n\n[SUJET - Plan C - Conclusion]\n(texte off 20s + synthèse)\n\nDurée visée totale : 1min30 ~ {$def['words']}.",
            'article'  => "{$intro}\n\nBut : Article de presse complet.\nFormat : 1 titre, 1 chapo, {$def['words']} en 4-5 paragraphes (introduction, contexte, développement, conclusion).",
            'social'   => "{$intro}\n\nBut : Posts réseaux sociaux.\nFormat (strict) : Génère 3 posts distincts numérotés :\n1/ Threads / X (≤ 280 car, punchy, un hashtag)\n2/ Facebook (150-250 mots, conversationnel)\n3/ WhatsApp / statut diffusion (court, partageable).",
            'titres'   => "{$intro}\n\nBut : 5 titres punchy pour le même article.\nFormat : 5 numérotés, maximum 12 mots chacun. Variétés : 1 scoop, 1 question, 1 chiffre marquant, 1 emporte-pièce, 1 teaser.",
            default    => $intro,
        };
    }

    public function index() {
        return redirect()->route('veille.sujets.index');
    }

    public function create(Request $request, VeilleSubject $subject)
    {
        $this->authorize('create', VeilleRemix::class);
        $type = $request->query('type', 'angle');
        if (!isset(self::$TYPES[$type])) $type = 'angle';

        $remix = null;
        $existingId = $request->query('remix');
        if ($existingId) {
            $remix = VeilleRemix::where('subject_id', $subject->id)->find($existingId);
        }

        $types = self::$TYPES;
        $tones = self::$TONES;
        $aiActions = [
            'generate'   => ['icon' => 'fa-solid fa-wand-magic-sparkles', 'label' => 'Générer', 'primary' => true],
            'fix'        => ['icon' => 'fa-solid fa-spell-check',           'label' => 'Orthographe'],
            'translate'  => ['icon' => 'fa-solid fa-language',              'label' => 'Traduire FR/EN'],
            'expand'     => ['icon' => 'fa-solid fa-expand',                'label' => 'Développer'],
            'rephrase'   => ['icon' => 'fa-solid fa-palette',               'label' => 'Changer ton'],
            'titles'     => ['icon' => 'fa-solid fa-lightbulb',             'label' => '5 titres'],
        ];

        return view('veille.remixes.create', compact('subject','remix','type','types','tones','aiActions'));
    }

    public function edit(VeilleRemix $remix)
    {
        $this->authorize('update', $remix);
        $subject = $remix->subject;
        $type = $remix->type;
        $types = self::$TYPES;
        $tones = self::$TONES;
        $aiActions = [
            'generate'   => ['icon' => 'fa-solid fa-wand-magic-sparkles', 'label' => 'Générer', 'primary' => true],
            'fix'        => ['icon' => 'fa-solid fa-spell-check',           'label' => 'Orthographe'],
            'translate'  => ['icon' => 'fa-solid fa-language',              'label' => 'Traduire FR/EN'],
            'expand'     => ['icon' => 'fa-solid fa-expand',                'label' => 'Développer'],
            'rephrase'   => ['icon' => 'fa-solid fa-palette',               'label' => 'Changer ton'],
            'titles'     => ['icon' => 'fa-solid fa-lightbulb',             'label' => '5 titres'],
        ];
        return view('veille.remixes.create', compact('subject','remix','type','types','tones','aiActions'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', VeilleRemix::class);
        $valid = $request->validate([
            'subject_id' => 'required|exists:veille_subjects,id',
            'type'       => ['required', Rule::in(array_keys(self::$TYPES))],
            'title'      => 'required|string|min:2|max:255',
            'content'    => 'nullable|string',
            'tone'       => 'nullable|string|max:40',
            'language'   => 'nullable|string|max:3',
            'version'    => 'nullable|array',
            'metadata'   => 'nullable|array',
        ]);
        $valid['user_id'] = Auth::id();
        $valid['status']  = 'draft';
        $valid['ai_model'] = config('services.groq.model') . '@' . config('services.groq.base_uri');
        if (!empty($valid['version']) && is_array($valid['version'])) {
            $meta = is_array($valid['metadata'] ?? null) ? $valid['metadata'] : [];
            $versions = $meta['versions'] ?? [];
            $versions[] = [
                'ts'     => now()->toIso8601String(),
                'title'  => $valid['title'],
                'content'=> $valid['content'] ?? '',
                'source' => $valid['version']['source'] ?? 'manual',
            ];
            $meta['versions'] = $versions;
            $valid['metadata'] = $meta;
        }
        unset($valid['version']);

        $remix = VeilleRemix::create($valid);
        return response()->json([
            'success' => true,
            'remix_id' => $remix->id,
            'status'   => $remix->status,
            'saved_at' => $remix->updated_at->format('H:i:s'),
            'edit_url' => route('veille.remixes.edit', $remix),
        ]);
    }

    public function update(Request $request, VeilleRemix $remix)
    {
        $this->authorize('update', $remix);
        $valid = $request->validate([
            'type'    => ['nullable', Rule::in(array_keys(self::$TYPES))],
            'title'   => 'nullable|string|min:2|max:255',
            'content' => 'nullable|string',
            'tone'    => 'nullable|string|max:40',
            'language'=> 'nullable|string|max:3',
            'version' => 'nullable|array',
            'metadata'=> 'nullable|array',
            'status'  => ['nullable', Rule::in(['draft','validated','published'])],
        ]);

        if (!empty($valid['version']) && is_array($valid['version'])) {
            $existing = is_array($remix->metadata) ? $remix->metadata : [];
            $versions = $existing['versions'] ?? [];
            $versions[] = [
                'ts'     => now()->toIso8601String(),
                'title'  => $valid['title']   ?? $remix->title,
                'content'=> $valid['content'] ?? $remix->content,
                'source' => $valid['version']['source'] ?? 'manual',
            ];
            $existing['versions'] = $versions;
            $valid['metadata'] = $existing;
        }
        unset($valid['version']);

        $remix->update($valid);
        return response()->json([
            'success'  => true,
            'remix_id' => $remix->id,
            'status'   => $remix->status,
            'saved_at' => $remix->updated_at->format('H:i:s'),
            'versions_count' => count(($remix->metadata['versions'] ?? [])),
        ]);
    }

    public function validateRemix(Request $request, VeilleRemix $remix)
    {
        $this->authorize('update', $remix);
        $remix->update(['status' => 'validated']);
        return redirect()->route('veille.sujets.show', $remix->subject)
            ->with('status', 'Remix « '.e($remix->title).' » validé avec succès.');
    }

    public function destroy(VeilleRemix $remix)
    {
        $this->authorize('delete', $remix);
        $subjectId = $remix->subject_id;
        $remix->delete();
        return redirect()->route('veille.sujets.show', $subjectId)
            ->with('status', 'Remix supprimé.');
    }

    public function aiStream(Request $request, AiProviderInterface $ai)
    {
        $this->authorize('create', VeilleRemix::class);
        $valid = $request->validate([
            'subject_id'   => 'required|exists:veille_subjects,id',
            'action'       => 'required|string|max:30',
            'type'         => ['nullable', Rule::in(array_keys(self::$TYPES))],
            'tone'         => ['nullable', Rule::in(array_keys(self::$TONES))],
            'language'     => 'nullable|string|max:3',
            'existing_text'=> 'nullable|string',
            'instructions' => 'nullable|string|max:1000',
        ]);

        $subject = VeilleSubject::findOrFail($valid['subject_id']);
        $type  = $valid['type']   ?? 'angle';
        $tone  = $valid['tone']   ?? 'journaliste';
        $lang  = $valid['language'] ?? 'fr';
        $action = $valid['action'];
        $existing = trim($valid['existing_text'] ?? '');

        $srcMaterial = "TITRE SUJET:\n" . ($subject->title ?? '') . "\n\n";
        if (!empty($subject->summary)) $srcMaterial .= "RÉSUMÉ SOURCE:\n" . $subject->summary . "\n\n";
        if (!empty($subject->content)) $srcMaterial .= "CONTENU ARTICLE SOURCE:\n" . $subject->content . "\n\n";
        if (!empty($subject->url)) $srcMaterial .= "URL SOURCE: " . $subject->url . "\n";

        $system = $this->systemPromptForType($type, $tone, $lang);
        $user = '';

        switch ($action) {
            case 'generate':
                $user = "Utilise les documents sources suivants comme UNIQUE base de travail (n'invente AUCUNE information non présente) :\n\n"
                    . $srcMaterial . "\n\n"
                    . "Génère un remix type={$type} comme expliqué dans tes instructions.\n"
                    . (!empty($valid['instructions']) ? "Instructions additionnelles: " . $valid['instructions'] : '');
                break;
            case 'fix':
                if ($existing === '') { $user = ''; break; }
                $system = "Tu es un correcteur orthographe/grammaire professionnel en français. Corriger le texte suivant. NE CHANGE PAS le sens, le ton ou la longueur. Retourne UNIQUEMENT le texte corrigé, AUCUN commentaire.";
                $user = "Texte à corriger :\n{$existing}";
                break;
            case 'translate':
                if ($existing === '') { $user = ''; break; }
                $target = ($lang === 'en') ? 'anglais' : 'français';
                $system = "Tu es un traducteur professionnel de qualité broadcast (style AFP). Traduction fidèle, naturelle, adaptée au public {$target}. Retourne UNIQUEMENT le texte traduit.";
                $user = "Traduire en {$target} :\n{$existing}";
                break;
            case 'expand':
                if ($existing === '') { $user = ''; break; }
                $system = "Tu es un journaliste senior. Développe le texte fourni en l'enrichissant. Appuie-toi STRICTEMENT sur le DOCUMENT SOURCE ci-joint pour ajouter du contexte, des détails factuels, une transition logique. Multiplie la taille par ~2. Uniquement en français.";
                $user = "DOCUMENT SOURCE (informations autorisées uniquement) :\n\n" . $srcMaterial . "\n\n---\n\nTEXTE À DÉVELOPPER :\n{$existing}";
                break;
            case 'rephrase':
                if ($existing === '') { $user = ''; break; }
                $toneTxt = self::$TONES[$tone] ?? self::$TONES['journaliste'];
                $system = "Tu es un journaliste éditeur senior. Reformule le texte avec le ton suivant : {$toneTxt}. Ne supprime AUCUNE information. Uniquement reformulation. Retourne texte reformulé UNIQUEMENT.";
                $user = "Texte à reformuler :\n{$existing}";
                break;
            case 'titles':
                $system = $this->systemPromptForType('titres', $tone, $lang);
                $user = "DOCUMENT SOURCE :\n\n" . $srcMaterial . "\n\nPropose 5 titres alternatifs punchy.";
                break;
            default:
                $user = '';
        }

        if ($user === '') {
            return response()->json(['error' => 'action invalide ou texte manquant'], 400);
        }

        $headers = [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ];
        return response()->stream(function () use ($ai, $system, $user, $action, $subject, $type, $tone) {
            @ini_set('output_buffering', 0); @ob_end_flush(); @ob_implicit_flush(1);
            echo "event: start\n";
            echo "data: " . json_encode(['action' => $action, 'subject_id' => $subject->id, 'type' => $type, 'tone' => $tone]) . "\n\n";
            flush();

            $buffer = '';
            $aiOptions = match($action) {
                'generate' => [
                    'temperature'           => 0.55,
                    'top_p'                 => 0.95,
                    'max_completion_tokens' => 2400,
                ],
                'titles'   => [
                    'temperature'           => 0.75,
                    'top_p'                 => 0.9,
                    'max_completion_tokens' => 600,
                ],
                'fix'      => [
                    'temperature'           => 0.1,
                    'top_p'                 => 0.8,
                    'max_completion_tokens' => 2000,
                ],
                default => [
                    'temperature'           => 0.45,
                    'top_p'                 => 0.95,
                    'max_completion_tokens' => 2000,
                ],
            };
            try {
                $ai->streamChat($system, $user, function (string $chunk, bool $done) use (&$buffer) {
                    if ($chunk !== '') {
                        $buffer .= $chunk;
                        echo "event: delta\n";
                        echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                        flush();
                    }
                    if ($done) {
                        echo "event: done\n";
                        echo "data: " . json_encode(['full_text' => $buffer, 'len' => strlen($buffer)]) . "\n\n";
                        flush();
                    }
                }, $aiOptions);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains(strtolower($msg), '401') || str_contains(strtolower($msg), 'invalid api key')) {
                    $msg = "🔑 Clé Groq invalide (401 Unauthorized). Ouvre le fichier .env et colle une vraie clé GROQ_API_KEY commençant par gsk_ .";
                } elseif (str_contains(strtolower($msg), '429') || str_contains(strtolower($msg), 'rate limit')) {
                    $msg = "🚦 Rate limit Groq atteint (429). Réessaie dans 30 secondes.";
                } elseif (str_contains(strtolower($msg), '50') || str_contains(strtolower($msg), 'server error')) {
                    $msg = "🛠 Erreur serveur Groq. Réessaie dans 1 minute.";
                }
                echo "event: error\n";
                echo "data: " . json_encode(['message' => $msg, 'code' => $e->getCode()]) . "\n\n";
                @flush();
            }
        }, 200, $headers);
    }

    public function aiAction(Request $request, AiProviderInterface $ai)
    {
        $this->authorize('create', VeilleRemix::class);
        $valid = $request->validate([
            'subject_id'   => 'required|exists:veille_subjects,id',
            'action'       => 'required|string|max:30',
            'type'         => ['nullable', Rule::in(array_keys(self::$TYPES))],
            'tone'         => ['nullable', Rule::in(array_keys(self::$TONES))],
            'language'     => 'nullable|string|max:3',
            'existing_text'=> 'nullable|string',
            'instructions' => 'nullable|string|max:1000',
        ]);

        $subject = VeilleSubject::findOrFail($valid['subject_id']);
        $type  = $valid['type']   ?? 'angle';
        $tone  = $valid['tone']   ?? 'journaliste';
        $lang  = $valid['language'] ?? 'fr';
        $action = $valid['action'];
        $existing = trim($valid['existing_text'] ?? '');

        $srcMaterial = "TITRE SUJET:\n" . ($subject->title ?? '') . "\n\n";
        if (!empty($subject->summary)) $srcMaterial .= "RÉSUMÉ SOURCE:\n" . $subject->summary . "\n\n";
        if (!empty($subject->content)) $srcMaterial .= "CONTENU ARTICLE SOURCE:\n" . $subject->content . "\n\n";

        $system = $this->systemPromptForType($type, $tone, $lang);
        $user = '';
        switch ($action) {
            case 'generate':
                $user = "DOCUMENT SOURCE :\n\n" . $srcMaterial . "\n\nGénère un remix type={$type}.\n"
                    . (!empty($valid['instructions']) ? "Instructions : " . $valid['instructions'] : '');
                break;
            case 'fix':
                if ($existing === '') break;
                $system = "Correcteur orthographe/grammaire français. Retourne uniquement texte corrigé.";
                $user = $existing;
                break;
            case 'translate':
                if ($existing === '') break;
                $target = ($lang === 'en') ? 'anglais' : 'français';
                $system = "Traducteur professionnel {$target}. Retourne uniquement texte traduit.";
                $user = $existing;
                break;
            default:
                break;
        }
        if ($user === '') return response()->json(['error' => 'paramètres invalides'], 400);
        $text = $ai->chat($system, $user, ['max_tokens' => 4096]);
        return response()->json(['success' => true, 'text' => $text, 'len' => strlen($text)]);
    }
}
