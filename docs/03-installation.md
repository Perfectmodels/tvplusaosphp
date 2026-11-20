# 03 — Installation locale

## 1. Prérequis

- PHP **8.3+** (8.5 recommandé ; la production tourne en 8.5.12)
- Extensions : `pdo_mysql` ou `pdo_sqlite`, `mbstring`, `openssl`, `gd`/`imagick`, `intl`, `zip`, `bcmath`
- Composer 2
- Node.js **20+** et npm
- MySQL/MariaDB ou SQLite pour le développement

Vérifier :

```bash
php -v
composer --version
node -v && npm -v
php -m | grep -E 'pdo|mbstring|openssl|gd|intl|zip'
```

## 2. Installation pas à pas

```bash
cd conducteurApp

# Dépendances PHP et JS
composer install
npm install

# Environnement
cp .env.example .env
php artisan key:generate

# Base (SQLite simple pour démarrer)
touch database/database.sqlite
# ou configurer DB_* pour MySQL dans .env

php artisan migrate
php artisan db:seed        # si disponible selon l'état du projet

# Assets
npm run build              # ou npm run dev en développement

# Serveur
php artisan serve
```

Windows : le script `INSTALLER.cmd` à la racine automatise ces étapes.

## 3. Variables d'environnement essentielles

| Variable | Développement | Production |
|---|---|---|
| `APP_NAME` | `TV+AOS` | `TV+AOS` |
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_URL` | `http://localhost:8000` | `https://tvplusaos.com` |
| `APP_KEY` | généré par `key:generate` | inchangé, secret |
| `APP_TIMEZONE` | `Africa/Libreville` | `Africa/Libreville` |
| `DB_CONNECTION` | `sqlite` ou `mysql` | `mysql` |
| `DB_HOST` | — / `127.0.0.1` | hôte MySQL de l'hébergeur |
| `SESSION_DRIVER` | `database` ou `file` | `database` |
| `SESSION_ENCRYPT` | `true` | `true` |
| `SESSION_SECURE_COOKIE` | `false` | `true` |
| `CACHE_STORE` | `database` ou `array` | `database` |
| `QUEUE_CONNECTION` | `sync` | `sync` (mutualisé) |
| `LOG_LEVEL` | `debug` | `warning` |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,127.0.0.1` | `tvplusaos.com,www.tvplusaos.com` |
| `TVPLUS_QR_JWT_SECRET` | valeur de dev | secret distinct en production |

> Ne jamais committer le `.env`. Vérifier `.gitignore`.

## 4. Base de données et rôles

Les migrations créent les tables métier, dont :

- `users`, `sessions`, `cache`, `cache_locks` ;
- `emissions`, `conducteurs`, `sequences`, `sequence_intervenants` ;
- `journalistes`, `intervenants`, `conducteur_presences`, `parcours_intervenants` ;
- `veille_sources`, `veille_subjects`, `veille_remixes` ;
- `workspace_*`, `digital_publications`, `editorial_*` ;
- `inscription_personnel`, `user_account_requests` ;
- `sondages`, `sondage_candidats`, `vote_sondages`.

La matrice des rôles/permissions est définie dans `config/role_access.php` et `config/workspaces.php`. Selon l'état du projet, se référer aux seeders présents dans `database/seeders/`.

## 5. Assets et Vite

```bash
npm run dev     # serveur Vite + HMR
npm run build   # build de production vers public/build
```

Si une vue utilise `@vite(...)` et que le manifest est absent, Laravel lève `ViteException` : lancer `npm run build` (ou `npm run dev`).

## 6. Tests

`phpunit.xml` force `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` : **les tests n'utilisent jamais la base de production**.

```bash
php artisan test
# ou
vendor/bin/phpunit tests/Feature/SondagePublicTest.php
```

Formatage :

```bash
php vendor/bin/pint app tests routes database --format agent
```

## 7. Vérification rapide

```bash
php artisan route:list --except-vendor | head -20
php artisan migrate:status
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:8000/
```

## 8. Problèmes fréquents en local

| Symptôme | Cause probable | Solution |
|---|---|---|
| `ViteException: Unable to locate file in Vite manifest` | Build absent | `npm run build` |
| 500 sur une vue avec `CiviliteEnum could not be converted to string` | Ancien cache de vue | `php artisan view:clear` |
| 403 sur une route métier | Rôle/permission absent | Vérifier `config/role_access.php` et le seeder |
| `Access denied` MySQL | Identifiants ou hôte | Vérifier `DB_*` dans `.env` |
| Session perdue | `SESSION_SECURE_COOKIE=true` en HTTP | Le désactiver en local |
