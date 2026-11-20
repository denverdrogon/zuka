<?php
/**
 * Template format constants: allowed types, element types and Elementor Free widgets.
 *
 * Pure PHP (no WordPress), shared by the runtime and the CLI scripts in bin/.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

/**
 * Template schema.
 */
final class Schema {

	/** Placeholder replaced by the importer with the URL of the template's media/ folder. */
	public const MEDIA_TOKEN = '{{PLUGT_MEDIA}}';

	public const VARIANT_V3 = 'v3';
	public const VARIANT_V4 = 'v4';

	/**
	 * Template types. Header/footer site-wide assignment needs Elementor Pro, so
	 * those are delivered as `section` templates with a `header`/`footer` category.
	 */
	public const TYPES = array( 'page', 'section' );

	public const REQUIRED_META = array( 'id', 'title', 'type', 'category', 'version', 'variants' );

	/**
	 * Category keys (closed list). Labels are translated in Admin\LibraryPage.
	 */
	public const CATEGORIES = array(
		'hero',
		'carousel',
		'bento',
		'split',
		'tabs',
		'public-sector',
		'header',
		'footer',
		'testimonials',
		'landing',
	);

	/** Optional `code` (library reference, unique). */
	public const CODE_PATTERN = '/^TPL-\d{2,3}$/';

	/** Optional `accent` (admin display only, never used in content). */
	public const ACCENT_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

	/** Max length of the optional `sector` and `motion` labels. */
	public const SECTOR_MAX_LENGTH = 60;
	public const MOTION_MAX_LENGTH = 80;

	/** Optional looping preview clip shown in the admin library. */
	public const PREVIEW_FILE      = 'preview.webm';
	public const PREVIEW_MAX_BYTES = 3145728;

	/**
	 * Structural element types of the v3 editor.
	 */
	public const V3_ELEMENT_TYPES = array( 'container', 'section', 'column', 'widget' );

	/**
	 * Widgets shipped with Elementor Free (v3). Anything else is rejected.
	 */
	public const V3_FREE_WIDGETS = array(
		'accordion',
		'alert',
		'audio',
		'button',
		'counter',
		'divider',
		'google_maps',
		'heading',
		'html',
		'icon',
		'icon-box',
		'icon-list',
		'image',
		'image-box',
		'image-carousel',
		'image-gallery',
		'menu-anchor',
		'nested-accordion',
		'nested-tabs',
		'progress',
		'rating',
		'read-more',
		'shortcode',
		'sidebar',
		'social-icons',
		'spacer',
		'star-rating',
		'tabs',
		'testimonial',
		'text-editor',
		'text-path',
		'toggle',
		'video',
	);

	/**
	 * Atomic (v4) element types available in Elementor Free.
	 * To be confirmed against Elementor 4.x source before shipping v4 variants (phase 7).
	 */
	public const V4_FREE_ELEMENTS = array(
		'e-button',
		'e-div-block',
		'e-divider',
		'e-flexbox',
		'e-heading',
		'e-image',
		'e-paragraph',
		'e-svg',
		'e-youtube',
	);

	/**
	 * Motion layer: CSS classes (Free `css_classes` / `_css_classes` settings)
	 * read by assets/src/motion. A class `plugt-m-<module>` marks a module root;
	 * `plugt-m-<module>__<part>` marks one of its parts and `--<modifier>` a variant.
	 * Each module has its own assets/src/motion/modules/<module>.{js,css}.
	 */
	public const MOTION_PREFIX = 'plugt-m-';

	public const MOTION_MODULES = array( 'accordion', 'bars', 'gauge', 'reveal', 'tabs' );

	/**
	 * Purely visual helper classes of the motion stylesheet.
	 */
	public const MOTION_UTILITIES = array( 'plugt-glass', 'plugt-glass--dark' );

	/**
	 * Settings holding CSS classes (containers / widgets).
	 */
	public const CLASS_SETTINGS = array( 'css_classes', '_css_classes' );

	/**
	 * Setting keys (or key prefixes) that only Elementor Pro understands.
	 */
	public const PRO_SETTING_PREFIXES = array(
		'motion_fx_',
		'sticky',
		'custom_css',
		'e_display_conditions',
		'_attributes',
		'background_motion_fx_',
	);
}
