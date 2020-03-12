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