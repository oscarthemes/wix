<?php
/**
 * State shared with detectors during a scan.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Gives detectors access to settings and lets them report notes (unresolved
 * references, skipped data) without writing results themselves.
 */
class Scan_Context {

	const NOTE_UNRESOLVED = 'unresolved';
	const NOTE_SKIPPED    = 'skipped';

	/**
	 * Maximum notes kept per scan.
	 */
	const MAX_NOTES = 200;

	/**
	 * Settings snapshot taken when the scan started.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Notes collected during the current step.
	 *
	 * @var array[]
	 */
	public $notes = array();

	/**
	 * Number of items skipped during the current step.
	 *
	 * @var int
	 */
	public $skipped = 0;

	/**
	 * Constructor.
	 *
	 * @param array $settings Settings snapshot.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether low-confidence "possible" references should be reported.
	 *
	 * @return bool
	 */
	public function possible_references() {
		return ! empty( $this->settings['possible_references'] );
	}

	/**
	 * Records a reference that could not be resolved to a form.
	 *
	 * @param Scan_Item $item    Location.
	 * @param string    $message What was found.
	 */
	public function unresolved( Scan_Item $item, $message ) {
		$this->add_note( self::NOTE_UNRESOLVED, $item, $message );
	}

	/**
	 * Records data that could not be inspected.
	 *
	 * @param Scan_Item $item    Location.
	 * @param string    $message Why it was skipped.
	 */
	public function skipped( Scan_Item $item, $message ) {
		++$this->skipped;
		$this->add_note( self::NOTE_SKIPPED, $item, $message );
	}

	/**
	 * Adds a note.
	 *
	 * @param string    $kind    Note kind.
	 * @param Scan_Item $item    Location.
	 * @param string    $message Message.
	 */
	private function add_note( $kind, Scan_Item $item, $message ) {
		if ( count( $this->notes ) >= self::MAX_NOTES ) {
			return;
		}
		$this->notes[] = array(
			'kind'    => $kind,
			'key'     => $item->key,
			'type'    => $item->type,
			'title'   => $item->title,
			'message' => $message,
		);
	}
}
