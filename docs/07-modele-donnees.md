# 07 — Modèle de données

Les modèles Eloquent se trouvent dans `app/Models/`, les migrations dans `database/migrations/`. Les relations ci-dessous reprennent les méthodes définies dans les modèles.

## 1. Vue d'ensemble

```text
User ──┬─ Journaliste (journaliste_id)
       ├─ Conducteur (owner_id / chef_edition_id)
       └─ rôles & permissions (Spatie)

Emission ──┬─ Conducteur (1:N)
           ├─ Sequence (1:N, emission_id)
           ├─ emission_journaliste (pivot : présentateurs)
           └─ Sondage (1:N)

Conducteur ──┬─ Sequence (1:N)
             ├─ ConducteurPresence (1:N)
             ├─ EmissionChatMessage / EmissionInteractionMessage
             └─ ParcoursIntervenant (1:N)

Sequence ──┬─ Intervenant (N:M via sequence_intervenants)
           └─ Journaliste responsable (journaliste_responsable_id)

Sondage ──┬─ SondageCandidat (1:N)
          └─ VoteSondage (1:N)
```

## 2. Identité et accès

### `users`

Comptes. Champs notables : `name`, `email`, `role` (legacy), `journaliste_id`, `password_changed_at`, `is_active`. Liens Spatie : `model_has_roles`, `model_has_permissions`.

### `journalistes`

Personnel interne. `civilite` (cast `CiviliteCast` → `CiviliteEnum`), `prenom`, `nom`, `fonction` (`FonctionJournalisteEnum`, dont `jri`), `pole` (`PoleRubriqueEnum`), `statut`, `is_active`, `user_id`.

Accesseurs utiles : `nomComplet`.

### `inscription_personnel`

Demandes publiques : identité, coordonnées, motivation, `statut` (pending/approved/rejected), `validated_by`, `user_id`, `journaliste_id`.

### `user_account_requests`

Autre flux de demande de compte (code partiellement Actif selon les routes).

## 3. Production

### `emissions`

`nom`, `slug`, `type` (`TypeEmissionEnum`), `statut` (`StatutEmissionEnum`), `categorie`, `heure_debut/fin`, `jours_diffusion`, `langues`, `chef_edition_id`, `presentateur_id`, `is_active`, `is_brouillon`.

Relations : `presentateurs()` (pivot `emission_journaliste` avec `role`, `statut_affectation`, `ordre`), `conducteurs()`, `sequences()`, `chefEdition()`, `presentateurPrincipal()`.

### `conducteurs`

`emission_id`, `date_edition`, `type` (`emission` | `redaction`), `owner_id`, `status` (`StatutConducteurEnum`), `date_locked_at`, `target_duration_sec`, `presenter_on_air_id`, `active_version`.

Relations : `emission()`, `sequences()`, `presences()`, `intervenants`, `chatMessages`.

### `sequences`

`conducteur_id`, `emission_id`, `title`, `type` (`TypeSequenceEnum`), `position`, `duration_seconds`, `journaliste_responsable_id`, `nom_prenom_text`, `fonction_text`, `angle_editorial`, `note_regie`, `alerte_info`, `start_time`, `end_time`, `heure_debut_calculee`, `heure_fin_calculee`.

Relations : `intervenants()` (N:M via `sequence_intervenants`, pivot `SequenceIntervenantPivot` avec `position`, `role_dans_sequence`, `ordre_passage`, logistique), `journalisteResponsable()`, `conducteur()`.

### `conducteur_presences`

`conducteur_id`, `journaliste_id`, `arrivee_at`, `ponctualite`, `created_by`. Contrainte d'unicité par (conducteur, journaliste).

### `intervenants` et suivi

- `intervenants` : identité, civilité, fonction, type, disponibilité, contact, photo.
- `intervenants_qr_codes` : jeton JWT, `is_active`, `used_at`, expiration.
- `parcours_intervenants` : liaison intervenant ↔ séquence/conducteur/émission, statut logistique, QR envoyé, confirmation.
- `intervenants_scan_logs`, `intervenant_checkin_attempts` : audit des scans (IP, UA).

## 4. Communication et interaction

### `emission_interaction_messages`

Messages publics liés à un `Conducteur` via `public_interaction_token` (48 caractères). Page `/i/emission/{token}`.

### `emission_chat_messages`

Chat interne du conducteur d'émission, diffusé en temps réel.

## 5. Veille

- `veille_sources`, `veille_subjects`, `veille_remixes`.
- `SourceVeille` protège le scrape SSRF via `UrlPublique`.
- Remixes IA via `VeilleAIService` / fournisseurs `GroqProvider`, `OpenCodeAssistant`.

## 6. Espace métier et éditorial

- `workspace_dossiers`, `workspace_tasks`, `workspace_comments`, `workspace_attachments`, `workspace_notifications`, `workspace_activities`.
- `editorial_stories`, `editorial_events`, `editorial_verifications`.
- `digital_publications` avec cycle de validation.

## 7. Sondage en direct (ajouté le 19/11/2026)

### `sondages`

`emission_id`, `sequence_id`, `created_by`, `question`, `token` (48 caractères, unique), `statut` (`StatutSondageEnum` : brouillon/ouvert/clos), `ouvert_at`, `clos_at`.

### `sondage_candidats`

`sondage_id`, `intervenant_id`, `nom` (snapshot), `ordre`. Unicité (sondage, intervenant).

### `vote_sondages`

`sondage_id`, `candidat_id`, `voter_id` (UUID du cookie chiffré), `voter_hash` (HMAC). Deux contraintes d'unicité garantissent un seul vote.

Voir [09 — Sondage direct](09-sondage-direct.md).

## 8. Enums principaux

| Enum | Valeurs notables |
|---|---|
| `TypeEmissionEnum` | `JT`, `Mag`, `Debat`… |
| `StatutEmissionEnum` | Brouillon, Planifiee, En_cours, Diffusee, Archivee, Annulee |
| `TypeSequenceEnum` | SUJET, REPORTAGE, DÉBAT, INTERVIEW… |
| `RoleSequenceIntervenantEnum` | invite, debatteur, correspondant_duplex… |
| `FonctionJournalisteEnum` | journaliste, jri, redacteur, chef_edition… |
| `CiviliteEnum` | Mr, Mme, Mlle, Me, Dr |
| `StatutSondageEnum` | brouillon, ouvert, clos |
| `StatutConducteurEnum` | Brouillon, … |
| `PonctualitePresenceEnum` | à pointer, ponctuel, retard… |

## 9. Casts et accesseurs importants

- `CiviliteCast` tolère les valeurs héritées (`MR`, `MADAME`, `MLLE`…) et renvoie `?CiviliteEnum`.
- `nomComplet` (Journaliste, Intervenant, InscriptionPersonnel, SondageCandidat via snapshot) gère les enums et les valeurs nulles.
- Les enums sont castés dans les modèles : toujours utiliser `->value` ou `?->label()` lors d'un affichage.
