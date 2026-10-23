<?php
/**
 * Fixture template used by the tests. Never executed by the scanner.
 *
 * @package Fixture
 */

// gravity_form( 4 ); is a comment and must be ignored.
gravity_form( 1, false, false );
gravity_form( $form_id );
echo do_shortcode( '[gravityform id="2" ajax="true"]' );
echo GFForms::get_form( 3 );
$obj->gravity_form( 5 );

/**
 * Declaring a function with a similar name is not a call.
 */
function gravity_form_helper() {}
?>
<aside>[gravityform id="2"]</aside>
