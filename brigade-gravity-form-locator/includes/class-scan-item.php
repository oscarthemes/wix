<?php
/**
 * A location being scanned.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * One scannable location: a post, a widget, a theme file or a stored builder option.
 */
class Scan_Item {

	const TYPE_POST     = 'post';
	const TYPE_PATTERN  = 'pattern';
	const TYPE_TEMPLATE = 'template';
	const TYPE_WIDGET   = 'widget';
	const TYPE_FILE     = 'theme_file';
	const TYPE_OPTION   = 'builder_option';

	/**
	 * One of the TYPE_* constants.
	 *
	 * @var string
	 */
	public $type = self::TYPE_POST;

	/**
	 * Unique key of the location, e.g. "post:42", "widget:text-3", "file:twentytwenty/footer.php".
	 *
	 * @var string
	 */
	public $key = '';

	/**
	 * Numeric ID when the location has one (post ID), otherwise 0.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Post type, widget base, theme file path or option name.
	 *
	 * @var string
	 */
	public $subtype = '';

	/**
	 * Display title.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Post status, or "active" / "inactive" for widgets, files and options.
	 *
	 * @var string
	 */
	public $status = '';

	/**
	 * Public URL captured at scan time, if any.
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Default builder label for usages found in this location.
	 *
	 * @var string
	 */
	public $builder = '';

	/**
	 * Main text content (post_content, file contents…).
	 *
	 * @var string
	 */
	public $content = '';

	/**
	 * Structured data (widget instance, option value…).
	 *
	 * @var mixed
	 */
	public $data = null;

	/**
	 * The post object for post-like items.
	 *
	 * @var \WP_Post|null
	 */
	public $post = null;

	/**
	 * ID of the detector that owns $content. Only that detector parses the main content,
	 * which prevents two detectors reporting the same shortcode under different builders.
	 *
	 * @var string
	 */
	public $content_owner = '';

	/**
	 * Returns a post meta value for post-like items.
	 *
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public function meta( $key ) {
		return $this->post ? get_post_meta( $this->post->ID, $key, true ) : '';
	}

	/**
	 * Whether this item is backed by a post row.
	 *
	 * @return bool
	 */
	public function is_post_like() {
		return null !== $this->post;
	}
}
