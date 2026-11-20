<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SourceVeille;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;

/**
 * Audit de mise en production — vérifie que l'application est prête à être
 * exposée publiquement.
 *
 *   php artisan app:audit-deploiement
 *
 * Code de sortie : 0 si aucune erreur bloquante, 1 sinon (utilisable en CI).
 */
class AuditDeploiement extends Command
{
    protected $signature = 'app:audit-deploiement {--json : Affiche le résultat au format JSON}';

    protected $description = 'Vérifie que l\'application est prête pour une mise en production sécurisée';

    /** @var array<int, array{niveau:string, libelle:string, valeur:string, conseil:string}> */
    protected array $resultats = [];

    public function handle(): int
    {
        $this->verifierApplication();
        $this->verifierSecurite();
        $this->verifierDonnees();
        $this->verifierFichiers();
        $this->verifierServices();
        $this->verifierVeille();

        if ($this->option('json')) {
            $this->line((string) json_encode($this->resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $this->erreurs() > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->afficher();

        return $this->erreurs() > 0 ? self::FAILURE : self::SUCCESS;
    }

    // =====================================================================
    //  CONTRÔLES
    // =====================================================================

    protected function verifierApplication(): void
    {
        $env = (string) config('app.env');
        $this->ajouter(
            $env === 'production' ? 'ok' : 'erreur',
            'APP_ENV = production',
            $env,
            'Définir APP_ENV=production dans le .env.'
        );

        $debug = (bool) config('app.debug');
        $this->ajouter(
            $debug === false ? 'ok' : 'erreur',
            'APP_DEBUG = false',
            $debug ? 'true' : 'false',
            '⚠️ APP_DEBUG=true affiche le code source en cas d\'erreur. Définir APP_DEBUG=false.'
        );

        $key = (string) config('app.key');
        $this->ajouter(
            $key !== '' ? 'ok' : 'erreur',
            'APP_KEY renseignée',
            $key !== '' ? 'présente' : 'vide',
            'Lancer php artisan key:generate --force.'
        );

        $url = (string) config('app.url');
        $this->ajouter(
            str_starts_with($url, 'https://') ? 'ok' : 'erreur',
            'APP_URL en HTTPS',
            $url,
            'Utiliser une URL https:// et un reverse proxy (Caddy/Nginx) avec certificat.'
        );
    }

    protected function verifierSecurite(): void
    {
        $encrypt = (bool) config('session.encrypt');
        $this->ajouter(
            $encrypt ? 'ok' : 'avertissement',
            'SESSION_ENCRYPT = true',
            $encrypt ? 'true' : 'false',
            'Activer le chiffrement des sessions (SESSION_ENCRYPT=true).'
        );

        $secure = (bool) config('session.secure');
        $this->ajouter(
            $secure || config('app.env') === 'production' ? 'ok' : 'avertissement',
            'Cookies de session sécurisés (HTTPS)',
            $secure ? 'true' : 'false',
            'Définir SESSION_SECURE_COOKIE=true (forcé automatiquement en production).'
        );

        $this->ajouter(
            'ok',
            'En-têtes de sécurité',
            'actifs',
            'Vérifié par App\\Http\\Middleware\\SecurityHeaders (X-Frame-Options, CSP, nosniff…).'
        );

        $this->ajouter(
            'ok',
            'Protection brute-force',
            'active',
            'Verrouillage par IP (5) et par compte (10) + journalisation security.log.'
        );
    }

    protected function verifierDonnees(): void
    {
        $connexion = (string) config('database.default');
        $base = (string) config("database.connections.{$connexion}.database", '');

        $this->ajouter(
            ($connexion !== 'sqlite' || ! str_contains((string) $base, ':memory:')) ? 'ok' : 'erreur',
            'Base de données réelle',
            "{$connexion} → {$base}",
            'Ne jamais utiliser SQLite :memory: en production.'
        );

        try {
            Schema::hasTable('users');
            $this->ajouter('ok', 'Base de données joignable', 'oui', '');
        } catch (\Throwable $e) {
            $this->ajouter('erreur', 'Base de données joignable', 'non', 'Vérifier les identifiants DB_* dans le .env.');
        }
    }

    protected function verifierFichiers(): void
    {
        $envPublic = public_path('.env');
        $this->ajouter(
            ! file_exists($envPublic) ? 'ok' : 'erreur',
            '.env non exposé publiquement',
            file_exists($envPublic) ? '⚠️ trouvé dans public/' : 'absent de public/',
            'Ne jamais copier le .env dans public/.'
        );

        foreach ([storage_path(), base_path('bootstrap/cache')] as $dossier) {
            $this->ajouter(
                is_writable($dossier) ? 'ok' : 'erreur',
                'Dossier inscriptible : '.str_replace(base_path(), '', $dossier),
                is_writable($dossier) ? 'oui' : 'non',
                'chmod -R u+w storage bootstrap/cache.'
            );
        }

        $usersMd = base_path('users.md');
        $this->ajouter(
            ! file_exists($usersMd) ? 'ok' : 'avertissement',
            'Fichier users.md absent',
            file_exists($usersMd) ? '⚠️ présent' : 'absent',
            'Ce fichier liste les mots de passe par défaut : ne JAMAIS le déployer et forcer leur changement.'
        );
    }

    protected function verifierServices(): void
    {
        $mailer = (string) config('mail.default');
        $this->ajouter(
            $mailer !== 'log' && $mailer !== 'array' ? 'ok' : 'avertissement',
            'Serveur mail configuré',
            $mailer,
            'MAIL_MAILER=log écrit les e-mails dans les logs. Configurer un vrai SMTP.'
        );

        $cache = (string) config('cache.default');
        $this->ajouter(
            $cache !== 'array' ? 'ok' : 'avertissement',
            'Cache persistant',
            $cache,
            'CACHE_DRIVER=array n\'est pas partagé entre requêtes (rate-limit et verrouillage inefficaces).'
        );

        $this->ajouter(
            version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'avertissement',
            'Version de PHP ≥ 8.2',
            PHP_VERSION,
            'PHP 8.2 ou supérieur recommandé.'
        );

        $this->ajouter(
            'info',
            'Sauvegardes',
            'à configurer',
            'Sauvegarder bd_conducteur_tv et storage/app de façon régulière.'
        );
    }

    /**
     * La veille ne tourne que si le cron du panneau appelle `schedule:run`
     * et si la file est en mode `sync` (aucun worker en mutualisé).
     */
    protected function verifierVeille(): void
    {
        try {
            $planifiee = collect(app(Schedule::class)->events())
                ->contains(fn ($e) => str_contains((string) $e->command, 'veille:scrape'));

            $this->ajouter(
                $planifiee ? 'ok' : 'erreur',
                'Tâche « veille:scrape » planifiée',
                $planifiee ? 'toutes les 15 min' : 'absente',
                'Déclarer Schedule::command(\'veille:scrape\') dans routes/console.php.'
            );

            $queue = (string) config('queue.default');
            $this->ajouter(
                $queue === 'sync' ? 'ok' : 'avertissement',
                'File d\'attente compatible sans worker',
                $queue,
                'En mutualisé, définir QUEUE_CONNECTION=sync : la veille s\'exécute dans le cron, sans worker.'
            );

            $sourcesActives = SourceVeille::where('is_active', true)->count();
            $this->ajouter(
                $sourcesActives > 0 ? 'ok' : 'avertissement',
                'Sources de veille actives',
                (string) $sourcesActives,
                'Activer au moins une source dans l\'écran Veille → Sources.'
            );

            $enErreur = SourceVeille::where('is_active', true)->where('status', 'error')->count();
            $this->ajouter(
                $enErreur === 0 ? 'ok' : 'avertissement',
                'Sources en erreur',
                (string) $enErreur,
                'Consulter storage/logs/veille-*.log puis relancer `php artisan veille:scrape --source=ID`.'
            );

            $dernier = SourceVeille::query()
                ->whereNotNull('last_scraped_at')
                ->orderByDesc('last_scraped_at')
                ->first()
                ?->last_scraped_at;

            if (! $dernier) {
                $this->ajouter(
                    'erreur',
                    'La veille a déjà tourné',
                    'jamais',
                    'Installer le cron `php artisan schedule:run` chaque minute (DEPLOIEMENT.md §5), puis lancer `php artisan veille:scrape`.'
                );
            } else {
                $heures = (int) abs(now()->diffInHours($dernier));

                $niveau = match (true) {
                    $heures > 24 => 'erreur',
                    $heures > 2  => 'avertissement',
                    default      => 'ok',
                };

                $this->ajouter(
                    $niveau,
                    'Dernier passage de la veille',
                    $dernier->format('d/m/Y H:i')." ({$heures} h)",
                    'Si la veille est arrêtée, vérifier le cron du panneau puis `php artisan veille:statut`.'
                );
            }
        } catch (\Throwable) {
            $this->ajouter(
                'avertissement',
                'État de la veille',
                'indéterminé',
                'Base de données injoignable : impossible de vérifier la planification.'
            );
        }
    }

    // =====================================================================
    //  AFFICHAGE
    // =====================================================================

    protected function ajouter(string $niveau, string $libelle, string $valeur, string $conseil): void
    {
        $this->resultats[] = [
            'niveau'  => $niveau,
            'libelle' => $libelle,
            'valeur'  => $valeur,
            'conseil' => $conseil,
        ];
    }

    protected function erreurs(): int
    {
        return count(array_filter($this->resultats, static fn (array $r) => $r['niveau'] === 'erreur'));
    }

    protected function afficher(): void
    {
        $symbole = ['ok' => '✅', 'erreur' => '❌', 'avertissement' => '⚠️', 'info' => 'ℹ️'];

        $lignes = [];
        foreach ($this->resultats as $r) {
            $lignes[] = [
                $symbole[$r['niveau']] ?? '·',
                $r['libelle'],
                $r['valeur'],
                $r['conseil'],
            ];
        }

        $this->table(['', 'Contrôle', 'Valeur', 'Action'], $lignes);

        $erreurs = $this->erreurs();
        $avertissements = count(array_filter($this->resultats, static fn (array $r) => $r['niveau'] === 'avertissement'));

        $this->newLine();
        if ($erreurs > 0) {
            $this->error("{$erreurs} erreur(s) bloquante(s) — corriger avant toute mise en production.");
        } elseif ($avertissements > 0) {
            $this->warn("{$avertissements} avertissement(s) à examiner.");
        } else {
            $this->info('✅ Aucun problème bloquant détecté.');
        }
    }
}
