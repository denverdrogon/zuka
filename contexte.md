# Contexte — Plug Templates

Plugin WordPress privé fournissant des templates personnalisés importables dans Elementor.

## 1. Décisions validées
- Templates livrés **dans le plugin**, sans serveur distant.
- Diffusion privée ; mises à jour via **GitHub Releases** avec Plugin Update Checker et l'en-tête `Update URI`.
- Cible **Elementor v3 et v4 Atomic** : fichiers `content.v3.json` et `content.v4.json`, optionnels par template.
- **Elementor Free uniquement**, aucune dépendance à Pro.

## 2. Analyse de la concurrence

| Solution | Points forts | Limites |
|---|---|---|
| Elementor Kit Library | Modale native | Beaucoup de kits exigent Pro |
| Starter Templates (Astra) | Import en un clic | Lourd, lié au thème Astra |
| Templately | Filtres et aperçus | Compte et cloud obligatoires |
| Envato Elements / ThemeForest | Kits cohérents | Pro requis, pas de mise à jour des kits |
| Essential / Happy / Royal Addons | Bouton dans l'éditeur | Plugins très lourds |

**Leçons retenues**
- Modale dans l'éditeur **et** page d'admin.
- Zéro dépendance à Pro, vérifiée automatiquement.
- Templates basés sur les Global Colors/Fonts (`__globals__`).
- Import des médias dans la médiathèque avec déduplication.
- Aucun compte, aucun appel externe.
- Aucun asset front inutile.

## 3. Contraintes (novembre 2026)

**WordPress 7.0**
- PHP 7.4 minimum, 8.3 recommandé.
- L'admin évolue vers DataViews (React).
- Arrivent : Abilities API (PHP et JS), enregistrement de blocs en PHP seul, éditeur en iframe.

**Elementor 4.x**
- Elementor 4.0 (mars 2026) : éditeur Atomic par défaut sur les nouveaux sites ; v3 et v4 cohabitent ; Classes et Variables importables/exportables.
- L'API v4 pour les tiers est encore peu documentée → tout isoler derrière l'Adapter.
- Elementor 4.1 ajoute Atomic Forms.

## 4. Architecture
- `plug-templates.php` : en-têtes, dont `Requires Plugins: elementor`.
- `uninstall.php`.
- `src/` :
  - `Plugin.php`
  - `Elementor/Adapter.php` (seul point d'accès à Elementor)
  - `Library/Repository.php`, `Library/Importer.php`
  - `Rest/TemplatesController.php` (`/plugt/v1/templates`, `/plugt/v1/import`)
  - `Admin/LibraryPage.php` (React + DataViews)
  - `Editor/EditorIntegration.php` (insertion via `$e.run('document/elements/import')`)
  - `Updates/Updater.php`
  - `Migrations/Runner.php` (`plugt_db_version`)
  - `Abilities/`
- `templates/` : `manifest.json`, puis `<slug>/` avec `meta.json`, `content.v3.json`, `content.v4.json`, `media/`, `thumbnail.webp`.
- `bin/` : `build-manifest.php`, `validate-templates.php`.
- Autres : `languages/`, `tests/`, `assets/src` compilé vers `assets/build`.

## 5. Flux d'import
1. Choix de la variante : v4 si Atomic est actif, sinon v3.
2. Import des médias dans la médiathèque (déduplication) et réécriture des IDs.
3. Trois modes : insertion dans l'éditeur, nouvelle page, ou post `elementor_library`.
4. Traçabilité par `_plugt_source = slug@version` ; le contenu déjà importé n'est jamais écrasé.

## 6. Mises à jour
- Dépôt GitHub privé, publication via **GitHub Releases** (zip construit par la CI sur tag).
- **Plugin Update Checker** dans `Updates/Updater.php` ; en-tête `Update URI` pour empêcher toute substitution par wordpress.org.
- Seul appel externe autorisé du plugin.
- Migrations de données via `Migrations/Runner.php` et l'option `plugt_db_version`.

## 7. Phases
1. **Socle** : en-têtes, autoload, Plugin, Adapter, outillage (lint, PHPStan, wp-env).
2. **Format des templates et validateur**, avec 3 exemples.
3. **Import côté serveur et REST.**
4. **Page d'admin** avec DataViews.
5. **Modale dans l'éditeur.**
6. **Mises à jour.**
7. **Variantes v4, Abilities API et widgets maison.**

## 8. Vérification
- Lint (PHPCS), PHPStan et tests PHPUnit au vert.
- Import manuel avec wp-env sur WP 7.0, avec et sans Atomic.
- Tests E2E Playwright.
- Test de mise à jour de N vers N+1.
- Plugin Check sans erreur bloquante.
- CI GitHub Actions : PHPCS, PHPStan, Plugin Check, PHPUnit sur wp-env ; matrice PHP 7.4/8.1/8.3/8.4 × WP 6.6/latest/7.0 × Elementor 3.x/4.x ; sur tag, build du zip et publication en release.
