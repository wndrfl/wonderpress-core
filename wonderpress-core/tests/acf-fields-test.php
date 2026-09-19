<?php
/**
 * Smoke test for manifest property → ACF field compilation (Pass 1).
 *
 * Run from wonderpress-core: php wonderpress-core/tests/acf-fields-test.php
 */

define( 'ABSPATH', '/tmp' );

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong() {}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

require dirname( __DIR__ ) . '/inc/acf.php';

$prefix = 'field_wndr_link';

$properties = array(
	array(
		'name'    => 'type',
		'type'    => 'select',
		'label'   => 'Type',
		'choices' => array(
			'internal' => 'Internal',
			'url'      => 'Url',
		),
		'default' => 'internal',
		'acf'     => array( 'ui' => 1 ),
	),
	array(
		'name' => 'internal_target_obj',
		'type' => 'post_object',
		'when' => array(
			array(
				array(
					'field'    => 'type',
					'operator' => '==',
					'value'    => 'internal',
				),
			),
		),
		'acf'  => array(
			'post_type'      => array( 'page' ),
			'return_format'  => 'object',
		),
	),
	array(
		'name' => 'contact_email',
		'type' => 'email',
		'when' => array(
			array(
				array(
					'field'    => 'type',
					'operator' => '==',
					'value'    => 'url',
				),
			),
		),
	),
);

$fields = wonder_acf_fields_from_properties( $properties, $prefix );

assert( count( $fields ) === 3, 'expected three fields' );

$type_field = $fields[0];
assert( 'select' === $type_field['type'], 'type field is select' );
assert( 1 === $type_field['ui'], 'select ui passthrough' );
assert( 'internal' === $type_field['default_value'], 'select default' );

$internal_field = $fields[1];
assert( 'post_object' === $internal_field['type'], 'post_object mapped' );
assert( ! empty( $internal_field['conditional_logic'] ), 'conditional_logic present' );
assert(
	$prefix . '_type' === $internal_field['conditional_logic'][0][0]['field'],
	'conditional references sibling key'
);
assert( 'internal' === $internal_field['conditional_logic'][0][0]['value'], 'conditional value' );

$email_field = $fields[2];
assert( 'email' === $email_field['type'], 'email mapped' );

fwrite( STDOUT, "acf-fields-test.php OK\n" );
