# TV+A OS — Exports PDF conducteur et script

## Formats

| Export | Route Laravel | Papier | Usage |
|---|---|---|---|
| Conducteur technique | conducteurs.emission.export-pdf | A4 paysage | Régie, réalisation, production |
| Script présentateur | conducteurs.emission.export-script-pdf | A4 portrait | Texte de lecture |
| Dossier complet | conducteurs.emission.export-dossier-pdf | A4 paysage + portrait | Dossier d'une édition |

La mise en page reprend la charte existante : bleu nuit #001A41, bleu #0054CD,
fond blanc, gris bleuté, wordmark TV+A OS, polices locales PilatNarrow Book et Bold.
Les documents non encore validés portent la mention discrète BROUILLON.

## Données affichées

### Conducteur technique

Émission, code, date, présentateur, durée prévue, objectif, statut et séquences
classées par position. Chaque ligne affiche numéro, heure, type, titre, durée,
intervenants et aperçu des consignes régie. Les notes longues, résumés et angles
éditoriaux sont reproduits dans une section détaillée paginée.

### Script présentateur

Pour chaque séquence : titre, numéro, horaires, durée, invités et script_text
en premier. Les notes_presentateur, note_regie et summary sont imprimés
dans des blocs distincts sous le texte de lecture. Le texte principal n'est
jamais inventé ; si script_text est absent, le PDF l'indique explicitement.

Le champ notes_perso n'est jamais imprimé, quel que soit l'export.

## Sécurité

Les trois routes requièrent Gate::authorize('view', $conducteur), un conducteur
de type émission et une session authentifiée. Les réponses portent les en-têtes
Cache-Control: private, no-store et Content-Type: application/pdf.
Les exports sont en lecture seule : aucune séquence n'est modifiée.

## Rendu et compatibilité LWS

Le moteur PDF n'a pas besoin de Chrome, wkhtmltopdf, dompdf, commande shell
ou nouvelle dépendance Composer. Il embarque dans le PDF les polices Pilat TTF
déjà présentes dans public/fonts/ et crée des pages vectorielles.
ToUnicode assure le texte sélectionnable et les accents français.

Les polices PilatNarrow-Book.ttf et PilatNarrow-Bold.ttf doivent être lisibles
sur LWS. L'extension PHP mbstring est nécessaire. Format PDF 1.4.

## Vérifications

Tests automatisés : php artisan test --filter=ConducteurExportPdfTest.

Après publication, vérifier les éditions avec 1, 8 et 30 séquences, les textes
longs, la pagination, les caractères accentués, l'impression A4 et les
téléchargements sur mobile. Vérifier les permissions avec un administrateur,
un présentateur et un utilisateur sans droit d'accès.

La fusion GitHub main ne prouve pas la publication LWS tant que l'envoi
des fichiers n'a pas été confirmé.
