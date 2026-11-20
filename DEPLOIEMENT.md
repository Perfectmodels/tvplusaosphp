# 🚀 Mise en production — TV+ A OS

> **Document historique.** Pour l'état actuel (hébergement LWS sans SSH, FTP, domaine `tvplusaos.com`, Laravel 12.68), consulter [`docs/04-deploiement.md`](docs/04-deploiement.md).

Checklist de déploiement sécurisé. Chaque section doit être validée avant de
passer à la suivante.

---

## 1. Prérequis

- PHP **≥ 8.2** avec les extensions : `pdo_mysql`, `mbstring`, `openssl`, `gd` ou `imagick`, `intl`, `zip`
- MySQL / MariaDB (base `bd_conducteur_tv`)
- Un reverse proxy HTTPS (**Caddy** ou **Nginx + certbot**) — Laravel ne doit **jamais** être exposé en HTTP seul
- Accès SSH pour déployer

---

## 2. Variables d'environnement (`.env`)

Copier `.env.example` et **remplacer** ces valeurs :

```dotenv
APP_ENV=production
APP_DEBUG=false                      # ⚠️ CRITIQUE : true affiche le code source
APP_URL=https://tvplusos.tv          # HTTPS obligatoire
APP_KEY=                             # puis : php artisan key:generate --force

# Base de données (compte dédié, droits minimaux)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bd_conducteur_tv
DB_USERNAME=tvplusos                 # ⚠️ PAS root
DB_PASSWORD=…                        # long, unique

# Sessions
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true                 # chiffrement du contenu de session
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Cache & files
CACHE_DRIVER=database                # partagé entre requêtes (rate-limit efficace)
QUEUE_CONNECTION=sync                # aucun worker en mutualisé (veille exécutée par le cron)

# E-mails (⚠️ MAIL_MAILER=log = e-mails perdus dans les logs)
MAIL_MAILER=smtp
MAIL_HOST=…
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="ne-pas-repondre@tvplusos.tv"
MAIL_FROM_NAME="TV+ A OS"

# Logs
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

> Ne **jamais** committer le `.env`. Vérifier qu'il est bien listé dans `.gitignore`.

---

## 3. Déploiement

```bash
# 1. Code + dépendances (pas de dev en production)
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Clé d'application
php artisan key:generate --force

# 3. Base de données
php artisan migrate --force

# 4. Rôles & permissions
php artisan db:seed --class=RolePermissionSeeder --force

# 5. Cache de configuration (IMPORTANT : le .env n'est plus lu ensuite)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Droits
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 4. Audit automatique

```bash
php artisan app:audit-deploiement
```

Renvoie `0` si tout est conforme, `1` sinon (utilisable dans une CI).
Chaque ligne indique l'action à mener.

**Tous les ❌ doivent être corrigés avant d'ouvrir l'accès.**

---

## 5. Planificateur (veille automatique)

Sans cette étape, le scraping de la veille **ne tourne jamais**. Le job est
exécuté en **synchrone** (`QUEUE_CONNECTION=sync`) : aucun worker, aucun
processus permanent — compatible avec un hébergement mutualisé.

> ⚙️ `QUEUE_CONNECTION` doit valoir **`sync`**. En mode `database`, le job
> resterait bloqué dans la table `jobs` faute de worker.

### 5.1 Panneau d'hébergement (cPanel / Plesk)

Créer **une tâche planifiée toutes les minutes** (une seule suffit, `schedule:run`
gère ensuite les fréquences déclarées dans `routes/console.php`) :

```cron
* * * * * /usr/local/bin/php /home/COMPTE/tvplusos/conducteurApp/artisan schedule:run >> /home/COMPTE/tvplusos/conducteurApp/storage/logs/cron.log 2>&1
```

- Remplacer `/usr/local/bin/php` par le binaire réel (souvent
  `/opt/cpanel/ea-php82/root/usr/bin/php`) — le trouver avec `which php` en SSH,
  ou l'onglet « Informations PHP » du panneau.
- Remplacer `/home/COMPTE/tvplusos/conducteurApp` par le chemin absolu du projet.
- Si le panneau n'offre qu'une granularité de **5 minutes**, ce n'est pas bloquant :
  `veille:scrape` respecte la fréquence propre à chaque source.

### 5.2 Serveur dédié / VPS (cron système)

```cron
* * * * * cd /var/www/tvplusos/conducteurApp && php artisan schedule:run >> storage/logs/cron.log 2>&1
```

**Windows (poste de développement)**

- Déclencheur : *toutes les 1 minute*, au démarrage, sans connexion
- Action : `php.exe artisan schedule:run`
- Répertoire : `C:\chemin\vers\conducteurApp`

### 5.3 Vérification

```bash
php artisan schedule:list      # la tâche veille:scrape doit apparaître (toutes les 15 min)
php artisan schedule:test      # exécute la tâche immédiatement
php artisan veille:statut      # dernier passage, sources dues, prochain réveil
```

Après quelques minutes, `storage/logs/veille-YYYY-MM-DD.log` doit se remplir.
Si `veille:statut` répond **1** (« veille arrêtée »), c'est que le cron n'appelle
pas `schedule:run` — `php artisan app:audit-deploiement` le signale aussi.

> ❌ Ne pas utiliser `php artisan schedule:work` ni `queue:work` en mutualisé :
> ces processus permanents sont tués par l'hébergeur.

---

## 6. Comptes initiaux

1. ⚠️ **Ne pas déployer `users.md`** (mots de passe par défaut).
2. Se connecter avec un compte administrateur.
3. **Forcer le changement de mot de passe** pour tous les comptes — le middleware
   `password.changed` redirige automatiquement les comptes non modifiés.
4. Désactiver les comptes inutilisés.

---

## 7. Vérifications post-déploiement

| Contrôle | Comment |
|---|---|
| HTTPS actif | Le navigateur affiche 🔒 et redirige bien `http://` → `https://` |
| En-têtes | `curl -I https://tvplusos.tv` → `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy` présents |
| Erreurs masquées | Provoquer une erreur → aucune trace de code source |
| `.env` inaccessible | `curl https://tvplusos.tv/.env` → **404** |
| Connexion | Se connecter / se déconnecter, vérifier `storage/logs/security-*.log` |
| Verrouillage | 6 mauvais mots de passe → blocage |
| Veille | `php artisan veille:scrape --now` remonte bien des sujets |
| Invitation QR | Ouvrir `https://tvplusos.tv/i/<jeton>` d'un intervenant test → page mobile sans connexion |
| WhatsApp | Cliquer « Envoyer WhatsApp » → WhatsApp s'ouvre avec le message pré-rempli (CSP `form-action` autorise `wa.me`) |
| Sauvegarde | Une restauration testée au moins une fois |

---

## 8. Maintenance

| Fréquence | Action |
|---|---|
| Chaque jour | Consulter `storage/logs/security-*.log` |
| Chaque semaine | `composer audit` et `npm audit` |
| Chaque mois | Sauvegarde testée, mises à jour de sécurité PHP |
| En continu | Sauvegardes automatiques de `bd_conducteur_tv` + `storage/app` |

---

## 9. En cas d'incident

1. **Isoler** : suspendre l'accès (reverse proxy → 503).
2. **Journaliser** : sauvegarder `storage/logs/` **avant** toute intervention.
3. **Analyser** : `security.log` liste connexions, échecs et verrouillages (IP + User-Agent).
4. **Révoquer** : forcer la réinitialisation des mots de passe concernés.
5. **Restaurer** : dernière sauvegarde saine + migrations à jour.

---

## 10. Invitations QR / WhatsApp

Chaque intervenant possède un **QR permanent** encodant
`https://tvplusos.tv/i/<jeton>` (jeton 48 caractères, régénérable depuis la fiche).

| Point | Détail |
|---|---|
| Envoi | Bouton « WhatsApp » sur le parcours ou la fiche → lien `wa.me` pré-rempli (aucun compte Business requis) |
| Fenêtre | 7 jours à compter de chaque envoi ; un renvoi la relance. Le QR, lui, ne périme jamais |
| Confirmation | **Par scan** : un utilisateur connecté scanne le QR (page « Scanner QR », poste **Invitation**) → étape « Confirmé » + placement automatique dans le conducteur |
| Page publique | Simple fiche + QR à présenter (sans bouton de confirmation) |
| Régénération | « Régénérer » sur la page QR invalide immédiatement l'ancien lien |
| `APP_URL` | **Doit** être l'URL HTTPS publique — c'est elle qui est écrite dans le QR |
| PNG | Rendu Imagick si disponible, repli automatique sur GD (aucune installation requise) |

> ⚠️ Après un changement de domaine, régénérer les QR (ou mettre à jour `APP_URL`)
> puis renvoyer les invitations : les anciens QR pointent vers l'ancien domaine.
