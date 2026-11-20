# 04 — Déploiement

## 1. Principes

La production `https://tvplusaos.com` est hébergée chez LWS en mutualisé. Les contraintes observées :

- **pas d'accès SSH** ; les échanges se font via FTP ;
- **pas de `npm run build` côté serveur** : les assets Vite sont compilés localement puis uploadés ;
- pas d'exécution distante de `php artisan` via un shell : les migrations peuvent être appliquées via phpMyAdmin (SQL équivalent) ou tout autre mécanisme fourni par l'hébergeur ;
- la racine web contient `index.php`, `.htaccess`, `build/`, `images/`, `pwa-icons/`, `sw.js`, `manifest.webmanifest`, `offline.html` ; le code applicatif est dans `conducteurApp/`.

Toute modification doit être :

1. appliquée dans le dépôt local `archive/conducteurApp` ;
2. envoyée via FTP ;
3. **re-téléchargée et comparée octet par octet** (`diff`) avec la copie locale ;
4. vérifiée en HTTP (statut, contenu, absence de stack trace).

## 2. Fichiers `.htaccess` (racine web)

Règles essentielles en place :

```apache
Options -Indexes -MultiViews
DirectoryIndex index.php
RewriteEngine On
RewriteRule ^\.well-known/acme-challenge/ - [L]
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} !^tvplusaos\.com$ [NC]
RewriteRule ^ https://tvplusaos.com%{REQUEST_URI} [R=301,L]

RewriteRule (^|/)\. - [F,L]
RewriteRule ^(?:conducteurApp|app|vendor|bootstrap|config|database|routes|resources|tests|node_modules)(?:/|$) - [F,L,NC]
RewriteRule \.(?:zip|sql|log|bak|md|xml|ini|env|sqlite)(?:$|/) - [F,L,NC]
RewriteRule ^(?!index\.php$).*\.php(?:/|$) - [F,L,NC]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.php [L]
```

**Conséquences :**

- aucun fichier `.php` autre que `index.php` n'est exécutable à la racine ;
- les fichiers `.xml` sont bloqués (le `sitemap.xml` doit être autorisé explicitement) ;
- les dotfiles (dont `.well-known/security.txt`) sont bloqués sauf `acme-challenge`.

Pour ajouter des en-têtes de cache, placer un `.htaccess` dédié dans `build/` et compléter la racine pour `sw.js`, `manifest.webmanifest`, `offline.html` et les images.

## 3. Procédure de mise à jour du code

```bash
# 1. Sauvegarde locale des fichiers distants avant modification
python3 - <<'PY'
import ftplib
c=ftplib.FTP('ftp.tvplusaos.com'); c.login('UTILISATEUR','MOT_DE_PASSE')
for local, remote in [('.env','/conducteurApp/.env')]:
    with open('backup/'+local,'wb') as f:
        c.retrbinary('RETR '+remote, f.write)
c.quit()
PY

# 2. Modifier les fichiers dans archive/conducteurApp
# 3. Formater et tester en local
php vendor/bin/pint --dirty --format agent
php artisan test --compact

# 4. Upload FTP (binaire)
# 5. Re-téléchargement + diff
```

## 4. Base de données

### Option A — SQL manuel (phpMyAdmin)

Le fichier `sondage-migration.sql` à la racine du dossier de travail donne un exemple complet : `CREATE TABLE IF NOT EXISTS` + insertion des lignes correspondantes dans la table `migrations` pour éviter une recréation ultérieure.

Après exécution, vérifier :

```sql
SHOW TABLES LIKE 'sondages';
SELECT migration FROM migrations ORDER BY id DESC LIMIT 5;
```

### Option B — Artisan si l'hébergeur le permet

```bash
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

En l'absence de cache de configuration/routes sur le serveur (vérifier `bootstrap/cache/`), les modifications de `.env` et de routes sont effectives au prochain hit.

## 5. Configuration de production

Valeurs attendues dans `.env` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tvplusaos.com
LOG_LEVEL=warning
SANCTUM_STATEFUL_DOMAINS=tvplusaos.com,www.tvplusaos.com
SESSION_SECURE_COOKIE=true
```

Ne jamais modifier `APP_KEY` sans migrer les données chiffrées.

## 6. Assets PWA (versionnage)

Les URLs PWA sont versionnées pour contourner le cache :

- `manifest.webmanifest?v=2`
- `sw.js?v=2`
- icônes `/pwa-icons/icon-192.png?v=2`, `icon-512.png?v=2`, `icon-maskable-512.png?v=2`
- favicons `?v=2`

Le service worker porte une version interne (`tvaos-pwa-v4`) ; incrémenter **les deux** à chaque mise à jour.

En-têtes recommandés :

```apache
<FilesMatch "^sw\.js$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Service-Worker-Allowed "/"
</FilesMatch>
```

## 7. Vérifications post-déploiement

```bash
CB=$(date +%s)

# Santé du site
curl -sS -o /dev/null -w '%{http_code}\n' "https://tvplusaos.com/?cb=$CB"

# Pas de debug
curl -sS "https://tvplusaos.com/personnel/inscription/search?q=an&cb=$CB" | grep -c 'Stack Trace'

# Assets
curl -sSI "https://tvplusaos.com/sw.js?cb=$CB" | grep -i 'cache-control'
curl -sSI "https://tvplusaos.com/build/assets/app.css?cb=$CB" | grep -i 'cache-control'

# TLS / ALPN
openssl s_client -connect tvplusaos.com:443 -alpn h2,http/1.1 </dev/null 2>/dev/null | grep 'ALPN protocol'
```

Checklist :

- [ ] pages publiques en 200 ;
- [ ] erreurs 500 sans stack trace ni SQL ;
- [ ] aucun secret visible ;
- [ ] routes protégées : 403 pour un utilisateur non autorisé ;
- [ ] `sitemap.xml` accessible si activé ;
- [ ] service worker mis à jour et installable ;
- [ ] logs sans pic d'erreurs ;
- [ ] diff local/distant identique pour chaque fichier modifié.

## 8. Retour arrière

Conserver les sauvegardes FTP des fichiers remplacés. En cas de problème :

1. restaurer le `.env` sauvegardé ;
2. restaurer les fichiers PHP/Blade ;
3. restaurer la base si une migration a été appliquée (dump préalable) ;
4. purger le cache navigateur/CDN et vérifier le service worker.
