<?php
/**
 * `wp plugt` commands. No business logic here: everything is delegated.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Cli;

use PlugTemplates\Library\Importer;
use PlugTemplates\Library\ManifestBuilder;
use PlugTemplates\Library\Repository;
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
	 * @param string       $templates_dir Absolute path of templates/.
	 * @param Console|null $console       Output.
	 */
	public function __construct( Repository $repository, Importer $importer, string $templates_dir, ?Console $console = null ) {
		$this->repository    = $repository;
		$this->importer      = $importer;
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
