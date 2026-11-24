# Plan d'exécution — Zuka (plug-templates) v0.1 → v1.0

Ce document contient tout le travail restant pour passer de v0.1.0 à v1.0. Il s'adresse au développeur qui reprend le projet, et à Claude Code s'il est utilisé. Chaque tâche indique son objectif, les fichiers concernés, la spécification, les tests et le critère « fait quand ».

Rédigé le 24/11/2026 sur la base du commit `daeefaf`.

---

## 0. Mode d'emploi

### 0.1 À lire avant de commencer
1. `CLAUDE.md` : conventions, règle d'or de l'Adapter, couche motion, sécurité. **Il prime sur ce document** en cas de conflit.
2. `contexte.md` : décisions d'architecture, concurrence, phases.
3. Ce fichier, section 1 (décisions) puis la tâche en cours.

### 0.2 Règles de travail
- **Une tâche = une branche = une PR.**
  - Nom de branche : `feat/T05-wp-cli`, `fix/...` ou `docs/...`.
  - Commits en français, préfixés `feat:`, `fix:`, `docs:`, `chore:`, `test:` ou `perf:`. C'est le style de l'historique.
- **Ordre :** respecter les dépendances indiquées (`Dépend de`). Les lots peuvent se chevaucher si les dépendances le permettent.
- **Suivi :** à la fin d'une tâche, cocher sa case dans le tableau de suivi (section 2). Si la spec change, ajouter une note « Écart » sous la tâche.
- **Avec Claude Code :** donner une seule tâche à la fois, par exemple « Implémente T03 de optimisations.md ». Le développeur relit la PR avant de fusionner.
- **Ne jamais :**
  - référencer Elementor hors de `src/Elementor/Adapter.php` ;
  - ajouter un widget ou un réglage Pro ;
  - ajouter un appel HTTP externe autre que l'updater ;
  - écraser un post déjà importé.

### 0.3 Environnement
```powershell
composer install
```
```powershell
npm ci
```
```powershell
npm run build
```
```powershell
npx wp-env start
```
- wp-env sert le site sur http://localhost:8888, identifiants `admin` / `password`. WP 7.0, PHP 8.3, Elementor stable : voir `.wp-env.json`.
- **Vignettes :** `npm run thumbnails` a besoin d'un WordPress local **avec le kit du site cible** et de Chrome installé (voir l'en-tête de `bin/build-thumbnails.mjs`).
- **Prototype Gemini :** le zip `other sources/atelier-wp-templates (3).zip` cité dans `CLAUDE.md` **n'est pas versionné**, et il est introuvable sur le portable du propriétaire (recherche du 24/11/2026). Il est nécessaire pour T11. Le localiser sur le poste du bureau : voir **T00**.

### 0.5 T00 — Localiser le prototype Gemini (à faire en premier, sur le poste du bureau)
1. Rechercher le fichier dans le profil utilisateur. La commande ci-dessous est à lancer dans PowerShell, ou à faire exécuter par Claude Code :
   ```powershell
   Get-ChildItem -Path $env:USERPROFILE -Recurse -Force -ErrorAction SilentlyContinue -Include "*atelier-wp-templates*","*atelier*.zip" | Select-Object FullName, Length, LastWriteTime
   ```
2. Si rien n'est trouvé, élargir la recherche à tous les disques :
   ```powershell
   Get-PSDrive -PSProvider FileSystem | ForEach-Object { Get-ChildItem -Path $_.Root -Recurse -Force -ErrorAction SilentlyContinue -Filter "*atelier-wp-templates*" } | Select-Object FullName, Length, LastWriteTime
   ```
3. Chercher aussi un dossier déjà décompressé : un dossier `other sources`, ou un fichier HTML contenant `atelier`.
4. Si le zip est trouvé :
   - le copier dans `other sources/` à la racine du dépôt **sans le commiter** ;
   - ajouter `other sources/` au `.gitignore` ;
   - noter ici son emplacement d'origine.
5. Si le zip reste introuvable :
   - prévenir le propriétaire ;
   - T11 se fait alors sans lui, en partant des templates existants et de la proposition de catalogue.

> Emplacement trouvé : _(à compléter)_

### 0.4 « Fait » global : à vérifier pour chaque PR
```powershell
composer lint
```
```powershell
composer phpstan
```
```powershell
composer test
```
```powershell
composer templates:check
```
```powershell
npm run lint:js
```
```powershell
npm run build
```
En complément :
- Tests ajoutés ou mis à jour pour tout code PHP nouveau. Le style est Brain Monkey ; voir `tests/php/Unit/TestCase.php`.
- Toutes les nouvelles chaînes passent par `__()` / `esc_html__()` avec le domaine `plug-templates`.
- Aucune syntaxe PHP 8+ : PHPStan est en `phpVersion: 70400` et `composer.json` fixe la plateforme à 7.4.33.

---

## 1. Décisions prises (ne pas rediscuter sans le propriétaire)

| # | Décision | Conséquence |
|---|---|---|
| D1 | Le **slug reste `plug-templates`** et le nom affiché est **Zuka**. | Ne pas renommer le dossier, le text domain ni le préfixe `plugt_`. Changer le slug casserait les mises à jour. |
| D2 | **Les chaînes sources restent en français** (plugin privé, utilisateurs francophones). | On génère quand même un `.pot` pour permettre d'autres langues plus tard. Pas de réécriture en anglais. |
| D3 | Les mises à jour passent par **GitHub Releases du dépôt `denverdrogon/zuka`** (privé), avec Plugin Update Checker v5 (déjà dans `composer.json`). | Le token est fourni par une constante `PLUGT_GITHUB_TOKEN` dans `wp-config.php`, jamais stocké en base. |
| D4 | Le zip de release s'appelle **`plug-templates.zip`** et contient un dossier racine **`plug-templates/`**. | Le dossier du plugin installé reste stable. |
| D5 | Un **kit** est un ensemble de templates existants avec une palette et une typographie. Ce n'est pas un nouveau type de template. | Les templates restent la seule unité de contenu. |
| D6 | Les **widgets maison** sont reportés après la v1.0. | Tout passe par les widgets Free et la couche motion. |

**Questions encore ouvertes pour le propriétaire.** Elles ne bloquent pas le lot 1.
- Q1 : la liste des secteurs et des kits cibles pour le catalogue v1 (T11). Proposition par défaut : section 4, T11.
- Q2 : qui crée le token GitHub (fine-grained, lecture seule sur `Contents` du dépôt) et comment il est transmis aux sites clients.

---

## 2. Tableau de suivi

| ID | Tâche | Lot | Dépend de | État |
|---|---|---|---|---|
| T01 | Updater GitHub Releases | 1 | — | [x] |
| T02 | Workflow de release + script de version | 1 | — | [x] |
| T03 | Runner de migrations | 1 | — | [x] |
| T04 | CI JS + matrice Elementor | 1 | — | [x] |
| T05 | `readme.txt` | 1 | T02 | [x] |
| T06 | i18n : `.pot` et vérification en CI | 1 | T04 | [x] |
| T07 | Tests E2E Playwright | 2 | T04 | [ ] |
| T08 | Commandes WP-CLI (list, import, validate) | 2 | — | [x] |
| T09 | Export d'une page vers un template (`wp plugt export`) | 2 | T08 | [x] |
| T10 | Avertissements qualité du validateur + guide d'auteur | 2 | — | [x] |
| T11 | Catalogue v1 (≥ 40 templates) | 3 | T09, T10 | [ ] |
| T12 | Champs `added` / `changelog` + nouvelles catégories | 3 | — | [ ] |
| T13 | Suivi des imports (« Déjà importé », « Mise à jour dispo », « Mes imports ») | 3 | T12 | [ ] |
| T14 | Favoris, nouveautés, filtres avancés, vue mémorisée | 3 | T12 | [ ] |
| T15 | Kits | 4 | T11, T13 | [ ] |
| T16 | Variantes v4 Atomic | 4 | T07 | [ ] |
| T17 | Import atomique (rollback) + hooks | 5 | T03 | [ ] |
| T18 | Motion chargé par module | 5 | — | [ ] |
| T19 | Nettoyage des aperçus orphelins (cron) | 5 | T03 | [ ] |
| T20 | Accessibilité (modules motion + modales) | 5 | T07 | [ ] |
| T21 | Tests JS (Jest) | 5 | T04 | [ ] |
| T22 | Page de réglages | 5 | T03 | [ ] |
| T23 | Abilities API (WP 7.0) | 5 | T08 | [ ] |

**Jalons :**
- **v0.2.0** : lot 1 terminé. C'est la première release distribuable.
- **v0.5.0** : lots 2 et 3.
- **v1.0.0** : lots 4 et 5.

---

## 3. Lot 1 — Distribution fiable

### T01 — Updater GitHub Releases
- **Objectif :** un site en version N voit et installe la version N+1 depuis l'écran Extensions.
- **Fichiers :**
  - créer `src/Updates/Updater.php` et `tests/php/Unit/UpdaterTest.php` ;
  - modifier `src/Plugin.php` et `plug-templates.php` (constantes).
- **Spécification :**
  - Dans `plug-templates.php`, ajouter `if ( ! defined( 'PLUGT_UPDATE_REPO' ) ) { define( 'PLUGT_UPDATE_REPO', 'https://github.com/denverdrogon/zuka/' ); }`. La définir dans `wp-config.php` permet de pointer vers un fork.
  - Créer la classe `PlugTemplates\Updates\Updater` :
    - le constructeur prend `string $repo, string $plugin_file, string $slug` ;
    - `register(): void` commence par `if ( ! apply_filters( 'plugt_update_enabled', true ) ) return;`.
  - Si `class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class )` est faux, retourner sans rien faire. C'est le cas d'un checkout de dev sans `vendor/`.
  - Sinon, appeler `PucFactory::buildUpdateChecker( $repo, $plugin_file, $slug )`, puis :
    - `$checker->getVcsApi()->enableReleaseAssets( '/plug-templates\.zip$/' )` ;
    - `$checker->setBranch( 'main' )` ;
    - si `defined( 'PLUGT_GITHUB_TOKEN' ) && PLUGT_GITHUB_TOKEN`, appeler `$checker->setAuthentication( PLUGT_GITHUB_TOKEN )`.
  - **Point d'enregistrement :** dans `Plugin::init()`, **avant** la vérification des prérequis. Une version qui corrige un prérequis doit pouvoir s'installer même quand le plugin est inactif.
  - Garder l'en-tête `Update URI: plug-templates`. Il empêche wordpress.org de proposer un autre plugin.
- **Tests (Brain Monkey) :**
  - le filtre `plugt_update_enabled` à `false` empêche toute construction ;
  - un PUC absent ne provoque pas d'erreur ;
  - le token est transmis s'il est défini. Isoler la construction derrière une méthode `protected build_checker()` surchargée dans le test.
- **Fait quand :**
  1. Installer sur wp-env le zip de la release `v0.2.0`.
  2. Publier une release `v0.2.1-test` (prerelease interdite : PUC ignore les prereleases).
  3. Dans Tableau de bord > Mises à jour > « Vérifier à nouveau », la mise à jour apparaît et s'installe.
  4. Après l'installation, le plugin reste actif, dans le dossier `plug-templates/`.
- **Doc :** ajouter dans `readme.txt` (T05) une section « Installation » qui explique `PLUGT_GITHUB_TOKEN`.

### T02 — Workflow de release + script de version
- **Objectif :** pousser un tag `vX.Y.Z` produit une GitHub Release avec `plug-templates.zip` prêt à installer.
- **Fichiers :** créer `.github/workflows/release.yml`, `bin/bump-version.php` et `bin/check-version.php`.
- **`bin/bump-version.php X.Y.Z` :** réécrit la version dans les quatre fichiers suivants, et refuse une version non semver.
  - en-tête `Version:` et constante `PLUGT_VERSION` dans `plug-templates.php` ;
  - `"version"` dans `package.json` ;
  - `Stable tag:` dans `readme.txt` (s'il existe).
- **`bin/check-version.php [X.Y.Z]` :**
  - vérifie que ces quatre valeurs sont identiques, et égales à l'argument s'il est fourni ;
  - code de sortie 1 avec un message clair sinon.
- **`release.yml`** :
  - déclenché sur `push: tags: ['v*']`, avec `permissions: contents: write`.
  - Étapes, dans l'ordre :
    1. checkout ;
    2. `shivammathur/setup-php@v2` en PHP 7.4 ;
    3. `php bin/check-version.php ${GITHUB_REF_NAME#v}` ;
    4. `composer install --no-dev --optimize-autoloader --no-interaction` ;
    5. `composer templates:check`. Si ce script exige les dépendances de dev, appeler directement `php bin/validate-templates.php && php bin/build-manifest.php --check` ;
    6. `actions/setup-node@v4` en Node 20, puis `npm ci && npm run build` ;
    7. `mkdir -p dist/plug-templates && rsync -a --exclude-from=.distignore --exclude=dist ./ dist/plug-templates/` ;
    8. vérifier que `dist/plug-templates/assets/build/motion.js` et `dist/plug-templates/vendor/autoload.php` existent, sinon échec ;
    9. `cd dist && zip -r plug-templates.zip plug-templates` ;
    10. `softprops/action-gh-release@v2` avec `files: dist/plug-templates.zip` et `generate_release_notes: true`.
- **À vérifier :**
  - `.distignore` exclut déjà `CLAUDE.md`, `contexte.md`, `bin`, `tests` et `assets/src`. Ajouter `optimisations.md`, `docs` et `dist`.
  - `node_modules` est exclu ; `vendor` (sans les dépendances de dev) doit rester dans le zip.
- **Fait quand :** le tag `v0.2.0` produit une release dont le zip s'installe par « Téléverser une extension » sur un WP propre et s'active sans erreur.

### T03 — Runner de migrations
- **Objectif :** exécuter une seule fois, et dans l'ordre, les transformations de données entre versions. Aujourd'hui, `plugt_db_version` est supprimée par `uninstall.php` mais jamais écrite.
- **Fichiers :**
  - créer `src/Migrations/Runner.php` et `tests/php/Unit/RunnerTest.php` ;
  - modifier `src/Plugin.php` : appeler le Runner dans `register_services()`, en premier.
- **Spécification :**
  - Constantes : `OPTION = 'plugt_db_version'`, `LOCK = 'plugt_migrating'` et `CURRENT = 1` (entier, incrémenté à chaque migration).
  - `run(): void` :
    1. lit `(int) get_option( OPTION, 0 )` et retourne si la valeur est déjà `>= CURRENT` ;
    2. pose un verrou `set_transient( LOCK, 1, 5 * MINUTE_IN_SECONDS )`, ou retourne si le verrou existe déjà ;
    3. pour chaque `$v` de `stored + 1` à `CURRENT`, appelle `$this->{"migrate_$v"}()` puis `update_option( OPTION, $v, false )` ;
    4. supprime le verrou.
  - Une exception arrête la boucle, laisse la version à la dernière migration réussie et appelle `error_log` avec le préfixe `[plugt]`.
  - `migrate_1()` ne fait rien. Elle marque l'installation initiale.
  - Les migrations suivantes, par exemple T19, s'ajoutent en `migrate_2()` et suivantes, en incrémentant `CURRENT`.
- **Tests :** installation neuve (0 → 1), aucune action si la version est à jour, un verrou présent empêche l'exécution, une exception laisse la version intacte.
- **Fait quand :** après activation sur wp-env, `wp option get plugt_db_version` renvoie `1`.

### T04 — CI JS + matrice Elementor
- **Fichiers :** modifier `.github/workflows/ci.yml`.
- **Spécification :**
  - Ajouter un job `js` : Node 20, puis `npm ci`, `npm run lint:js` et `npm run build`.
  - Ajouter un job `integration`, qui sert de base à T07 :
    - matrice `elementor: ['3.32.0', 'latest-stable']` × `wp: ['7.0', 'latest']` ;
    - générer à la volée un `.wp-env.override.json` avec `core` = `WordPress/WordPress#<wp>` et l'URL du zip Elementor correspondant (`https://downloads.wordpress.org/plugin/elementor.<version>.zip`) ;
    - lancer `npx wp-env start`, puis un test de fumée : `npx wp-env run cli wp plugin list --status=active --field=name`, qui doit contenir `elementor` et le plugin.
  - **Version de la matrice :** prendre la **dernière 3.x réellement publiée** (vérifier sur wordpress.org/plugins/elementor/advanced) et la reporter dans `PLUGT_MIN_ELEMENTOR` si elle est plus haute que `3.25.0`.
- **Fait quand :** la CI d'une PR affiche les jobs `php`, `js`, `plugin-check` et `integration` au vert.
- Écart : `npm run lint:js` échouait déjà (wp-scripts 30.7 passe `--eslintrc`, refusé par ESLint 9). Ajout de `eslint.config.js` (config flat `@wordpress/eslint-plugin`), script remplacé par `eslint assets/src`, formatage Prettier appliqué (`--fix`) et 5 erreurs corrigées dans `assets/src`.
- Écart : version Elementor 3.x de la matrice laissée à `3.32.0` (non vérifiée hors ligne) ; à confirmer sur wordpress.org/plugins/elementor/advanced et reporter dans `PLUGT_MIN_ELEMENTOR` si plus haute que `3.25.0`.

### T05 — `readme.txt`
- **Fichier :** créer `readme.txt`, au format standard WP.
- **Contenu :**
  - en-tête : `Contributors`, `Tags`, `Requires at least: 7.0`, `Tested up to: 7.0`, `Requires PHP: 7.4`, `Requires Plugins: elementor`, `Stable tag` (identique à `PLUGT_VERSION`), `License` ;
  - sections : Description, Installation (zip + `PLUGT_GITHUB_TOKEN`), FAQ (Elementor Pro non requis ; rien n'est écrasé ; désinstallation), Changelog (`= 0.2.0 =` …).
- `bin/check-version.php` (T02) vérifie le `Stable tag`.
- **Fait quand :** Plugin Check (CI) ne remonte aucune erreur readme, et le lien « Voir les détails » de la mise à jour affiche le changelog.

### T06 — i18n
- **Objectif :** un `.pot` à jour est versionné, et la CI échoue s'il ne l'est pas.
- **Fichiers :** créer `languages/plug-templates.pot` ; modifier `package.json` (script) et `ci.yml`.
- **Spécification :**
  - Ajouter le script npm `"i18n:pot": "wp-env run cli --env-cwd=wp-content/plugins/$(basename $PWD) wp i18n make-pot . languages/plug-templates.pot --exclude=node_modules,vendor,assets/build,tests,bin,dist --domain=plug-templates"`. Sous Windows, si `$(basename …)` ne fonctionne pas, utiliser le nom du dossier en dur.
  - Le chargement est déjà en place : `load_plugin_textdomain` dans `Plugin::init()`, `wp_set_script_translations` dans `LibraryPage` et `EditorIntegration`. Rien à modifier.
  - **En CI, dans le job `integration` :** régénérer le `.pot` dans un fichier temporaire, puis comparer les `msgid`, en ignorant l'en-tête et les dates. Échec en cas de différence.
  - **Chaînes en dur :** relire `assets/src/editor/inject.js`. `aria-label` et `data-tooltip` y valent « Zuka » sans `__()`. Les laisser : c'est un nom propre.
- **Fait quand :** `languages/plug-templates.pot` est commité et le contrôle CI passe.
- Écart : le `.pot` a été généré hors wp-env (wp-cli i18n-command en local, Docker non démarré) ; le contrôle CI compare uniquement les `msgid`/`msgctxt`, donc l'en-tête et les références de lignes n'ont pas d'effet. À valider par la première exécution de la CI. Le contrôle est placé à la fin du job `integration` (ne tourne donc que sur les entrées de la matrice).

---

## 4. Lot 2 et lot 3 — Outillage puis bibliothèque

### T07 — Tests E2E Playwright
- **Fichiers :**
  - créer `tests/e2e/` (`playwright.config.mjs`, `global-setup.mjs`, specs) ;
  - ajouter `@playwright/test` en devDependency (version figée) et le script `"test:e2e": "playwright test -c tests/e2e"` ;
  - ajouter les specs au job `integration` (T04).
- **Mise en place :**
  - `global-setup` se connecte avec `admin` / `password` sur `localhost:8888` et enregistre `storageState`.
  - Il active les conteneurs Elementor : `wp option update elementor_experiment-container active`.
- **Specs minimales :**
  1. `library.spec` : la page d'admin Zuka liste N cartes, où N est le nombre d'entrées de `templates/manifest.json`. La recherche « anif » filtre. Le filtre catégorie fonctionne.
  2. `import-page.spec` : l'action « Créer une page » sur `header-simple` crée un brouillon. L'éditeur Elementor s'ouvre, le post a la méta `_plugt_source = header-simple@<version>`, et le média `logo.webp` est dans la médiathèque.
  3. `no-overwrite.spec` : importer deux fois le même template donne deux posts distincts, et le premier n'est pas modifié (`post_modified` inchangé). Un seul attachment `logo.webp` existe grâce à la déduplication.
  4. `editor-insert.spec` : dans l'éditeur Elementor d'une page vide, le bouton Zuka ouvre la modale. « Insérer » ajoute les éléments, et après sauvegarde le rendu public contient le titre du template.
  5. `preview.spec` : l'aperçu en direct s'ouvre et les boutons Ordinateur, Tablette et Mobile changent la largeur de l'iframe.
  6. `motion.spec` : sur une page créée depuis `institutional-tabs`, `plugt-motion` (le JS) est chargé et cliquer sur le deuxième onglet l'active (`aria-selected="true"`). Sur une page sans classe `plugt-`, le script **n'est pas** chargé.
- **Fait quand :** `npm run test:e2e` passe en local et en CI sur toute la matrice.

### T08 — Commandes WP-CLI
- **Fichiers :**
  - créer `src/Cli/Command.php` et `tests/php/Unit/CliCommandTest.php` ;
  - modifier `src/Plugin.php` : `if ( defined( 'WP_CLI' ) && WP_CLI ) \WP_CLI::add_command( 'plugt', new Command( $this->repository, $this->importer ) );`.
- **Commandes :**
  - `wp plugt list [--format=table|json|csv] [--category=<cat>]` : affiche `id`, `code`, `title`, `type`, `category`, `version`, `variants` et `compatible`.
  - `wp plugt import <id> [--mode=page|library] [--title=<t>]` : appelle `Importer::import()`, affiche l'ID du post et l'URL d'édition. Le mode `editor` est refusé.
  - `wp plugt validate` : exécute le validateur sur `templates/` (même logique que `bin/validate-templates.php`) et renvoie le code de sortie 1 en cas d'erreur.
- **Règle :** pas de logique métier dans `Command`. Elle délègue tout au Repository, à l'Importer et au validateur.
- **Fait quand :** `npx wp-env run cli wp plugt import header-simple` crée une page.

### T09 — `wp plugt export` (une page Elementor vers un template)
- **Objectif :** produire un template à partir d'une page construite dans Elementor. C'est le levier principal pour remplir le catalogue (T11).
- **Fichiers :**
  - créer `src/Library/Exporter.php` (logique pure, testable) ;
  - ajouter à l'Adapter `get_document_data( int $post_id ): ?array` (elements + page_settings) et `get_kit_globals(): array` (couleurs et polices globales, avec leurs `_id` et leurs valeurs) ;
  - ajouter la commande `export` dans `Cli/Command.php`.
- **Usage :** `wp plugt export <post_id> <slug> --category=<cat> [--type=section|page] [--title=<t>] [--code=TPL-NN] [--force]`
- **Algorithme :**
  1. Lire `elements` et `page_settings` via l'Adapter. Refuser si le post n'est pas construit avec Elementor.
  2. **Médias :** pour chaque réglage `{id, url}` d'un attachment (image, background_image, gallery…), copier le fichier original dans `templates/<slug>/media/`. Si l'image fait plus de 1920 px, la convertir en `.webp` 1920 px max avec `wp_get_image_editor`. Remplacer l'url par `{{PLUGT_MEDIA}}/<fichier>` et `id` par `''`. Faire exactement l'inverse de `ContentTransformer`, qu'il faut lire avant d'écrire l'Exporter.
  3. **Globals :** une couleur ou une typographie en dur, strictement égale à une valeur du kit, devient une référence `__globals__` (`globals/colors?id=<_id>`). Lister dans la sortie CLI les valeurs restées en dur.
  4. **IDs d'éléments :** les régénérer, 7 caractères hexadécimaux, conformes à `ELEMENT_ID_PATTERN`.
  5. **Fichiers :** écrire `content.v3.json` (JSON indenté, `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`) et `meta.json` (version `1.0.0`, `variants: ["v3"]`, `added` = date du jour, voir T12).
  6. Lancer le validateur sur le dossier. En cas d'erreur, les afficher et sortir en 1, sans supprimer le dossier pour permettre la correction.
- **Sécurité :** refuser si `templates/<slug>/` existe déjà, sauf avec `--force`. Il n'y a pas de vignette : rappeler en sortie qu'il faut lancer `npm run thumbnails`.
- **Tests :** sur une fixture `elements` contenant une image, une couleur globale et une couleur en dur, vérifier le JSON produit, la liste des médias et l'avertissement.
- **Fait quand :** importer `page-accueil-agence`, l'exporter sous `test-roundtrip` puis lancer `composer templates:check` passe. Le contenu est équivalent à l'original, aux IDs près.
- **Écart :** (1) le validateur exige `thumbnail.webp` : l'export ignore cette seule erreur et rappelle de lancer `npm run thumbnails`, donc `composer templates:check` ne passe qu'une fois la vignette ajoutée. (2) Le média est localisé par `src/Library/MediaExporter.php` (redimensionnement WebP 1920 px), appelé via un callable pour que `Exporter` reste pur. (3) Le round-trip complet sur `page-accueil-agence` n'a pas été exécuté (wp-env indisponible) : à valider à la main.

### T10 — Avertissements qualité + guide d'auteur
- **Fichiers :** modifier `src/Library/TemplateValidator.php` et `bin/validate-templates.php` ; créer `docs/authoring.md`.
- **Spécification :**
  - `validate()` renvoie aujourd'hui une liste d'erreurs. Ajouter une méthode `warnings( string $dir ): array`, **non bloquante** et affichée par `bin/validate-templates.php` avec le préfixe `[WARN]`. Option `--strict` : les avertissements font alors échouer la commande.
  - **Avertissements :**
    - couleur `#xxxxxx` en dur dans un réglage de couleur alors que `__globals__` est absent pour ce réglage ;
    - `typography_font_family` en dur ;
    - média de plus de 300 Ko ;
    - widget `image` sans `alt` (ou `image.alt`) ;
    - texte contenant `lorem`, `ipsum` ou `TODO` ;
    - `thumbnail.webp` absent ou d'une taille différente de 600 × 400.
  - **`docs/authoring.md`** documente le cycle complet :
    1. construire la page dans Elementor (Free uniquement, couleurs et polices globales) ;
    2. ajouter les classes motion si besoin ;
    3. `wp plugt export` ;
    4. relire `meta.json` ;
    5. `npm run thumbnails` ;
    6. `composer templates:build`, puis `composer templates:check` ;
    7. PR.
  - Le guide contient aussi la checklist de relecture : mobile, contraste, alt, aucun texte factice, rendu dans le kit par défaut d'Elementor **et** dans le kit cible.
- **Fait quand :** `php bin/validate-templates.php` affiche les avertissements des 11 templates existants, et ceux qui sont pertinents sont corrigés dans la même PR.
- **Écart :** `warnings()` est un simple accès aux avertissements de `validate()` (qui les calcule déjà). Les 61 avertissements restants sont des couleurs en dur (budget-transparency, institutional-tabs, page-accueil-agence) : non corrigés, car les remplacer par des globales changerait le rendu sans le kit cible.

### T11 — Catalogue v1 (≥ 40 templates)
- **Constat :** il y a 11 templates, dont 6 ANIF. Les catégories `hero`, `bento`, `split` et `footer` sont vides.
- **Objectif chiffré :**
  - au moins 40 templates au total ;
  - au moins 3 templates par catégorie de `Schema::CATEGORIES`, y compris celles ajoutées en T12 ;
  - au moins 3 pages complètes.
- **Proposition par défaut**, à valider (Q1). Trois kits sectoriels, de 10 à 12 templates chacun, plus des sections génériques :
  - **Institution publique** (base ANIF existante) : hero, missions, chiffres clés, actualités, accès rapides, documents, FAQ, contact, footer, page d'accueil.
  - **Agence / PME de services :** hero, services (bento), à propos (split), méthode (tabs), références, témoignages, tarifs, CTA, contact, footer, page d'accueil.
  - **Association / ONG :** hero, cause, impact (bars, gauge), actions, équipe, partenaires, dons (CTA, sans formulaire Pro), FAQ, footer, page d'accueil.
- **Source :** porter en priorité les sections restantes du prototype Gemini (`other sources/…zip`, voir 0.3). Faire d'abord l'inventaire des 21 sections et de celles déjà portées, et le noter sous cette tâche.
- **Processus :** pour chaque template, suivre `docs/authoring.md` (T10). Livrer par PR de 5 templates au maximum.
- **Codes :** `code` suit la séquence `TPL-12`, `TPL-13`… sans trou ni doublon (le validateur vérifie l'unicité).
- **Fait quand :** `composer templates:check` passe, chaque template a sa vraie vignette et la page d'admin affiche au moins 40 cartes.

### T12 — Champs `added` / `changelog` + nouvelles catégories
- **Fichiers :**
  - `src/Library/Schema.php`, `src/Library/TemplateValidator.php`, `src/Library/ManifestBuilder.php` ;
  - `src/Rest/TemplatesController.php` (exposition) ;
  - `src/Admin/LibraryPage.php` (libellés) ;
  - les tests correspondants.
- **Spécification :**
  - `meta.json` reçoit deux champs optionnels, à valider :
    - `added` : date ISO `YYYY-MM-DD` ;
    - `changelog` : objet `{ "<semver>": "<texte ≤ 200 car.>" }`.
  - Les recopier dans le manifest et dans la réponse `GET /templates`.
  - Ajouter à `Schema::CATEGORIES` : `features`, `cta`, `faq`, `pricing`, `team`, `stats`, `contact`, `gallery`, `blog`, `partners`, `404`. Ajouter les libellés traduits dans `LibraryPage::category_labels()`.
  - Renseigner `added` pour les 11 templates existants à partir de `git log --diff-filter=A --format=%as -- templates/<slug>/meta.json`.
  - Régénérer le manifest.
- **Fait quand :** le validateur rejette `added: "hier"` et la réponse REST contient `added` et `changelog`.

### T13 — Suivi des imports
- **Objectif :** savoir ce qui a été importé et signaler les nouvelles versions, **sans jamais écraser**.
- **Fichiers :**
  - créer `src/Library/ImportLog.php` ;
  - ajouter à l'Adapter `find_imported_posts(): array` (requête sur la méta `_plugt_source`, tous types de posts confondus) ;
  - modifier `TemplatesController` (nouvelle route) ;
  - modifier `assets/src/library/Library.js`, `api.js` et `library.scss`.
- **REST :** `GET /plugt/v1/imports`, avec `permission_callback` sur `can_list`. Pour chaque post, renvoyer : `post_id`, `title`, `post_type`, `status`, `edit_url`, `template_id`, `imported_version`, `current_version`, `update_available` (vrai si `version_compare( current, imported, '>' )`) et `imported_at` (`post_date_gmt`).
  - Pagination : `per_page` ≤ 100.
  - Utiliser `WP_Query` avec `fields => ids` et `no_found_rows => false`.
- **Interface :**
  - Sur les cartes, ajouter un badge « Importé ×N » et un badge « v1.1 disponible » quand le template a une version plus récente que le dernier import.
  - Ajouter un onglet « Mes imports » (`TabPanel` WordPress) en vue tableau DataViews, avec les colonnes Titre, Template, Version importée, Version actuelle et Date.
  - Actions : « Modifier » (lien vers l'éditeur) et « Importer la nouvelle version ». Cette seconde action crée un **nouveau** post intitulé « <titre> (v<version>) ». Le modal affiche le `changelog` (T12).
- **Tests :** PHP sur le calcul de `update_available` ; E2E pour ajouter un cas à `no-overwrite.spec`.
- **Fait quand :**
  1. Importer `header-simple`.
  2. Passer sa version à `1.1.0` en local et reconstruire le manifest.
  3. Le badge et l'action apparaissent. L'action crée un second post et ne touche pas au premier.

### T14 — Favoris, nouveautés, filtres, vue mémorisée
- **Fichiers :** `Library.js` et `api.js` ; une route REST `POST /plugt/v1/favorites` qui reçoit `{ id, favorite: bool }`.
- **Stockage des favoris :** user meta `plugt_favorites` (liste de slugs validés contre le Repository), renvoyée dans `GET /templates` (`favorite: bool`).
- **Spécification :**
  - **Favoris :** action étoile sur la carte et filtre « Favoris ».
  - **Nouveautés :** tri `added` décroissant par défaut et badge « Nouveau » si `added` remonte à moins de 30 jours.
  - **Filtres :**
    - « Secteur » (`sector`) ;
    - « Animation » (`motion` non nul) ;
    - « Widgets » (champ `widgets`, opérateur `isAny`) ;
    - « Kit » (T15, à brancher plus tard).
  - **Vue mémorisée :** l'état `view` (filtres, tri, `perPage`, type de vue) est stocké dans `localStorage` sous la clé `plugt.view.<context>`, avec `try/catch`. Il est partagé entre l'admin et la modale si le contexte est identique.
- **Fait quand :** un favori survit au rechargement et les filtres et le tri sont conservés après rechargement de la page d'admin.

---

## 5. Lot 4 — Kits et Elementor 4

### T15 — Kits
- **Format :** `templates/kits/<kit-slug>/kit.json`.
```json
{
  "id": "institution",
  "title": "Institution publique",
  "description": "…",
  "version": "1.0.0",
  "thumbnail": "thumbnail.webp",
  "templates": ["page-accueil-institution", "anif-missions", "anif-actualites"],
  "home": "page-accueil-institution",
  "globals": {
    "colors": [{ "_id": "primary", "title": "Primaire", "color": "#14305F" }],
    "typography": [{ "_id": "primary", "title": "Titres", "typography_font_family": "Inter", "typography_font_weight": "600" }]
  }
}
```
- **Validation :**
  - dans `TemplateValidator` ou un nouveau `KitValidator`, contrôler que les templates existent, que `home` est de type `page` et que les `_id` des globals sont `primary`, `secondary`, `text` ou `accent` (les clés système Elementor), ou des slugs ;
  - le manifest reçoit une clé `kits` ;
  - le validateur rejette un `kits/` mal formé.
- **Adapter :**
  - `get_kit_globals(): array` existe depuis T09 ;
  - ajouter `update_kit_globals( array $globals ): bool`, qui sauvegarde d'abord les valeurs actuelles dans l'option `plugt_kit_backup_<timestamp>` (autoload `false`) ;
  - ajouter `restore_kit_globals( string $backup_key ): bool`.
- **Importer :** `import_kit( string $kit_id, bool $apply_globals ): array|WP_Error` :
  1. si `$apply_globals` est vrai, sauvegarde puis applique les globals ;
  2. importe chaque template en mode `page` (pour `type: page`) ou `library` (pour `type: section`), **en brouillon** ;
  3. renvoie un rapport `{ created: [...], failed: [...], backup_key }`.
- **REST :**
  - `GET /plugt/v1/kits` ;
  - `POST /plugt/v1/kits/import` avec `{ id, apply_globals }`. Capacités : `edit_pages`, `upload_files`, plus `manage_options` si `apply_globals` est vrai ;
  - `POST /plugt/v1/kits/restore` avec `{ backup_key }`, réservé à `manage_options`.
- **Interface :** un onglet « Kits » avec une carte par kit. Le modal de confirmation présente une case « Appliquer les couleurs et polices au site (une sauvegarde est créée) », décochée par défaut. Il affiche ensuite le rapport, avec les liens vers les posts et le bouton « Restaurer les couleurs précédentes ».
- **Tests :**
  - PHP : validation, rapport partiel en cas d'échec d'un template, sauvegarde créée avant la modification ;
  - E2E : l'import d'un kit crée N brouillons.
- **Fait quand :** les 3 kits de T11 sont importables, et la restauration remet exactement les globals précédents.

### T16 — Variantes v4 Atomic
- **Préalable, à faire en premier :**
  - Lire les sources d'Elementor 4.1 (`modules/atomic-widgets/`) et dresser la liste réelle des éléments Atomic Free. Mettre à jour `Schema::V4_FREE_ELEMENTS` et retirer le commentaire « To be confirmed ».
  - Documenter dans `docs/v4-format.md` la structure JSON d'un élément Atomic exporté (props typées, `styles`, classes, variables), en partant d'un export réel fait dans wp-env avec l'éditeur Atomic actif.
- **Validateur :** valider `content.v4.json` (types d'éléments autorisés, absence de Pro, classes motion portées par le mécanisme de classes v4). Vérifier comment v4 stocke des classes CSS libres : si elles sont incompatibles avec `plugt-m-*`, **arrêter et le signaler au propriétaire** avant d'aller plus loin.
- **Import :** `Importer::pick_variant()` choisit déjà v4 si Atomic est actif. Vérifier `Adapter::create_page()` et `create_library_template()` avec un contenu v4. Correspondance des globals : les Variables v4 jouent le rôle des `__globals__` (à confirmer pendant le préalable).
- **Portage :** produire `content.v4.json` pour 5 templates prioritaires (`page-accueil-agence`, `header-simple`, `section-temoignages`, `institutional-tabs`, `anif-missions`).
- **Tests :** ajouter à la matrice E2E (T07) le cas « Atomic actif ». Sur ce site, l'import de ces 5 templates utilise la variante `v4`, et `_plugt_variant = v4`.
- **Fait quand :** les 5 templates s'importent et s'affichent correctement avec l'éditeur Atomic, et les 11 autres restent importables en v3 sur ce même site (cohabitation).

---

## 6. Lot 5 — Qualité, performance, intégrations

### T17 — Import atomique + hooks
- **Fichiers :** `src/Library/Importer.php`, `src/Library/MediaImporter.php` et leurs tests.
- **Spécification :**
  - Pendant `prepare()`, collecter les IDs des attachments **créés** (pas ceux réutilisés par déduplication). Si la création du post échoue, ou si un média obligatoire est introuvable, faire `wp_delete_attachment( $id, true )` sur ces IDs et renvoyer l'erreur.
  - Aujourd'hui, un média qui échoue renvoie `null`, et le contenu garde une URL cassée. Décider d'un comportement par défaut :
    - **avertissement** : la réponse REST contient `warnings: [...]` et l'interface l'affiche ;
    - **échec** : si le filtre `plugt_strict_media` est à `true`.
  - **Hooks :**
    - filtre `apply_filters( 'plugt_import_content', $content, $template_id, $variant )` juste avant la création ;
    - action `do_action( 'plugt_after_import', $post_id, $template_id, $mode )` après la pose des métas.
  - Ajouter le texte alternatif des médias : si `meta.json` contient `media_alt: { "<fichier>": "<alt>" }`, renseigner `_wp_attachment_image_alt` à la création. Ajouter ce champ au validateur.
- **Fait quand :** un test unitaire simule un `create_page` en `WP_Error` et vérifie que les attachments créés sont supprimés et que ceux réutilisés sont conservés.

### T18 — Motion chargé par module
- **Objectif :** une page qui n'utilise que `reveal` ne charge pas le JS et le CSS de `tabs`, `gauge`, etc.
- **Fichiers :** `webpack.config.js`, `assets/src/motion/index.js`, `src/Frontend/MotionAssets.php` et `tests/php/Unit/MotionAssetsTest.php`.
- **Spécification :**
  - **Build :** une entrée `motion-core` (`base.css` + `core.js`, qui expose `window.plugtMotion = { registerModule, boot }`) et une entrée `motion-<module>` par fichier de `modules/`, générée dynamiquement en lisant le dossier dans `webpack.config.js`. Ajouter un module ne doit toujours rien modifier d'autre (contrat de `CLAUDE.md`).
  - **PHP :**
    - `uses_motion()` devient `modules_used( string $classes ): string[]`. Il extrait `<module>` de `plugt-m-<module>(__|--|$)` et ignore les modules absents de `Schema::MOTION_MODULES` ;
    - `enqueue_for_queried_post()` lit les données du post une seule fois via l'Adapter. Ajouter `Adapter::post_data_string( int $post_id ): string` si nécessaire ;
    - enregistrer `plugt-motion-<module>` avec la dépendance `plugt-motion-core`.
  - Les utilitaires `plugt-glass` restent dans `motion-core.css`.
  - **Aperçu de l'éditeur :** `on_preview_enqueue` charge tous les modules.
- **Mettre à jour `CLAUDE.md`** (section Couche motion) pour décrire ce découpage.
- **Fait quand :** sur la page E2E `motion.spec`, seuls `motion-core` et `motion-tabs` sont chargés. Les autres specs motion passent.

### T19 — Nettoyage des aperçus orphelins
- **Fichiers :** `src/Library/Previewer.php` et `src/Migrations/Runner.php` (`migrate_2` planifie le cron).
- **Spécification :**
  - Planifier un évènement quotidien `plugt_cleanup_previews` avec `wp_schedule_event`, à l'activation et dans `migrate_2`. Le déplanifier dans `register_deactivation_hook` et dans `uninstall.php`.
  - Supprimer les posts `_plugt_preview` dont le template n'existe plus, ou dont `_plugt_preview_version` diffère de `Previewer::stamp()` du template actuel.
  - Supprimer par lots de 50 au maximum.
- **Fait quand :** un test unitaire vérifie la sélection des posts à supprimer.

### T20 — Accessibilité
- **Modules `tabs` et `accordion` :** respecter les patterns ARIA APG.
  - **Tabs :**
    - `role=tablist` / `tab` / `tabpanel` ;
    - flèches gauche/droite, Home et End ;
    - `tabindex` itinérant ;
    - `aria-controls` et `aria-labelledby` croisés.
  - **Accordion :** l'élément déclencheur est un vrai `button` ou porte `role=button` + `tabindex=0`, réagit à Entrée et Espace, et porte `aria-expanded` et `aria-controls`.
  - L'autoplay de `tabs--autoplay` s'arrête au focus clavier et respecte `prefers-reduced-motion` (déjà le cas pour les animations, à vérifier pour l'autoplay).
- **Modales (aperçu et éditeur) :**
  - le focus va dans la modale à l'ouverture et y reste piégé ;
  - Échap ferme la modale ;
  - le focus revient sur le déclencheur à la fermeture ;
  - le titre est annoncé.
- **Vérification :** ajouter `@axe-core/playwright` aux specs E2E `library` et `motion`. Aucune violation `serious` ni `critical` n'est tolérée.
- **Fait quand :** les specs axe passent et la navigation au clavier est testée en E2E sur tabs et accordion.

### T21 — Tests JS (Jest)
- **Fichiers :** `assets/src/**/__tests__/*.test.js` et le script `"test:js": "wp-scripts test-unit-js"`, à ajouter au job CI `js`.
- **Couverture minimale :**
  - **`core.js`** :
    - `registerModule` et `boot` ;
    - `setActive` émet `plugt:activate` et met à jour `aria-selected` / `aria-expanded` ;
    - aucun masquage sans `.plugt-motion-ready` ;
    - tout reste visible sous `.elementor-editor-active` ;
    - `prefers-reduced-motion` est respecté (mock de `matchMedia`).
  - **Un test par module** (DOM jsdom minimal).
  - **`Library.js`** : les actions d'import sont désactivées pour un item `compatible: false` ; les filtres et le tri par défaut sont corrects.
- **Fait quand :** `npm run test:js` passe en CI.

### T22 — Page de réglages
- **Fichiers :** créer `src/Admin/SettingsPage.php`. L'option est `plugt_settings`, déjà supprimée par `uninstall.php`.
- **Réglages proposés**, via la Settings API WordPress avec nonce et `sanitize_callback` strict :
  - `default_mode` : `page` ou `library` ;
  - `post_status` : `draft` (par défaut) ou `publish` ;
  - `motion_enabled` : booléen, vrai par défaut. Faux désactive tout chargement front de la couche motion ;
  - `import_capability` : `edit_posts` (par défaut) ou `edit_pages` ou `manage_options`.
- **Branchements :**
  - `TemplatesController::can_import()` et `LibraryPage::config()` lisent `import_capability` ;
  - `Adapter::create_page()` et `create_library_template()` reçoivent `post_status` ;
  - `MotionAssets::register()` sort immédiatement si `motion_enabled` est faux.
- **Emplacement :** sous-menu « Réglages » sous le menu Zuka, réservé à `manage_options`.
- **Fait quand :** chaque réglage a un test unitaire de son effet.

### T23 — Abilities API (WP 7.0)
- **Fichiers :** créer `src/Abilities/Registrar.php`, enregistré sur le hook d'initialisation des abilities de WP 7.0. Vérifier son nom exact dans le core 7.0 (`wp_abilities_api_init`) ainsi que la signature de `wp_register_ability`.
- **Abilities**, avec `input_schema` et `output_schema` JSON :
  - `plugt/list-templates` : filtres `category` et `type` optionnels ;
  - `plugt/import-template` : `id`, `mode` (`page` ou `library`), `title` ;
  - `plugt/import-kit` : après T15.
- **Règles :**
  - `permission_callback` identiques aux routes REST, avec réutilisation des méthodes du contrôleur ou d'un service commun ;
  - aucune logique dupliquée : tout passe par Repository et Importer.
- **Garde :** `if ( ! function_exists( 'wp_register_ability' ) ) return;`.
- **Fait quand :** `wp eval 'var_dump( wp_get_ability( "plugt/list-templates" )->execute( array() ) );'` renvoie la liste.

---

## 7. Hors périmètre v1.0 (ne pas faire sans accord)
- Widgets Elementor maison (D6).
- Templates bilingues ou contenu de démonstration par langue.
- Nouveaux modules motion. À ajouter seulement si un template de T11 en a besoin ; ils suivent alors le contrat de `CLAUDE.md`.
- Télémétrie, même locale.
- Changement de slug ou de text domain (D1).
