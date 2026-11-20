<?php

namespace Database\Seeders;

use App\Models\VeilleSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VeilleSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            [
                'title'   => 'Sommet UA',
                'status'  => 'approved',
                'summary' => 'Suivi des résolutions et des discours principaux lors du 37ème sommet de l\'Union Africaine.',
                'content' => "Le 37ème sommet de l'Union Africaine s'est tenu à Addis-Abeba avec pour thème central la paix et la sécurité sur le continent. Les chefs d'État ont adopté une feuille de route commune sur la gestion des crises dans la région des Grands Lacs et du Sahel.",
                'category'  => 'international',
                'priority'  => 9,
                'promoted_to_conducteur' => true,
                'published_at' => now()->subMinutes(15),
                'scraped_at'   => now()->subMinutes(5),
                'source_id'    => 1,
            ],
            [
                'title'   => 'Crise du riz',
                'status'  => 'pending',
                'summary' => 'Analyse de l\'impact des restrictions d\'exportation sur les marchés locaux et l\'inflation.',
                'content' => "Plusieurs pays exportateurs asiatiques ont annoncé des restrictions temporaires sur les exportations de riz, provoquant une hausse des prix de 12% en une semaine sur les marchés ouest-africains. Le Gabon a activé son stock stratégique national.",
                'category'  => 'economie',
                'priority'  => 8,
                'published_at' => now()->subHours(2),
                'scraped_at'   => now()->subHours(2),
                'source_id'    => 2,
            ],
            [
                'title'   => 'Élections régionales',
                'status'  => 'archived',
                'summary' => 'Rétrospective des résultats et couverture médiatique des dernières élections.',
                'content' => "Les élections régionales du 12 février dernier ont été marquées par un taux de participation record de 68%. Les résultats définitifs ont été proclamés par la Cour constitutionnelle le 20 février.",
                'category'  => 'politique',
                'priority'  => 5,
                'published_at' => now()->subDays(20),
                'scraped_at'   => now()->subDays(12),
                'source_id'    => 3,
            ],
            [
                'title'   => 'COP 29',
                'status'  => 'approved',
                'summary' => 'Surveillance des engagements climatiques et des réactions des ONG.',
                'content' => "La 29ème conférence des parties sur le climat (COP 29) débute à Bakou. Le Gabon présente sa contribution nationale déterminée actualisée avec un objectif de neutralité carbone d'ici 2060.",
                'category'  => 'international',
                'priority'  => 10,
                'promoted_to_conducteur' => false,
                'published_at' => now()->subMinutes(45),
                'scraped_at'   => now()->subMinutes(5),
                'source_id'    => 1,
            ],
        ];

        foreach ($defs as $def) {
            $slugFn = function (string $base) {
                $slug = Str::slug($base);
                $cand = $slug; $i = 1;
                while (VeilleSubject::where('slug', $cand)->exists()) {
                    $cand = $slug.'-'.$i++;
                }
                return $cand;
            };
            $def['slug'] = $slugFn($def['title']);
            VeilleSubject::firstOrCreate(
                ['title' => $def['title'], 'source_id' => $def['source_id']],
                $def
            );
        }
    }
}
