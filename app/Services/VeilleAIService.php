<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VeilleAIService
{
    protected ?string $openAiKey;
    protected ?string $anthropicKey;
    protected ?string $googleKey;
    protected ?string $openRouterKey;

    public function __construct()
    {
        $this->openAiKey = env('OPENAI_API_KEY');
        $this->anthropicKey = env('ANTHROPIC_API_KEY');
        $this->googleKey = env('GOOGLE_API_KEY');
        $this->openRouterKey = env('OPENROUTER_API_KEY');
    }

    public function generateAngles(string $content, int $count = 5): array
    {
        $prompt = "Tu es chef d'édition TV. Génère {$count} angles journalistiques originaux et pertinents pour traiter ce sujet. Format: JSON [{\"title\":...,\"angle\":...,\"public_cible\":...}]";

        return $this->callLLM($prompt, $content) ?? [
            ['title' => 'Angle 1', 'angle' => 'Impact économique', 'public_cible' => 'Grand public'],
            ['title' => 'Angle 2', 'angle' => 'Analyse politique', 'public_cible' => 'Abonnés premium'],
            ['title' => 'Angle 3', 'angle' => 'Témoignages locaux', 'public_cible' => 'Régional'],
        ];
    }

    public function generateTitles(string $content, int $count = 10): array
    {
        $prompt = "Génère {$count} titres accrocheurs pour un sujet TV. Types: JT 20h, réseaux sociaux, bande annonce. Format JSON [{\"titre\":...,\"type\":...,\"ton\":...}]";

        return $this->callLLM($prompt, $content) ?? [
            ['titre' => 'Titre principal percutant', 'type' => 'JT 20h', 'ton' => 'Informatif'],
            ['titre' => 'La révélation du jour', 'type' => 'Bande annonce', 'ton' => 'Suspens'],
            ['titre' => 'Tout comprendre en 1 minute', 'type' => 'Réseaux sociaux', 'ton' => 'Pédagogique'],
        ];
    }

    public function reformulate(string $content, string $style = 'jt20'): string
    {
        $styles = [
            'jt20' => 'Style JT 20h, objectif, 150 mots maximum',
            'chronique' => 'Style chronique éditoriale, ton personnel, 300 mots',
            'social' => 'Style post réseau social, punchy, 120 caractères',
            'presentateur' => 'Texte à l\'oral pour un présentateur, naturel, 1 minute',
            'teaser' => 'Teaser accrocheur pour bande annonce, 20 secondes',
        ];

        $prompt = "Réécris le contenu suivant dans le style: {$styles[$style]}. Contenu: {$content}";
        $result = $this->callLLM($prompt);

        return is_array($result) ? implode(' ', $result) : ($result ?: $content);
    }

    protected function callLLM(string $prompt, ?string $context = null): mixed
    {
        $fullPrompt = $context ? "CONTEXTE: {$context}\n\nINSTRUCTION: {$prompt}" : $prompt;
        $result = null;

        try {
            if ($this->openAiKey) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->openAiKey}",
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.7,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un expert en production télé française. Réponds toujours en JSON valide, sauf si demande contraire.'],
                        ['role' => 'user', 'content' => $fullPrompt],
                    ],
                ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    $decoded = json_decode($content, true);
                    $result = $decoded ?? $content;
                }
            }
        } catch (\Exception $e) {
            Log::warning("[VeilleAI] LLM call failed: {$e->getMessage()}");
        }

        return $result;
    }
}
