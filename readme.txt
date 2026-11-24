=== Zuka ===
Contributors: zuka
Tags: elementor, templates, library, sections, design
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: elementor
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bibliothèque de templates Elementor personnalisés, compatibles Elementor Free (v3 et v4 Atomic).

== Description ==

Zuka fournit une bibliothèque de templates Elementor livrés dans le plugin. Aucun serveur distant n'est contacté : les templates s'importent depuis une modale dans l'éditeur ou depuis une page d'administration.

* Compatible Elementor Free uniquement : aucun widget ni réglage Pro.
* Fonctionne avec Elementor v3 et v4 (Atomic).
* Les contenus déjà importés ne sont jamais écrasés.

== Installation ==

1. Téléversez `plug-templates.zip` depuis Extensions > Ajouter > Téléverser une extension, puis activez le plugin. Elementor doit être installé et actif.
2. Les mises à jour sont publiées sur GitHub Releases. Le dépôt étant privé, ajoutez dans `wp-config.php` un jeton d'accès en lecture seule (fine-grained, permission `Contents` en lecture) :

`define( 'PLUGT_GITHUB_TOKEN', 'github_pat_...' );`

3. Pour pointer vers un fork, définissez `PLUGT_UPDATE_REPO` avec l'URL du dépôt. Pour désactiver les mises à jour, utilisez le filtre `plugt_update_enabled`.

== Frequently Asked Questions ==

= Elementor Pro est-il nécessaire ? =

Non. Les templates n'utilisent que des widgets et des fonctions d'Elementor Free.

= Un template déjà importé est-il écrasé ? =

Non. Chaque import crée un nouveau contenu ; rien n'est remplacé.

= Que se passe-t-il à la désinstallation ? =

Les réglages du plugin sont supprimés. Les pages et templates déjà importés restent en place.

== Changelog ==

= 0.1.0 =
* Première version : bibliothèque de templates, import depuis l'éditeur, couche motion.
