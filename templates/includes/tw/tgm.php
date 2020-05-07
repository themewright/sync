<?php
/**
 * Registers the required plugins for this theme.
 *
 * @return void
 */
function tw_register_required_plugins() {
	$plugins = array(
		array(
			'name'         => 'Advanced Custom Fields PRO',
			'slug'         => 'advanced-custom-fields',
			'source'       => 'https://github.com/themewright/advanced-custom-fields-pro/archive/master.zip',
			'external_url' => 'https://www.advancedcustomfields.com/pro/',
			'required'     => false,
		),
		array(
			'name'     => 'Classic Editor',
			'slug'     => 'classic-editor',
			'required' => false,
		),
	);

	$config = array(
		'id'           => 'tgmpa',
		'default_path' => '',
		'menu'         => 'tgmpa-install-plugins',
		'parent_slug'  => 'themes.php',
		'capability'   => 'edit_theme_options',
		'has_notices'  => true,
		'dismissable'  => true,
		'dismiss_msg'  => '',
		'is_automatic' => true,
		'message'      => '',
	);

	tgmpa( $plugins, $config );
}

add_action( 'tgmpa_register', 'tw_register_required_plugins' );