# TV+A OS — Application Conducteur & Production

Plateforme de gestion de production audiovisuelle pour TV+ Afrique.

## 📚 Documentation

La documentation complète se trouve dans [`docs/`](docs/README.md) :

- [Présentation](docs/01-presentation.md)
- [Architecture](docs/02-architecture.md)
- [Installation](docs/03-installation.md)
- [Déploiement](docs/04-deploiement.md)
- [Modules fonctionnels](docs/05-modules-fonctionnels.md)
- [Rôles et permissions](docs/06-roles-permissions.md)
- [Modèle de données](docs/07-modele-donnees.md)
- [Routes et API](docs/08-routes-api.md)
- [Sondage en direct](docs/09-sondage-direct.md)
- [PWA](docs/10-pwa.md)
- [Sécurité et audit](docs/11-securite-audit.md)
- [Exploitation](docs/12-exploitation.md)
- [Lexique](docs/13-lexique.md)
- [Dépannage](docs/14-depannage.md)

> `DEPLOIEMENT.md` et `SECURITE.md` à la racine sont des documents historiques. Se référer à `docs/` pour l'état actuel.

## Démarrage rapide

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
php artisan serve
```

Windows : double-cliquer sur `INSTALLER.cmd`.

## Stack

Laravel **12.68**, PHP **8.3+**, Breeze, Sanctum, Spatie Permission / MediaLibrary, Simple QrCode, Firebase JWT, Reverb, Scout, Tailwind CSS 3, Alpine.js 3, Tiptap 3, FullCalendar 6, Vite 6, PHPUnit 12.

## Production

`https://tvplusaos.com` — voir [04 — Déploiement](docs/04-deploiement.md) et [12 — Exploitation](docs/12-exploitation.md).
