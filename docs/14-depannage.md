# 14 — Dépannage

## 1. Le site renvoie 500 sur toutes les pages

**Vérifier d'abord** le journal : `storage/logs/laravel.log`, dernières lignes.

### Cas A — `SQLSTATE[HY000] [1045] Access denied`

Le mot de passe MySQL du `.env` est refusé par le serveur de base.

1. Ne pas modifier le code.
2. Récupérer le mot de passe actuel dans le panneau de l'hébergeur.
3. Remplacer `DB_PASSWORD` dans `/conducteurApp/.env`.
4. Re-télécharger le fichier et vérifier qu'il est identique à la copie locale.
5. Vérifier `curl -sS -o /dev/null -w '%{http_code}' https://tvplusaos.com/`.

Historique : panne du 19/11 14 h 00 → 20/11, résolue par rotation du mot de passe.

### Cas B — `Object of class App\Enums\CiviliteEnum could not be converted to string`

Un affichage Blade concatène un enum. Corriger avec `->value`, `?->label()` ou l'accesseur `nomComplet`.

Fichier connu corrigé : `resources/views/conducteur_redaction.blade.php`.

### Cas C — Page Laravel de ~980 Ko avec stack trace

`APP_DEBUG=true` en production. Passer `APP_DEBUG=false` et `APP_ENV=production` dans `.env`. Vérifier qu'une erreur renvoie la page « Coupure technique » sans SQL ni version.

## 2. `/personnel/inscription/search` renvoie 500

Historiquement causé par `$j->civilite . ' '` dans le contrôleur. Le correctif utilise `$j->nomComplet` et ne renvoie plus les coordonnées. Si le 500 revient, vérifier la ligne 53 et le cast `CiviliteCast`.

## 3. Attribution d'un sujet refusée : « Ce journaliste n'est pas présent »

Deux cas :

- vous êtes **journaliste / correspondant** : la règle exige que le responsable soit marqué présent dans `conducteur_presences` (page Présences) ;
- vous êtes **chef d'édition / admin / direction** : cette règle ne s'applique plus depuis le 20/11/2026 ; si le message persiste, vérifier que le code de `ConducteurRedactionVraiController` est bien déployé (`estResponsableEdition`).

Le sélecteur des responsables affiche tous les journalistes actifs, même absents.

## 4. `ViteException: Unable to locate file in Vite manifest`

Les assets compilés sont absents. Lancer `npm run build` localement et uploader `public/build/`, ou `npm run dev` en local.

## 5. Erreur 419 sur un formulaire

Le jeton CSRF est absent ou expiré (cookie de session supprimé, page restée ouverte trop longtemps).

- recharger la page puis ressoumettre ;
- vérifier que le formulaire contient `@csrf` ;
- pour un `fetch`, envoyer l'en-tête `X-XSRF-TOKEN` avec la valeur du cookie `XSRF-TOKEN`.

## 6. 403 sur une action métier

Vérifier :

1. le rôle de l'utilisateur (`config/workspaces.php`) ;
2. la permission dans `config/role_access.php` ;
3. la Policy correspondante (`app/Policies/`) ;
4. le verrouillage du conducteur (`date_locked_at`) ;
5. les middlewares de la route (`php artisan route:list --name=<route>`).

## 7. `sitemap.xml` en 403

La règle `.htaccess` bloque les `.xml`. Ajouter avant la règle de blocage :

```apache
RewriteRule ^sitemap\.xml$ - [L]
```

## 8. Double vote au sondage

Le vote est protégé par cookie chiffré + contraintes d'unicité. Si un double vote est constaté :

- vérifier que les contraintes `vote_sondages_voter_unique` et `vote_sondages_hash_unique` existent ;
- vérifier que `Cookie::queue()` est bien appelé après le premier vote ;
- vérifier que le navigateur accepte les cookies `Secure`/`SameSite=Lax`.

## 9. Mise à jour PWA non visible

1. Incrémenter `VERSION` dans `sw.js` **et** le `?v=` d'enregistrement dans les layouts.
2. Vérifier l'en-tête `Cache-Control: no-cache` sur `sw.js`.
3. Vider le cache du navigateur / désinstaller puis réinstaller la PWA.

## 10. Ancien contenu affiché après déploiement

- ajouter un cache-buster `?cb=$(date +%s)` aux URLs de test ;
- vérifier `X-Cache-Status` dans les en-têtes ;
- si besoin, purger le cache côté hébergeur ;
- côté serveur, vérifier les caches Laravel (`bootstrap/cache/`).

## 11. Erreurs de recherche de profil

- `q` de moins de 2 caractères renvoie `[]` : normal ;
- les résultats ne contiennent volontairement ni e-mail, ni téléphone, ni fonction, ni `has_account` ;
- ne pas réintroduire ces champs pour « réparer » un affichage : utiliser les champs saisis par le candidat.

## 12. Où chercher

| Besoin | Emplacement |
|---|---|
| Erreur applicative | `storage/logs/laravel.log` |
| Événements de sécurité | `storage/logs/security-*.log` |
| Routes | `php artisan route:list` |
| Permissions | `config/role_access.php`, `app/Policies/` |
| Matrice rôles | `config/workspaces.php` |
| Configuration serveur | `.htaccess` racine |
| Audit détaillé | `/Users/tvplus/Downloads/audit-tvplusaos.md` |
