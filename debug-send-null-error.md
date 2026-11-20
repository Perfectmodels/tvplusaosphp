# Debug Session · `send() on null` (`$response` = null dans public/index.php L17)

## Session ID
`send-null-error` (created 2026-08-27)

## Statut
🔴 **[OPEN]** Reproductible utilisateur.

## Symptômes observés (user)
- Navigateur `http://localhost:8000/login` déclenche Laravel error:
  ```
  Symfony \ Component \ ErrorHandler \ Error \ FatalThrowableError
  Call to a member function send() on null
  C:\Users\DELL\Desktop\tvplusos\conducteurApp\public\index.php:17
  ```
- Ligne fautive: `$response->send();` où `$response = $app->handleRequest(Request::capture());` retourne `null`.

## Reproduction Steps
1. URL: `/login` (GET)
2. POST `/login` (toutes deux peuvent déclencher ?)
3. Framework: Laravel 12.68.0 / PHP 8.3.28 / serveur `php artisan serve --port=8000`

## Historique récent
- Avant-hier: HTTP 500 `Unable to locate component [layouts.guest]` — résolu par suppression proxys Blade
- Aujourd'hui: Formulaire doublon → résolu par `@extends/@section` sur 7 blades
- **MAIS** erreur `send() on null` APPARAÎT AUSSI à certains moments (user dit "il reste lerreur sen()")

---

## 🧪 Hypothèses falsifiables (ordre probabilité décroissante)

### H1 — Forte probabilité: `app/Exceptions/Handler.php` custom override `render()` return NULL
**Si:** Handler de Laravel Breeze a été patché Sprint 0 avec `reportable()` ou `render()` qui retourne `null` sur certaines exceptions (ex: `ViewException` Blade).
**Test falsifiable:** Lire `app/Exceptions/Handler.php` + stack trace complet 150 derniere lignes log Laravel ERROR qui précède `send() on null`.

### H2 — Forte probabilité: `BroadcastManager` unresolved / Pusher tenté même si env `BROADCAST_CONNECTION=null`
**Si:** `Laravel/Boost/src/Middleware/InjectBoost.php` (visible stack trace L21) tente d'émettre un `Broadcast::event()` pendant l'Exception Handler et Driver `Pusher`/`Reverb` non dispo = exception silencieuse + return null.
**Test:** stack trace broadcast ou pusher présent ?

### H3 — Moyenne: Pipeline middleware doublon ou exception `Error` (pas Exception) non capturable par Handler
**Si:** Une exception `Error` (ex: `TypeError` dans Blade compiled views) est déclenchée DANS `handleRequest()` avant la création de Response → handler global PHP ne return pas response → Kernel `null`.

### H4 — Moyenne: `require_once __DIR__.'/../bootstrap/app.php'` retourne `null` / Application non créée
**Si:** bootstrap/app.php a une erreur de syntaxe PHP 8 (avant `->create()`) → application null → handleRequest = error mais silence.  
**Falsifiable:** `php artisan about` OK → app created donc H4 rejetée.

### H5 — Faible: Blade compiler cache corrompu (storage/framework/views php files fatals)
**Si:** cache non purgé malgré optimize:clear.
**Test:** `rm -rf storage/framework/views/*` puis test.

---

## 📋 Checklist instrumentation & résolution
- [ ] Step 1: Dernier `storage/logs/laravel-2026-08-27.log` (150 dernieres lignes stack trace AVANT send on null)
- [ ] Step 2: Lectures `app/Exceptions/Handler.php`
- [ ] Step 3: Injection instrumentation public/index.php (avant send()) pour afficher la class/type $app, $response (si exception capturable)
- [ ] Step 4: Correctif basé evidences
- [ ] Step 5: Test GET /login, POST /login, GET /dashboard → PAS send null
