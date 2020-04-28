<?php
/**
 * Template part handler.
 */
class TW_Part {
	/**
	 * The registered template parts and their arguments.
	 *
	 * @var array
	 */
	private static $parts = array();

	/**
	 * Registers a new template part and its default arguments.
	 *
	 * @param  string  $name
	 * @param  array   $args
	 * @return void
	 */
	public static function register( $name, $args = array() ) {
		static::$parts[$name] = $args;
	}

	/**
	 * Renders a template part with custom arguments.
	 *
	 * @param  string  $name
	 * @param  array   $args
	 * @return void
	 */
	public static function render( $name, $args = array() ) {
		if ( isset( static::$parts[$name] ) ) {
			global $postmeta, $_postmeta, $option;

			$final_args = array();

			foreach ( static::$parts[$name] as $arg => $default ) {
				$final_args[$arg] = $args[$arg] ?? $default;
			}

			extract( $final_args );

			include get_template_directory() . '/views/parts/' . $name . '.php';
		}
	}
}