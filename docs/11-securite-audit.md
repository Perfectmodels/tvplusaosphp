# 11 — Sécurité et audit

Ce document synthétise l'audit de production du 19/11/2026, les correctifs déployés et les recommandations restantes. Le rapport complet est `/Users/tvplus/Downloads/audit-tvplusaos.md`.

## 1. État initial (avant corrections)

| Domaine | Constat |
|---|---|
| Environnement | `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000` |
| Fuite d'infos | Page de debug Laravel de ~980 Ko avec stack trace, SQL, versions, chemins |
| Inscriptions | `/personnel/inscriptions` accessible à tout compte authentifié ; rôle arbitraire (superadmin) et mot de passe temporaire affiché |
| Endpoint public | `/personnel/inscription/search` renvoyait e-mail/téléphone/fonction et plantait en 500 sur `CiviliteEnum` |
| DB | Mot de passe MySQL invalide depuis le 19/11 14 h 00 → 500 global |
| Headers | Absence de `Cache-Control` sur les assets ; HTTP/1.1 ; CSP avec `unsafe-eval` |
| SEO | `sitemap.xml` 403, page de parking LWS, doublon `/index.php`, meta absentes |
| Accessibilité | `select-name` sur le formulaire d'inscription ; intro audio sans « Passer » |
| Performance | Material Symbols 1,15 Mo, hero 301 Ko, LCP 6,5 s, bundles inutilisés |

Points bons : TLS valide, HSTS, cookies `Secure/HttpOnly/SameSite=Lax`, protection `.env`/`.git`, PWA installable, rate limiting du login, CRUD utilisateurs protégé par policy, ports DB/SSH filtrés.

## 2. Correctifs P0 déployés (19/11/2026)

1. **Configuration** : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tvplusaos.com`, `LOG_LEVEL=warning`, `SANCTUM_STATEFUL_DOMAINS` corrigé, `SESSION_SECURE_COOKIE=true`.
2. **Inscriptions** :
   - middleware `role:admin|superadmin` sur les actions, `role:admin|superadmin|directeur_general` sur la liste ;
   - `InscriptionPersonnelPolicy` + `$this->authorize()` dans `InscriptionPersonnelAdminController` ;
   - `Rule::in(UsersController::rolesAssignableTo(...))` empêche l'attribution de `superadmin` par un non-superadmin ;
   - message d'erreur générique au lieu de `$e->getMessage()`.
3. **Recherche publique** : suppression de `email`, `telephone`, `fonction`, `pole`, `has_account` ; utilisation de `nomComplet` ; `q` borné et échappé. **Limite :** l'annuaire nominatif reste exposé ; la solution robuste serait de supprimer la recherche publique.
4. **Vérification** : 5 tests Feature (16 assertions), re-téléchargement et diff des 6 fichiers, page d'erreur de 6 638 octets sans stack trace.

## 3. Incident base de données (19–20/11)

- Première erreur `SQLSTATE[HY000] [1045] Access denied` à 14 h 00 le 19/11, avant le déploiement P0.
- Toutes les pages en 500 ; le service worker et la page d'erreur restaient propres (plus de debug).
- Diagnostic par sonde PHP : échec depuis `127.0.0.1`, `localhost` et l'hôte MySQL ; le mot de passe du `.env` était bien envoyé mais rejeté par MySQL.
- Résolution le 20/11 : nouveau `DB_PASSWORD` fourni, `.env` mis à jour et vérifié, site revenu en 200.
- Recommandation : purger `storage/logs/laravel.log` (53 Mo générés pendant la panne).

## 4. Correctifs post-déploiement

### 4.1 500 sur `/conducteurs/chef-edition/{id}`

`CiviliteEnum` concaténé dans `conducteur_redaction.blade.php`. Corrigé via `nomComplet` et `fonction?->label()`. Test dédié + vérification du rendu en production (102 993 octets, sans erreur).

### 4.2 Attribution des sujets aux JRI

La règle de présence bloquait les chefs d'édition. Les rôles responsables d'édition sont désormais exemptés, et leur sélecteur affiche tous les journalistes actifs. 9 tests, 23 assertions.

### 4.3 Sondage

Ajout sécurisé (voir [09 — Sondage direct](09-sondage-direct.md)) : jeton opaque, cookie chiffré, unicité DB, throttles, `no-store`.

## 5. Recommandations restantes (P1/P2)

| Priorité | Recommandation |
|---|---|
| P1 | En-têtes de cache sur assets/images, HTTP/2, Brotli déjà actif |
| P1 | Remplacer Material Symbols complet (1,15 Mo) par un sous-ensemble ou SVG |
| P1 | Hero AVIF/WebP avec preload (301 Ko → 30–47 Ko) |
| P1 | Bundle public séparé (CSS/JS) pour ne pas charger le dashboard sur les pages publiques |
| P1 | SEO : sitemap, robots, meta/OG/canonical, 301 `index.php` et `default_index.html` |
| P1 | Intro : bouton « Passer », pause/mute, overlay clavier, `sessionStorage` |
| P1 | Page politique de confidentialité + consentement |
| P1 | Throttle `forgot-password`, endpoint `veille` en POST + queue |
| P1 | CSP : retirer `unsafe-eval`, auto-héberger SweetAlert2 |
| P2 | Redis pour sessions/cache, worker de queue, OPcache, caches Laravel |
| P2 | DMARC `rua`, CAA, HSTS preload, OCSP stapling, `security.txt` |
| P2 | Trusted Proxies documenté, rotation des logs, SFTP à la place de FTP |
| P2 | Document root sur `conducteurApp/public` |

## 6. Réflexes de sécurité

- En production : `APP_DEBUG=false` en permanence.
- Toute nouvelle route d'administration : middleware d'authentification **et** Policy.
- Toute donnée personnelle exposée publiquement : minimaliser puis tester.
- Tout enum affiché en Blade : `->value` / `?->label()` / accesseur `nomComplet`.
- Après chaque déploiement : re-téléchargement + diff + test HTTP.
