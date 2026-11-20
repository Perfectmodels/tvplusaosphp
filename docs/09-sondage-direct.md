# 09 — Sondage en direct

Fonctionnalité déployée le 20/11/2026 : sondage pendant les émissions de type **Débat**, avec QR code et vote anonyme du public.

## 1. Règles métier

1. **Éligibilité** : seules les émissions dont `type = TypeEmissionEnum::Debat` peuvent avoir un sondage (`abort(404)` sinon).
2. **Candidats** : les intervenants de la séquence `TypeSequenceEnum::Debat` dont le pivot `role_dans_sequence = RoleSequenceIntervenantEnum::Debatteur` (règle existante : 2 à 4 débatteurs).
3. **Question** : obligatoire, 5 à 255 caractères.
4. **Cycle** : `brouillon` → `ouvert` → `clos`.
   - brouillon : page publique en 404 ;
   - ouvert : vote accepté, résultats en direct ;
   - clos : plus de vote, résultats figés.
5. **Permissions** (`SondagePolicy`) : admin, superadmin, ou chef d'édition affecté à l'émission (`emission.chef_edition_id`).
6. **Vote** : public anonyme, un seul vote par personne.

## 2. Parcours

### Côté administration

1. Sur la fiche émission, bouton **« Sondage en direct »** (visible si type Débat et permission).
2. Saisir la question ; les candidats sont pré-remplis depuis les débatteurs.
3. Créer le sondage → statut brouillon.
4. Ouvrir le vote → le QR code devient actif.
5. Diffuser le QR (affiché ou téléchargé en SVG).
6. Clore le scrutin quand le débat se termine.

### Côté téléspectateur

1. Scanner le QR code → `GET /s/{token}`.
2. Choisir un débatteur et envoyer le vote.
3. Message de confirmation ; résultats rafraîchis toutes les 5 secondes.

## 3. Anti-fraude / anti-spam

| Mesure | Détail |
|---|---|
| Jeton opaque | `token` aléatoire de 48 caractères, unique, non devinable |
| Cookie chiffré | `sondage_voter_{id}` contient un UUID (EncryptCookies + `CookieValuePrefix`) |
| Unicité DB | `UNIQUE(sondage_id, voter_id)` et `UNIQUE(sondage_id, voter_hash)` |
| Empreinte | `hash_hmac('sha256', voter_id|user-agent, APP_KEY)` |
| Concurrence | Transaction + `UniqueConstraintViolationException` → message « vous avez déjà voté » |
| Limitation | `throttle:30,1` sur la page et les résultats, `throttle:10,1` sur le vote |
| Validation | `candidat_id` doit exister **et** appartenir au sondage (sinon 404) |
| Cache | `Cache-Control: no-store` sur la page et les résultats |
| SEO | `X-Robots-Tag: noindex, nofollow` sur la page publique |

> Limite assumée : un utilisateur peut contourner le cookie en changeant d'appareil ou de navigateur. Pour un scrutin nominatif, il faudrait exiger une authentification ou une vérification SMS/e-mail.

## 4. Schéma

```text
sondages (id, emission_id, sequence_id, created_by, question, token, statut, ouvert_at, clos_at)
  ├─ sondage_candidats (id, sondage_id, intervenant_id, nom, ordre)
  │    └─ vote_sondages (id, sondage_id, candidat_id, voter_id, voter_hash)
  └─ emission, sequence, createur
```

Les migrations sont :

- `2026_11_19_194044_create_sondages_table.php`
- `2026_11_19_194044_create_sondage_candidats_and_votes_tables.php`

Un équivalent SQL pour phpMyAdmin existe dans `sondage-migration.sql` (dossier de travail) ; il insère aussi les lignes dans `migrations`.

## 5. Fichiers

| Fichier | Rôle |
|---|---|
| `app/Enums/StatutSondageEnum.php` | brouillon / ouvert / clos |
| `app/Models/Sondage.php` | génération du jeton, relations, résultats, `estOuvert()` |
| `app/Models/SondageCandidat.php` | candidat + votes |
| `app/Models/VoteSondage.php` | vote anonyme |
| `app/Policies/SondagePolicy.php` | autorisations admin / chef d'édition |
| `app/Http/Controllers/SondageAdminController.php` | create, store, show, ouvrir, clore, qr |
| `app/Http/Controllers/SondagePublicController.php` | show, vote, resultats |
| `app/Http/Requests/StoreSondageRequest.php` | validation question + authorize |
| `app/Http/Requests/VoteSondageRequest.php` | validation candidat |
| `routes/module_sondage.php` | 9 routes |
| `resources/views/sondage/{create,admin,show}.blade.php` | vues |
| `tests/Feature/SondagePublicTest.php`, `SondageAdminTest.php` | 12 tests, 44 assertions |

## 6. API de résultats

`GET /s/{token}/resultats` renvoie :

```json
{
  "question": "Qui a gagné le débat ?",
  "statut": "ouvert",
  "total": 3,
  "candidats": [
    { "id": 1, "nom": "M. Alice OUATTARA", "votes": 1 },
    { "id": 2, "nom": "M. Bob KOFFI", "votes": 2 }
  ]
}
```

## 7. Exploitation

- Vérifier que le service worker ne met pas en cache la page de vote (`no-store`).
- Après clôture, le QR pointe toujours sur la page qui affiche les résultats finaux.
- Pour régénérer un jeton : supprimer et recréer le sondage (le jeton n'est pas modifiable).
- Les votes sont supprimés en cascade si le sondage ou l'émission est supprimé.

## 8. Vérification post-déploiement

```bash
curl -sS -o /dev/null -w '%{http_code}\n' "https://tvplusaos.com/s/JETON"
curl -sS "https://tvplusaos.com/s/JETON/resultats" | head -c 300
```

Test réalisé le 20/11/2026 : page 200, premier vote compté (total 1), second vote avec le même cookie non compté, données de test supprimées.
