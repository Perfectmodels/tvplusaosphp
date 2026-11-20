# Documentation TV+A OS

Bienvenue dans la documentation technique et fonctionnelle de **TV+A OS**, l'application de pilotage de la production audiovisuelle de TV+ Afrique.

## Démarrage rapide

| Besoin | Document |
|---|---|
| Comprendre l'application et le vocabulaire | [01 — Présentation](01-presentation.md) |
| Comprendre la stack et l'organisation du code | [02 — Architecture](02-architecture.md) |
| Installer en local | [03 — Installation](03-installation.md) |
| Mettre en production | [04 — Déploiement](04-deploiement.md) |
| Découvrir les écrans et les parcours métier | [05 — Modules fonctionnels](05-modules-fonctionnels.md) |
| Savoir qui peut faire quoi | [06 — Rôles et permissions](06-roles-permissions.md) |
| Comprendre les tables et les relations | [07 — Modèle de données](07-modele-donnees.md) |
| Connaître les routes et l'API | [08 — Routes et API](08-routes-api.md) |
| Créer et exploiter un sondage en direct | [09 — Sondage direct](09-sondage-direct.md) |
| Comprendre la PWA et le mode hors-ligne | [10 — PWA](10-pwa.md) |
| Sécurité, audit et durcissement | [11 — Sécurité et audit](11-securite-audit.md) |
| Exploitation, logs, sauvegardes, incidents | [12 — Exploitation](12-exploitation.md) |
| Glossaire métier | [13 — Lexique](13-lexique.md) |
| Diagnostiquer un problème courant | [14 — Dépannage](14-depannage.md) |

## Faits essentiels

- **Domaine de production** : `https://tvplusaos.com`
- **Framework** : Laravel **12.68** / PHP **8.5.12** en production
- **Front** : Vite **6**, Tailwind CSS **3.4**, Alpine.js **3**
- **Base** : MySQL / MariaDB (`SESSION_DRIVER=database`, `CACHE_STORE=database`)
- **Authentification** : Breeze + Sanctum (API), Spatie Permission (rôles)
- **Temps réel** : Laravel Reverb + événements broadcast (`ConducteurChanged`, chat d'émission)
- **PWA** : manifest + service worker `tvaos-pwa-v4`, icônes 192/512/maskable
- **Tests** : PHPUnit, 80 fichiers de tests Feature/Unit

> Les fichiers `README.md`, `DEPLOIEMENT.md` et `SECURITE.md` à la racine sont antérieurs à cette documentation et contiennent encore des références historiques (Laravel 13, domaine `tvplusos.tv`). Ce dossier fait foi pour l'état actuel.

## Convention de lecture

- Les chemins sont relatifs à la racine du projet.
- Les noms de routes sont ceux retournés par `php artisan route:list`.
- Les extraits de code sont indicatifs ; le code source reste la référence.
- Aucun secret (mot de passe, `APP_KEY`, jeton) n'est reproduit dans cette documentation.
