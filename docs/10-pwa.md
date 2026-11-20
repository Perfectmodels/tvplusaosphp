# 10 — PWA (Progressive Web App)

La PWA permet d'installer TV+A OS sur un appareil et d'afficher une page hors-ligne quand la connexion est indisponible.

## 1. Fichiers publics

| Fichier | Rôle |
|---|---|
| `/manifest.webmanifest` | Manifeste d'installation |
| `/sw.js` | Service worker (`tvaos-pwa-v4`) |
| `/offline.html` | Page hors-ligne localisée et brandée |
| `/pwa-icons/icon-192.png` | Icône 192×192 |
| `/pwa-icons/icon-512.png` | Icône 512×512 |
| `/pwa-icons/icon-maskable-512.png` | Icône maskable 512×512 |
| `/apple-touch-icon.png` | Icône iOS 180×180 |
| `/favicon.svg`, `/favicon-32x32.png`, `/favicon.ico` | Favicons |

Les icônes ont été régénérées depuis `public/images/oeil.png` (crop 403,100, 800×800 → master 1024, PNG8 256 couleurs).

## 2. Manifeste

Propriétés principales :

- `name` : « TV+A OS — Système d'exploitation TV Afrique »
- `short_name` : « TV+A OS »
- `start_url` : `/dashboard`
- `scope` : `/`
- `display` : `standalone`
- `theme_color` : `#001a41`
- `background_color` : `#F4F7F9`
- `lang` : `fr`
- icônes 192 / 512 / maskable / apple-touch
- raccourcis : Tableau de bord, Conducteurs · Rédaction, Émissions

Les URLs d'icônes sont versionnées `?v=2`.

## 3. Service worker

Version interne : `tvaos-pwa-v4`. Caches :

- `tvaos-pwa-v4-precache` : `offline.html`, manifest, favicons, icônes ;
- `tvaos-pwa-v4-assets` : assets `/build` (cache-first).

Stratégies :

- navigations : réseau d'abord, repli sur `offline.html` ;
- assets `/build` : cache-first ;
- autres ressources statiques : stale-while-revalidate ;
- **aucune page HTML authentifiée n'est mise en cache** (sécurité/privacy) ;
- nettoyage des anciens caches ;
- `skipWaiting()` + `clients.claim()`.

Enregistrement dans `layouts/guest.blade.php` et `layouts/app.blade.php` avec `/sw.js?v=2`.

## 4. En-têtes recommandés

```apache
<FilesMatch "^sw\.js$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Service-Worker-Allowed "/"
</FilesMatch>
<FilesMatch "^(manifest\.webmanifest|offline\.html)$">
    Header set Cache-Control "public, max-age=86400"
</FilesMatch>
```

Les assets `/build/assets/*` étant hashés par Vite, ils peuvent recevoir `public, max-age=31536000, immutable`.

## 5. Mise à jour

À chaque modification du service worker :

1. incrémenter `VERSION` dans `sw.js` ;
2. incrémenter le `?v=` d'enregistrement dans les layouts ;
3. incrémenter `?v=` des icônes/manifest si modifiés ;
4. déployer, re-télécharger et comparer ;
5. tester l'installation et le mode hors-ligne.

## 6. Points d'attention

- `start_url=/dashboard` : un visiteur non connecté est redirigé vers `/login`.
- `skipWaiting + claim` peut remplacer un onglet ouvert sans prévenir ; prévoir un message « Nouvelle version disponible » si besoin.
- Tester régulièrement que le HTML authentifié n'est pas mis en cache.
- La page de vote du sondage doit rester `no-store` malgré le service worker.
