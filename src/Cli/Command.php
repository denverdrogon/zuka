<?php
/**
 * `wp plugt` commands. No business logic here: everything is delegated.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Cli;

use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Library\Exporter;
use PlugTemplates\Library\Importer;
use PlugTemplates\Library\ManifestBuilder;
use PlugTemplates\Library\MediaExporter;
use PlugTemplates\Library\Repository;
use PlugTemplates\Library\Schema;
use PlugTemplates\Library\TemplateValidator;
use WP_Error;

/**
 * Manages the Zuka template library.
 */
class Command {

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Importer.
	 *
	 * @var Importer
	 */
	private Importer $importer;

	/**
	 * Elementor adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $elementor;

	/**
	 * Absolute path of templates/.
	 *
	 * @var string
	 */
	private string $templates_dir;

	/**
	 * Output.
	 *
	 * @var Console
	 */
	private Console $console;

	/**
	 * Constructor.
	 *
	 * @param Repository   $repository    Repository.
	 * @param Importer     $importer      Importer.
	 * @param Adapter      $elementor     Elementor adapter.
	 * @param string       $templates_dir Absolute path of templates/.
	 * @param Console|null $console       Output.
	 */
	public function __construct( Repository $repository, Importer $importer, Adapter $elementor, string $templates_dir, ?Console $console = null ) {
		$this->repository    = $repository;
		$this->importer      = $importer;
		$this->elementor     = $elementor;
		$this->templates_dir = $templates_dir;
		$this->console       = $console ?? new Console();
	}

	/**
	 * Lists the templates of the library.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * [--category=<category>]
	 * : Only templates of this category.
	 *
	 * @subcommand list
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function list_( array $args, array $assoc_args ): void {
		unset( $args );
		$category = $assoc_args['category'] ?? '';
		$rows     = array();

		foreach ( $this->repository->all() as $entry ) {
			if ( '' !== $category && (string) ( $entry['category'] ?? '' ) !== $category ) {
				continue;
			}
			$rows[] = array(
				'id'         => (string) $entry['id'],
				'code'       => (string) ( $entry['code'] ?? '' ),
				'title'      => (string) $entry['title'],
				'type'       => (string) $entry['type'],
				'category'   => (string) $entry['category'],
				'version'    => (string) $entry['version'],
				'variants'   => implode( ',', (array) ( $entry['variants'] ?? array() ) ),
				'compatible' => null === $this->importer->incompatibility( $entry ) ? 'yes' : 'no',
			);
		}

		$this->console->table(
			$assoc_args['format'] ?? 'table',
			$rows,
			array( 'id', 'code', 'title', 'type', 'category', 'version', 'variants', 'compatible' )
		);
	}

	/**
	 * Imports a template as a draft page or a library template.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template id (folder name).
	 *
	 * [--mode=<mode>]
	 * : Where to create the content.
	 * ---
	 * default: page
	 * options:
	 *   - page
	 *   - library
	 * ---
	 *
	 * [--title=<title>]
	 * : Title of the created post (defaults to the template title).
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function import( array $args, array $assoc_args ): void {
		$mode = $assoc_args['mode'] ?? Importer::MODE_PAGE;
		if ( ! in_array( $mode, array( Importer::MODE_PAGE, Importer::MODE_LIBRARY ), true ) ) {
			$this->console->error( 'Mode invalide : utilisez « page » ou « library ».' );
			return;
		}

		$result = $this->importer->import( (string) ( $args[0] ?? '' ), $mode, $assoc_args['title'] ?? '' );
		if ( $result instanceof WP_Error ) {
			$this->console->error( $result->get_error_message() );
			return;
		}

		$this->console->success( sprintf( 'Template « %s » importé (ID %d).', (string) $result['template_id'], (int) $result['post_id'] ) );
		$this->console->line( (string) $result['edit_url'] );
	}

	/**
	 * Exports an Elementor page as a template (templates/<slug>/).
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : Post built with Elementor.
	 *
	 * <slug>
	 * : Template id (folder name, kebab-case).
	 *
	 * --category=<category>
	 * : Category key.
	 *
	 * [--type=<type>]
	 * : Template type.
	 * ---
	 * default: section
	 * options:
	 *   - section
	 *   - page
	 * ---
	 *
	 * [--title=<title>]
	 * : Template title (defaults to the post title).
	 *
	 * [--code=<code>]
	 * : Library code, e.g. TPL-12 (defaults to the next free one).
	 *
	 * [--force]
	 * : Overwrite an existing templates/<slug>/ folder.
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function export( array $args, array $assoc_args ): void {
		$post_id  = (int) ( $args[0] ?? 0 );
		$slug     = (string) ( $args[1] ?? '' );
		$category = (string) ( $assoc_args['category'] ?? '' );
		$type     = (string) ( $assoc_args['type'] ?? 'section' );

		if ( ! in_array( $category, Schema::CATEGORIES, true ) ) {
			$this->console->error( 'Catégorie inconnue (autorisées : ' . implode( ', ', Schema::CATEGORIES ) . ').' );
			return;
		}
		if ( ! in_array( $type, Schema::TYPES, true ) ) {
			$this->console->error( 'Type invalide (autorisés : ' . implode( ', ', Schema::TYPES ) . ').' );
			return;
		}

		$data = $this->elementor->get_document_data( $post_id );
		if ( null === $data ) {
			$this->console->error( sprintf( 'Le contenu %d n\'existe pas ou n\'est pas construit avec Elementor.', $post_id ) );
			return;
		}

		$exporter = new Exporter();
		$media    = new MediaExporter();
		$result   = $exporter->transform( $data['elements'], $data['page_settings'], $this->elementor->get_kit_globals(), array( $media, 'locate' ) );

		$title = isset( $assoc_args['title'] ) && '' !== trim( $assoc_args['title'] ) ? $assoc_args['title'] : ( '' !== $data['title'] ? $data['title'] : $slug );
		$code  = $assoc_args['code'] ?? $exporter->next_code( $this->templates_dir );
		$meta  = $exporter->meta( $slug, $title, $type, $category, $code, gmdate( 'Y-m-d' ) );

		try {
			$dir = $exporter->write( $this->templates_dir, $slug, $meta, $result, isset( $assoc_args['force'] ) );
		} catch ( \RuntimeException $e ) {
			$media->cleanup();
			$this->console->error( $e->getMessage() );
			return;
		}
		$media->cleanup();

		foreach ( array_keys( $result['media'] ) as $name ) {
			$this->console->line( 'Média copié : media/' . $name );
		}
		foreach ( $result['hardcoded'] as $value ) {
			$this->console->warning( 'Valeur restée en dur : ' . $value );
		}
		foreach ( $result['warnings'] as $message ) {
			$this->console->warning( $message );
		}

		// The thumbnail is generated afterwards (npm run thumbnails): its absence is expected here.
		$report = ( new TemplateValidator() )->validate( $dir );
		$errors = array_values( array_diff( $report['errors'], array( 'thumbnail.webp manquant.' ) ) );
		foreach ( $report['warnings'] as $message ) {
			$this->console->warning( $message );
		}
		if ( array() !== $errors ) {
			foreach ( $errors as $message ) {
				$this->console->line( '✗ ' . $message );
			}
			$this->console->error( sprintf( 'Export écrit dans templates/%s mais invalide : corrigez puis lancez « wp plugt validate ».', $slug ) );
			return;
		}

		$this->console->success( sprintf( 'Template « %s » exporté dans templates/%s (code %s).', $title, $slug, $code ) );
		$this->console->line( 'Reste à faire : relire meta.json, « npm run thumbnails », puis « composer templates:build ».' );
	}

	/**
	 * Validates every template of templates/ (same rules as bin/validate-templates.php).
	 *
	 * Exits with code 1 when at least one template has errors.
	 *
	 * ## OPTIONS
	 *
	 * [--strict]
	 * : Warnings also fail.
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function validate( array $args, array $assoc_args ): void {
		unset( $args );
		$reports  = ( new ManifestBuilder() )->build( $this->templates_dir )['reports'];
		$errors   = 0;
		$warnings = 0;

		foreach ( $reports as $slug => $report ) {
			$this->console->line( ( array() === $report['errors'] ? '[OK]   ' : '[FAIL] ' ) . $slug );
			foreach ( $report['errors'] as $message ) {
				$this->console->line( '       ✗ ' . $message );
			}
			foreach ( $report['warnings'] as $message ) {
				$this->console->line( '       [WARN] ' . $message );
			}
			$errors   += count( $report['errors'] );
			$warnings += count( $report['warnings'] );
		}

		$summary = sprintf( '%d template(s), %d erreur(s), %d avertissement(s).', count( $reports ), $errors, $warnings );
		if ( $errors > 0 || ( isset( $assoc_args['strict'] ) && $warnings > 0 ) ) {
			$this->console->error( $summary );
			return;
		}
		$this->console->success( $summary );
	}
}
