# CLAUDE.md — Plug Templates

## But
Plugin WordPress privé fournissant des templates Elementor personnalisés, livrés dans le plugin (aucun serveur distant), importables depuis une modale dans l'éditeur ou une page d'admin. Cible Elementor v3 et v4 (Atomic), Elementor Free uniquement. Diffusion privée, mises à jour via GitHub Releases. Voir `contexte.md` pour le détail.

## Conventions
- Slug : `plug-templates` · text domain : `plug-templates` · préfixe : `plugt_` (options, hooks, fonctions, REST `plugt/v1`).
- Namespace PHP `PlugTemplates\` en PSR-4 dans `src/`.
- Standard de code : WPCS.
- PHP 7.4 minimum, testé jusqu'à 8.4 (pas de syntaxe PHP 8+ dans le code du plugin).
- WordPress 6.6 minimum, « Tested up to » 7.0.

## Règle d'or
Tout appel interne à Elementor (classes, hooks, documents, API v3/v4) passe par `src/Elementor/Adapter.php`. Aucun autre fichier ne référence directement Elementor.

## Templates
- Compatibles **Elementor Free uniquement** : aucun widget, condition ou fonction Pro.
- Liste blanche de widgets, vérifiée par `bin/validate-templates.php` (aussi en CI).
- Par template : `templates/<slug>/` avec `meta.json`, `content.v3.json` et/ou `content.v4.json` (optionnels), `media/`, `thumbnail.webp`. Index : `templates/manifest.json` (généré par `bin/build-manifest.php`).
- Utiliser les Global Colors/Fonts (`__globals__`) plutôt que des valeurs en dur.

## Sécurité
- Nonces sur toute action d'admin ; `permission_callback` explicite sur chaque route REST.
- Sanitation des entrées, échappement des sorties, requêtes préparées.
- Aucun appel externe, hors updater (GitHub Releases).
- Ne jamais écraser du contenu déjà importé.

## Commandes prévues
```
composer lint        # PHPCS (WPCS)
composer phpstan     # analyse statique
composer test        # PHPUnit
npm run build        # assets/src -> assets/build
npx wp-env start     # environnement WP local (wp-env)
```

## Structure prévue
```
plug-templates.php        # en-têtes (Requires Plugins: elementor, Update URI)
uninstall.php
src/                      # Plugin, Elementor/Adapter, Library/{Repository,Importer},
                          # Rest/TemplatesController, Admin/LibraryPage,
                          # Editor/EditorIntegration, Updates/Updater,
                          # Migrations/Runner, Abilities/
templates/                # manifest.json + <slug>/ (meta, content.v3/v4, media, thumbnail)
bin/                      # build-manifest.php, validate-templates.php
assets/src, assets/build  # sources / build (build ignoré par git)
languages/
tests/
```
