<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$users = User::with('roles')->orderBy('id')->get();

$total   = $users->count();
$actifs  = $users->where('is_active', true)->count();
$devs    = 0;
$force   = 0;

$roles = DB::table('model_has_roles as mhr')
    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
    ->select('r.name as role', DB::raw('COUNT(*) as n'))
    ->groupBy('r.name')->orderByDesc('n')->get();

$lignes = [];
foreach ($users as $u) {
    $mdpDefaut = Hash::check('password', $u->password);
    if ($mdpDefaut) { $devs++; }
    if (! $u->password_changed_at) { $force++; }
    $lignes[] = [
        'id'     => (int) $u->id,
        'nom'    => (string) $u->name,
        'email'  => (string) $u->email,
        'role'   => $u->getRoleNames()->implode(', ') ?: '(aucun)',
        'actif'  => $u->is_active ? 'oui' : 'NON',
        'mdp'    => $mdpDefaut ? '`password`' : 'hash bcrypt',
        'force'  => $u->password_changed_at ? 'non' : '**oui**',
    ];
}

// ============================ GÉNÉRATION DU MARKDOWN ============================
$md = [];
$md[] = '# 👥 Utilisateurs — TV+OS (base `bd_conducteur_tv`)';
$md[] = '';
$md[] = '> **Généré le : ' . date('d/m/Y à H:i') . '**';
$md[] = '> Source : `conducteurApp/database/seeders/DatabaseSeeder.php` + `database/factories/UserFactory.php`';
$md[] = '';
$md[] = '---';
$md[] = '';
$md[] = '## 1. Synthèse';
$md[] = '';
$md[] = '| Indicateur | Valeur |';
$md[] = '|---|---|';
$md[] = '| **Total comptes** | **' . $total . '** |';
$md[] = '| Actifs | ' . $actifs . ' |';
$md[] = '| Inactifs | ' . ($total - $actifs) . ' |';
$md[] = '| **Mot de passe `password`** | **' . $devs . ' / ' . $total . '** |';
$md[] = '| Créés via le **seed** | ' . User::whereNull('created_by')->count() . ' |';
$md[] = '| Créés via le **CRUD admin** | ' . User::whereNotNull('created_by')->count() . ' |';
$md[] = '| Changement de MDP forcé en attente | ' . $force . ' |';
$md[] = '';
$md[] = '### Répartition par rôle';
$md[] = '';
$md[] = '| Rôle | Effectif |';
$md[] = '|---|---|';
foreach ($roles as $r) {
    $md[] = '| `' . $r->role . '` | ' . $r->n . ' |';
}
$md[] = '| *(sans rôle)* | ' . User::whereDoesntHave('roles')->count() . ' |';
$md[] = '';

$md[] = '---';
$md[] = '';
$md[] = '## 2. 🔑 Comptes de référence';
$md[] = '';
$md[] = '| ID | Nom | Email | Rôle | Mot de passe |';
$md[] = '|---|---|---|---|---|';
$md[] = '| 1 | Administrateur | `admin@tvplusos.tv` | `admin` | `password` |';
$md[] = '| 2 | Christian Ekogha | `christian.ekogha@tvplusos.tv` | `chef_edition` | `password` |';
$md[] = '| — | Directeur Général | `dg@tvplusos.tv` | `directeur_general` | `password` |';
$md[] = '';
$md[] = '```';
$md[] = 'URL       : http://127.0.0.1:8099/login';
$md[] = 'Identifiant : admin@tvplusos.tv';
$md[] = 'Mot de passe : password';
$md[] = '```';
$md[] = '';

$md[] = '---';
$md[] = '';
$md[] = '## 3. 🔒 Points de sécurité';
$md[] = '';
$md[] = '| Point | Détail | Risque |';
$md[] = '|---|---|---|';
$md[] = '| **Un seul mot de passe pour tous** | `password` pour ' . $devs . ' comptes | 🔴 **Critique** si la base est exposée |';
$md[] = '| **Comptes de démonstration** | 90 comptes Faker (`@example.org/net/com`) | 🟠 À désactiver en production |';
$md[] = '| **Flag `password_changed_at`** | ' . $force . ' comptes doivent encore changer leur MDP | 🟡 Mécanisme actif |';
$md[] = '| **Flag posé à tort** | Le seed force `password_changed_at = now()` sans changer le MDP | 🟠 Le système croit le MDP changé alors que non |';
$md[] = '| `.env` | `APP_DEBUG=true` + **clé `GROQ_API_KEY` réelle en clair** | 🔴 À révoquer si le dépôt est partagé |';
$md[] = '| Secret QR | `TVPLUS_QR_JWT_SECRET` = valeur par défaut | 🟠 À changer |';
$md[] = '';

$md[] = '### ✅ Actions recommandées avant mise en production';
$md[] = '';
$md[] = '1. **Révoquer la clé API Groq** et en générer une nouvelle';
$md[] = '2. Passer `APP_DEBUG=false` et `APP_ENV=production`';
$md[] = '3. **Réinitialiser les mots de passe** avec des valeurs uniques par compte';
$md[] = '4. Forcer le changement de MDP (`password_changed_at = NULL`) pour les ' . $force . ' comptes concernés';
$md[] = '5. Désactiver ou supprimer les 90 comptes de démonstration Faker';
$md[] = '6. Changer `TVPLUS_QR_JWT_SECRET`';
$md[] = '';

$md[] = '---';
$md[] = '';
$md[] = '## 4. 📋 Liste complète des comptes';
$md[] = '';
$md[] = '> `MDP` = mot de passe vérifié par `Hash::check()` sur la base.';
$md[] = '> `Forcé` = `password_changed_at` NULL → l\'utilisateur sera redirigé vers le changement de mot de passe à la 1ʳᵉ connexion.';
$md[] = '';
$md[] = '| ID | Nom | Email | Rôle | Actif | MDP | Changement forcé |';
$md[] = '|---:|---|---|---|:---:|---|:---:|';
foreach ($lignes as $l) {
    $md[] = '| ' . $l['id'] . ' | ' . str_replace('|', '\|', $l['nom']) . ' | `' . $l['email'] . '` | `' . $l['role'] . '` | ' . $l['actif'] . ' | ' . $l['mdp'] . ' | ' . $l['force'] . ' |';
}
$md[] = '';

$md[] = '---';
$md[] = '';
$md[] = '## 5. 🧩 Rôles disponibles (Spatie Permission)';
$md[] = '';
$md[] = 'Rôles créés par `DatabaseSeeder` :';
$md[] = '';
$md[] = '| Rôle | Usage |';
$md[] = '|---|---|';
$md[] = '| `admin` | Administration complète |';
$md[] = '| `directeur_general` | Direction — accès total (`ConducteurPolicy::before`) |';
$md[] = '| `chef_edition` | Crée et édite les conducteurs + séquences |';
$md[] = '| `chef_redaction` | Équivalent chef d\'édition |';
$md[] = '| `journaliste` | Édite ses propres sujets dans le Conducteur de Rédaction |';
$md[] = '| `presentateur` | Notes + séquence active + compte à rebours **uniquement** |';
$md[] = '| `habilleur` | Colonnes habillage |';
$md[] = '| `chef_plateau` | Logistique plateau |';
$md[] = '| `receptionniste` | Accueil — scan QR |';
$md[] = '| `lecture` | Consultation seule |';
$md[] = '';

file_put_contents(__DIR__ . '/../users.md', implode("\n", $md) . "\n");
echo 'users.md généré : ' . count($md) . ' lignes' . PHP_EOL;
echo 'Comptes listés  : ' . $total . PHP_EOL;
