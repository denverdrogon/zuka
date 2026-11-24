# Guide d'auteur : créer un template Zuka

Cycle complet, de la page Elementor à la PR. Prérequis : un WordPress local (`npx wp-env start`) avec Elementor Free et, pour les vignettes, le kit du site cible.

## 1. Construire la page dans Elementor
- **Elementor Free uniquement** : aucun widget, condition ou réglage Pro. La liste blanche est dans `Schema::V3_FREE_WIDGETS`.
- **Couleurs et polices globales** : choisir les couleurs et typographies du kit (pastille « Globales »), jamais une valeur saisie à la main. Le validateur avertit sinon.
- Renseigner le **texte alternatif** de chaque image. Aucun texte factice (« Lorem ipsum », « TODO »).
- Médias : images légères (300 Ko au plus), en WebP de préférence.

## 2. Ajouter les classes motion (si besoin)
Dans « Avancé > Classes CSS » du conteneur ou du widget :
- `plugt-m-<module>` sur la racine d'un module (`accordion`, `bars`, `gauge`, `reveal`, `tabs`) ;
- `plugt-m-<module>__<part>` sur une partie, `--<modifier>` sur une variante ;
- `plugt-glass` pour l'effet verre.

Le widget HTML reste statique : pas de script, pas de `on*`, pas d'URL externe.

## 3. Exporter
```powershell
npx wp-env run cli wp plugt export <post_id> <slug> --category=<cat> [--type=section|page] [--title=<t>] [--code=TPL-NN]
```
La commande copie les médias dans `templates/<slug>/media/`, régénère les identifiants, remplace les valeurs égales à une valeur du kit par des références globales, puis écrit `meta.json` et `content.v3.json`. Elle liste en sortie les valeurs restées en dur. Elle refuse d'écraser un dossier existant (sauf `--force`).

## 4. Relire `meta.json`
Vérifier `title`, `description`, `category`, `tags`, `code` (unique), `sector`, `motion`, `accent` et `elementor_min`. La version initiale est `1.0.0`.

## 5. Générer la vignette
```powershell
npm run thumbnails -- --wp=<racine WP> --url=<URL du site>
```
La vignette doit faire **600 × 400 px**. Elle capture le vrai rendu, jamais une maquette.

## 6. Valider et indexer
```powershell
composer templates:build
```
```powershell
composer templates:check
```
`templates:check` échoue sur les erreurs. Les avertissements `[WARN]` ne bloquent pas ; `php bin/validate-templates.php --strict` les rend bloquants.

## 7. Ouvrir la PR
Une PR par template ou par petit lot cohérent, avec le dossier `templates/<slug>/` et `templates/manifest.json`.

## Checklist de relecture
- [ ] Mobile : lisible à 375 px, aucun débordement horizontal.
- [ ] Tablette (768 px) correcte.
- [ ] Contraste suffisant (4,5:1 pour le texte courant).
- [ ] Toutes les images ont un `alt`.
- [ ] Aucun texte factice.
- [ ] Rendu correct dans le kit par défaut d'Elementor **et** dans le kit cible.
- [ ] Couleurs et polices globales ; aucun `[WARN]` inexpliqué.
- [ ] Motion : contenu visible sans JavaScript et avec `prefers-reduced-motion`.
- [ ] Import testé deux fois : deux pages distinctes, aucun média dupliqué.
