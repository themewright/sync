<?php
/**
 * Gets the modification time of a theme file.
 *
 * @param  string  $path
 * @return int
 */
function tw_filemtime( $path ) {
	$path = get_template_directory() . '/' . ltrim( $path, '/' );

	if ( file_exists( $path ) ) {
		return filemtime( $path );
	}

	return null;
}

/**
 * Converts an associative array to an element attributes string.
 *
 * @param  array  $atts
 * @return string
 */
function tw_element_attributes( $atts ) {
	$html = array();

	foreach ( $atts as $key => $value ) {
		$html[] = $key . '="' . $value . '"';
	}

	return implode( ' ', $html );
}