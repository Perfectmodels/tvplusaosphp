# 🔒 Rapport de sécurité — TV+ A OS

> **Document historique.** Pour l'état actuel et l'audit de production de novembre 2026, consulter [`docs/11-securite-audit.md`](docs/11-securite-audit.md).

État de la sécurité applicative, les failles corrigées et les contrôles en place.

*Dernière mise à jour : 26 octobre 2026*

---

## 1. Synthèse exécutive

| Domaine | État |
|---|---|
| API exposée sans authentification | ✅ Corrigé (faille critique) |
| SSRF (lecture de ressources internes) | ✅ Corrigé |
| En-têtes de sécurité HTTP | ✅ En place |
| Protection anti force-brute | ✅ Renforcée (IP **et** compte) |
| Journalisation de sécurité | ✅ En place (180 jours) |
| Validation des fichiers | ✅ En place (SVG banni) |
| Chiffrement des sessions | ✅ Activé |
| Mots de passe | ✅ Politique forte + verrouillage |
| Environnement de production | ⚠️ À configurer (voir §6) |

**157 tests · 553 assertions · 0 échec**, dont **14 tests dédiés à la sécurité**.

---

## 2. 🔴 Faille critique corrigée

### API `/api/v1/*` accessible sans authentification

Toutes les routes du groupe `/api/v1` étaient publiques, alors qu'elles permettaient notamment :

| Route | Risque |
|---|---|
| `POST /api/v1/veille/scrape-url` | **SSRF** — faire récupérer au serveur l'URL de son choix (`http://169.254.169.254/`, métadonnées cloud, réseau interne) **et écrire en base** |
| `POST /api/v1/veille/ia-action` | Consommation abusive d'appels IA |
| `GET /api/v1/intervenants/liste` | Tentative d'extraction de données |
| `POST /api/v1/veille/promote/{id}` | Modification de données |

**Correction** : `auth:sanctum` + `throttle:60,1` sur l'ensemble du groupe.
**Test** : `test_l_api_v1_refuse_les_requetes_non_authentifiees`.

---

## 3. Protections mises en place

### 3.1 En-têtes de sécurité
`app/Http/Middleware\SecurityHeaders.php`, appliqué à toutes les réponses web.

| En-tête | Rôle |
|---|---|
| `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'` | Anti-clickjacking |
| `X-Content-Type-Options: nosniff` | Anti MIME-sniffing |
| `Referrer-Policy: strict-origin-when-cross-origin` | Pas de fuite d'URL interne |
| `Permissions-Policy` | Caméra/micro limités au site (scanner QR préservé) |
| `Cross-Origin-Opener-Policy` / `Cross-Origin-Resource-Policy` | Isolation des ressources |
| `Content-Security-Policy` | `default-src 'self'`, `object-src 'none'`, `base-uri`, `form-action` |
| `Strict-Transport-Security` | HTTPS forcé (émis uniquement sur HTTPS) |

> La CSP autorise explicitement les seuls CDN utilisés : Google Fonts, Font Awesome, SweetAlert2, html5-qrcode.

### 3.2 Anti-SSRF
`app/Rules\UrlPublique.php` — refuse :

- les schémas non web (`file://`, `gopher://`, `ftp://`, `data://`) ;
- `localhost`, `0.0.0.0`, `::1`, `*.local`, `*.internal`, `*.lan`, `metadata.google.internal` ;
- les IP littérales privées/réservées (`127.x`, `10.x`, `192.168.x`, `172.16-31.x`, `169.254.x`) ;
- les IP encodées (`0x7f000001`, `2130706433`, `127.1`).

Le `ScraperService` refuse par ailleurs tout schéma non http/https (défense en profondeur).

### 3.3 Validation des fichiers
`app/Services/MediaValidation.php`, appliqué aux collections Media Library
(`Journaliste`, `Intervenant`, `Media`) :

| Contrainte | Portrait | Documents |
|---|---|---|
| Types | jpeg, png, webp, gif, avif | pdf, images, bureautique, texte |
| Taille max | 5 Mo | 15 Mo |
| **SVG** | 🚫 refusé | 🚫 refusé |

> Le SVG est un vecteur XSS classique (`<svg onload=…>`) : il est banni même
> s'il est déclaré comme image.

### 3.4 Anti force-brute & verrouillage de compte
`app/Http/Requests/Auth/LoginRequest.php` :

- **par IP + e-mail** : 5 tentatives ;
- **par COMPTE** (toutes IP confondues) : 10 tentatives → bloque un attaquant
  qui change d'adresse ;
- blocage **15 minutes**, évènement `Lockout` émis et journalisé.

### 3.5 Journalisation de sécurité
Canal dédié `security` → `storage/logs/security-*.log`, **conservé 180 jours**.
`app/Services/SecurityLogger.php` trace :

| Évènement | Contexte enregistré |
|---|---|
| `connexion.reussie` | e-mail, identifiant, IP, User-Agent, URL |
| `connexion.echouee` | idem + motif (identifiants invalides / compte désactivé) |
| `compte.verrouille` | idem + durée du blocage |
| `deconnexion` | idem |
| `mot_de_passe.change` | idem |

La journalisation est protégée par `try/catch` : elle ne fait **jamais** échouer
une requête.

### 3.6 Sessions
- `SESSION_ENCRYPT=true` → contenu de session chiffré.
- Cookies `http_only`, `same_site=lax`, `secure` (forcé en production).

### 3.7 Mots de passe
- Politique forte : `min:8` + majuscules + chiffres + symboles.
- `dontFlash` sur `password` / `password_confirmation`.
- Middleware `password.changed` : force le changement à la première connexion.

### 3.8 Routes publiques d'invitation (`/i/*`)
- **Hors authentification** (nécessaire depuis le téléphone de l'intervenant), mais :
  - jeton aléatoire de **48 caractères**, unique, régénérable (l'ancien est invalidé) ;
  - `throttle:30,1` sur les trois routes ;
  - `X-Robots-Tag: noindex, nofollow` ;
  - vérification stricte que le parcours confirmé appartient bien au porteur du jeton ;
  - aucune donnée sensible (téléphone, e-mail) affichée ; le QR y est mis à disposition.
- La **CONFIRMATION n'est pas publique** : elle est validée par un utilisateur authentifié
  via le scan (page « Scanner QR », poste `Invitation`) — `ScannerQrController` :
  - `auth` + `verified` + policy `viewAny` sur `Intervenant` ;
  - confirmation **idempotente** ; chaque scan tracé (`IntervenantScanLog` + IP/User-Agent) ;
  - l'opérateur est enregistré (`last_trigger_user_id`).
- `form-action` du CSP autorise explicitement `https://wa.me` (sinon le navigateur
  bloque la redirection des formulaires « Envoyer WhatsApp »).

---

## 4. Contrôles déjà présents (vérifiés)

- ✅ Rate-limit de connexion (Breeze, complété par le verrouillage par compte)
- ✅ CSRF sur toutes les routes web
- ✅ Échappement Blade (`{{ }}`) — les seuls `{!! !!}` produisent du HTML serveur (badges d'énumérations) ou pré-échappé (`nl2br(e($content))`)
- ✅ Requêtes préparées — `whereRaw` toujours paramétré
- ✅ Autorisation par policies (`before()` sur les rôles)
- ✅ Registration publique désactivée

---

## 5. Tests de sécurité

`tests/Feature/SecuriteApplicationTest.php` — 14 tests :

| # | Contrôle |
|---|---|
| 1 | En-têtes de sécurité présents |
| 2 | Protection clickjacking (CSP `frame-ancestors`) |
| 3 | API v1 → 401 sans jeton |
| 4 | Connexion limitée en débit |
| 5 | 13 URLs internes refusées |
| 6 | 3 URLs publiques acceptées |
| 7 | Images valides acceptées |
| 8 | 5 fichiers dangereux refusés (exe, php, html, svg…) |
| 9 | Fichiers volumineux refusés |
| 10 | Série d'échecs → verrouillage |
| 11 | Connexions échouées journalisées |
| 12 | Commande d'audit fonctionnelle |
| 13 | L'audit signale les blocages |
| 14 | Export JSON de l'audit |

---

## 6. ⚠️ Restant à faire avant mise en production

1. **`APP_ENV=production` + `APP_DEBUG=false`** — *le plus critique*.
   `APP_DEBUG=true` affiche le code source en cas d'erreur.
2. **`users.md`** contient 93 comptes avec le mot de passe `password` :
   ne **jamais** déployer, et forcer leur changement.
3. **HTTPS réel** derrière un reverse proxy (Caddy / Nginx + certificat).
4. **SMTP réel** (`MAIL_MAILER=log` écrit les e-mails dans les logs).
5. **Sauvegardes** régulières de `bd_conducteur_tv` + `storage/app`.
6. **Installer le cron** du panneau (`php artisan schedule:run` chaque minute) —
   sinon la veille ne tourne pas. Contrôle : `php artisan veille:statut`.

➡️ Voir **[DEPLOIEMENT.md](DEPLOIEMENT.md)** pour la checklist complète.

---

## 7. Audit automatique

```bash
php artisan app:audit-deploiement          # tableau ✅ / ⚠️ / ❌
php artisan app:audit-deploiement --json   # exportable en CI
```

23 contrôles, **code de sortie `1`** dès qu'un blocage existe → intégrable dans
une pipeline CI/CD.

### Résultat sur l'environnement de développement actuel

| | Contrôle | Valeur | Attendu |
|---|---|---|---|
| ❌ | `APP_ENV` | `local` | `production` |
| ❌ | `APP_DEBUG` | `true` | `false` |
| ❌ | `APP_URL` | `http://localhost:8000` | `https://…` |
| ⚠️ | `SESSION_SECURE_COOKIE` | `false` | `true` |
| ⚠️ | `MAIL_MAILER` | `log` | `smtp` |
| ✅ | 12 autres contrôles | conformes | — |

> Les 3 ❌ reflètent un **poste de développement**, pas un bug : ils
> disparaîtront avec le `.env` de production.

---

## 8. Réponse à incident

1. **Isoler** — suspendre l'accès (reverse proxy → 503).
2. **Conserver les preuves** — sauvegarder `storage/logs/` *avant* toute intervention.
3. **Analyser** — `security.log` liste connexions, échecs et verrouillages (IP + User-Agent).
4. **Révoquer** — forcer la réinitialisation des mots de passe concernés.
5. **Restaurer** — dernière sauvegarde saine + migrations à jour.
6. **Tirer les leçons** — ajouter un test de régression pour chaque faille identifiée.

---

## 9. Maintenance continue

| Fréquence | Action |
|---|---|
| Quotidienne | Consulter `storage/logs/security-*.log` |
| Hebdomadaire | `composer audit` et `npm audit` |
| Mensuelle | Mises à jour de sécurité PHP + test de restauration |
| En continu | Sauvegardes automatiques de la base et de `storage/app` |
