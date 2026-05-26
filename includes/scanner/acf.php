<?php
/**
 * ACF image and gallery scanner.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan only ACF image-like fields on pages.
 *
 * @param WP_Post $post    Page object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 */
function media_insight_scan_page_acf_image_fields( WP_Post $post, &$results, &$stats ) {
	if ( function_exists( 'get_field_objects' ) ) {
		$field_objects = get_field_objects( $post->ID );

		if ( empty( $field_objects ) || ! is_array( $field_objects ) ) {
			return;
		}

		foreach ( $field_objects as $field ) {
			media_insight_scan_acf_field_object( $field, $post, $results, $stats );
		}

		return;
	}

	if ( ! function_exists( 'get_fields' ) ) {
		return;
	}

	$fields = get_fields( $post->ID );

	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return;
	}

	foreach ( $fields as $field_name => $field_value ) {
		media_insight_scan_acf_image_value( $field_value, $post, $results, $stats, (string) $field_name, false );
	}
}

/**
 * Scan an ACF field object with field-type awareness.
 *
 * @param array   $field   ACF field object.
 * @param WP_Post $post    Page object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 * @param string  $parent_context Optional parent field context for nested structures.
 */
function media_insight_scan_acf_field_object( $field, WP_Post $post, &$results, &$stats, $parent_context = '' ) {
	if ( ! is_array( $field ) || ! array_key_exists( 'value', $field ) ) {
		return;
	}

	$field_type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
	$field_name = isset( $field['name'] ) ? (string) $field['name'] : 'acf_field';
	$field_label = isset( $field['label'] ) && '' !== (string) $field['label'] ? (string) $field['label'] : $field_name;
	$field_context = sprintf( '%s (%s)', $field_label, $field_type ? $field_type : 'acf' );
	$context = '' !== $parent_context ? $parent_context . ' > ' . $field_context : $field_context;

	if ( in_array( $field_type, array( 'image', 'gallery' ), true ) ) {
		media_insight_scan_acf_image_value( $field['value'], $post, $results, $stats, $context, true );
		return;
	}

	if ( in_array( $field_type, array( 'group', 'repeater', 'flexible_content', 'clone' ), true ) ) {
		media_insight_scan_acf_structured_field( $field, $post, $results, $stats, $context );
	}
}

/**
 * Scan structured ACF fields by using sub field definitions where possible.
 *
 * @param array   $field   ACF field object.
 * @param WP_Post $post    Page object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 * @param string  $context Context label.
 */
function media_insight_scan_acf_structured_field( $field, WP_Post $post, &$results, &$stats, $context ) {
	$value      = isset( $field['value'] ) ? $field['value'] : null;
	$sub_fields = isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array();

	if ( empty( $sub_fields ) && 'flexible_content' === sanitize_key( $field['type'] ?? '' ) && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
		media_insight_scan_acf_flexible_content_layouts( $field['layouts'], $value, $post, $results, $stats, $context );
		return;
	}

	if ( empty( $sub_fields ) ) {
		media_insight_scan_acf_image_value( $value, $post, $results, $stats, $context, false );
		return;
	}

	if ( ! is_array( $value ) ) {
		return;
	}

	if ( media_insight_is_assoc_array( $value ) ) {
		media_insight_scan_acf_subfield_row( $sub_fields, $value, $post, $results, $stats, $context );
		return;
	}

	foreach ( $value as $index => $row ) {
		if ( is_array( $row ) ) {
			$row_context = $context . ' > row ' . ( absint( $index ) + 1 );
			media_insight_scan_acf_subfield_row( $sub_fields, $row, $post, $results, $stats, $row_context );
		}
	}
}

/**
 * Scan ACF flexible content rows against their layout definitions.
 *
 * @param array   $layouts Layout definitions.
 * @param mixed   $value   Flexible content value.
 * @param WP_Post $post    Page object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 * @param string  $context Context label.
 */
function media_insight_scan_acf_flexible_content_layouts( $layouts, $value, WP_Post $post, &$results, &$stats, $context ) {
	if ( empty( $layouts ) || ! is_array( $layouts ) || empty( $value ) || ! is_array( $value ) ) {
		return;
	}

	$layout_map = array();

	foreach ( $layouts as $layout ) {
		if ( ! is_array( $layout ) || empty( $layout['name'] ) || empty( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
			continue;
		}

		$layout_map[ (string) $layout['name'] ] = $layout;
	}

	foreach ( $value as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$layout_name = isset( $row['acf_fc_layout'] ) ? (string) $row['acf_fc_layout'] : '';
		$layout      = isset( $layout_map[ $layout_name ] ) ? $layout_map[ $layout_name ] : null;

		if ( ! is_array( $layout ) ) {
			continue;
		}

		$layout_label = isset( $layout['label'] ) && '' !== (string) $layout['label'] ? (string) $layout['label'] : $layout_name;
		$row_context  = $context . ' > ' . $layout_label . ' row ' . ( absint( $index ) + 1 );

		media_insight_scan_acf_subfield_row( $layout['sub_fields'], $row, $post, $results, $stats, $row_context );
	}
}

/**
 * Scan a structured ACF row against sub field definitions.
 *
 * @param array   $sub_fields Sub field definitions.
 * @param array   $row        Row value.
 * @param WP_Post $post       Page object.
 * @param array   $results    Results array.
 * @param array   $stats      Stats array.
 * @param string  $context    Context label.
 */
function media_insight_scan_acf_subfield_row( $sub_fields, $row, WP_Post $post, &$results, &$stats, $context ) {
	foreach ( $sub_fields as $sub_field ) {
		if ( ! is_array( $sub_field ) ) {
			continue;
		}

		$name = isset( $sub_field['name'] ) ? (string) $sub_field['name'] : '';
		$key  = isset( $sub_field['key'] ) ? (string) $sub_field['key'] : '';

		if ( '' === $name && '' === $key ) {
			continue;
		}

		$value = null;
		if ( '' !== $name && array_key_exists( $name, $row ) ) {
			$value = $row[ $name ];
		} elseif ( '' !== $key && array_key_exists( $key, $row ) ) {
			$value = $row[ $key ];
		} else {
			continue;
		}

		$sub_field['value'] = $value;
		$label              = isset( $sub_field['label'] ) && '' !== (string) $sub_field['label'] ? (string) $sub_field['label'] : $name;
		$sub_context        = $context . ' > ' . $label;

		if ( in_array( sanitize_key( $sub_field['type'] ?? '' ), array( 'image', 'gallery' ), true ) ) {
			media_insight_scan_acf_image_value( $value, $post, $results, $stats, $sub_context, true );
		} else {
			media_insight_scan_acf_field_object( $sub_field, $post, $results, $stats, $sub_context );
		}
	}
}

/**
 * Scan image-like ACF values.
 *
 * @param mixed   $value         ACF value.
 * @param WP_Post $post          Page object.
 * @param array   $results       Results array.
 * @param array   $stats         Stats array.
 * @param string  $context       Context label.
 * @param bool    $allow_numeric Whether plain numeric values are allowed as attachment IDs.
 */
function media_insight_scan_acf_image_value( $value, WP_Post $post, &$results, &$stats, $context, $allow_numeric ) {
	if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
		if ( $allow_numeric ) {
			$attachment_id = absint( $value );

			if ( $attachment_id && media_insight_attachment_is_supported_image( $attachment_id ) ) {
				if ( media_insight_add_attachment_usage( $results, $attachment_id, $post, 'acf_page_image', $context ) ) {
					$stats['acf_page_usages']++;
				}
			}
		}
		return;
	}

	if ( is_string( $value ) ) {
		if ( media_insight_is_image_url( $value ) && media_insight_add_image_usage( $results, $value, $post, 'acf_page_image', $context, 0 ) ) {
			$stats['acf_page_usages']++;
		}
		return;
	}

	if ( ! is_array( $value ) ) {
		return;
	}

	if ( media_insight_array_looks_like_acf_image( $value ) ) {
		$attachment_id = media_insight_get_attachment_id_from_acf_array( $value );

		if ( $attachment_id ) {
			if ( media_insight_add_attachment_usage( $results, $attachment_id, $post, 'acf_page_image', $context ) ) {
				$stats['acf_page_usages']++;
			}
			return;
		}

		foreach ( array( 'url', 'src' ) as $url_key ) {
			if ( ! empty( $value[ $url_key ] ) && is_string( $value[ $url_key ] ) && media_insight_is_image_url( $value[ $url_key ] ) ) {
				if ( media_insight_add_image_usage( $results, $value[ $url_key ], $post, 'acf_page_image', $context, 0 ) ) {
					$stats['acf_page_usages']++;
				}
				return;
			}
		}
	}

	$allow_nested_numeric_ids = $allow_numeric && ! media_insight_is_assoc_array( $value );

	foreach ( $value as $nested_value ) {
		media_insight_scan_acf_image_value( $nested_value, $post, $results, $stats, $context, $allow_nested_numeric_ids );
	}
}

