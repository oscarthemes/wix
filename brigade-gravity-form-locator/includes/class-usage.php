<?php
/**
 * A single detected form usage.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized usage record returned by every detector.
 *
 * Detectors only describe *what* they found (form reference, method, source, confidence);
 * the scanner adds *where* (the scan item) and resolves the form reference against the
 * registered Gravity Forms.
 */
class Usage {

	const CONFIRMED = 'confirmed';
	const LIKELY    = 'likely';
	const POSSIBLE  = 'possible';

	const METHOD_SHORTCODE       = 'shortcode';
	const METHOD_BLOCK           = 'block';
	const METHOD_WIDGET          = 'widget';
	const METHOD_BUILDER_ELEMENT = 'builder_element';
	const METHOD_PHP_FUNCTION    = 'php_function';
	const METHOD_SETTING         = 'setting_reference';
	const METHOD_MARKUP          = 'rendered_markup';

	/**
	 * Form reference as written in the source: a numeric ID or a form title.
	 *
	 * @var string
	 */
	public $form_ref = '';

	/**
	 * Builder label (Elementor, Divi, Gutenberg…). Empty means "use the scan item's builder".
	 *
	 * @var string
	 */
	public $builder = '';

	/**
	 * One of the METHOD_* constants.
	 *
	 * @var string
	 */
	public $method = self::METHOD_SHORTCODE;

	/**
	 * Human-readable description of where inside the location the reference was found.
	 *
	 * @var string
	 */
	public $source = '';

	/**
	 * One of CONFIRMED, LIKELY or POSSIBLE.
	 *
	 * @var string
	 */
	public $confidence = self::CONFIRMED;

	/**
	 * Number of identical matches found inside the same location and source.
	 *
	 * @var int
	 */
	public $occurrences = 1;

	/**
	 * Creates a usage.
	 *
	 * @param string $form_ref   Form ID or title.
	 * @param string $method     METHOD_* constant.
	 * @param string $source     Where in the location it was found.
	 * @param string $confidence Confidence level.
	 * @param string $builder    Optional builder override.
	 * @return self
	 */
	public static function make( $form_ref, $method, $source, $confidence = self::CONFIRMED, $builder = '' ) {
		$usage             = new self();
		$usage->form_ref   = trim( (string) $form_ref );
		$usage->method     = $method;
		$usage->source     = $source;
		$usage->confidence = $confidence;
		$usage->builder    = $builder;
		return $usage;
	}

	/**
	 * Translated labels for implementation methods.
	 *
	 * @return array<string,string>
	 */
	public static function method_labels() {
		return array(
			self::METHOD_SHORTCODE       => __( 'Shortcode', 'brigade-gravity-form-locator' ),
			self::METHOD_BLOCK           => __( 'Gravity Forms block', 'brigade-gravity-form-locator' ),
			self::METHOD_WIDGET          => __( 'Gravity Forms widget', 'brigade-gravity-form-locator' ),
			self::METHOD_BUILDER_ELEMENT => __( 'Builder element', 'brigade-gravity-form-locator' ),
			self::METHOD_PHP_FUNCTION    => __( 'PHP template function', 'brigade-gravity-form-locator' ),
			self::METHOD_SETTING         => __( 'Setting reference', 'brigade-gravity-form-locator' ),
			self::METHOD_MARKUP          => __( 'Rendered form markup', 'brigade-gravity-form-locator' ),
		);
	}

	/**
	 * Translated labels for confidence levels.
	 *
	 * @return array<string,string>
	 */
	public static function confidence_labels() {
		return array(
			self::CONFIRMED => __( 'Confirmed', 'brigade-gravity-form-locator' ),
			self::LIKELY    => __( 'Likely', 'brigade-gravity-form-locator' ),
			self::POSSIBLE  => __( 'Possible', 'brigade-gravity-form-locator' ),
		);
	}
}
