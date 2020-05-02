<?php
/**
 * Block group handler.
 */
class TW_Block_Group {
	/**
	 * The registered block groups.
	 *
	 * @var array
	 */
	private static $block_groups = array();

	/**
	 * The block groups locations.
	 *
	 * @var array
	 */
	private static $locations = array();

	/**
	 * Registers a new block group.
	 *
	 * @param  array  $args
	 * @return void
	 */
	public static function register( $args ) {
		if ( ! static::$block_groups ) {
			add_action( 'acf/init', 'TW_Block_Group::add_fields' );
		}

		static::$block_groups[$args['name']] = $args;
	}

	/**
	 * Adds a new block group location.
	 *
	 * @param  string  $name
	 * @param  array   $rule
	 * @return void
	 */
	public static function add_location( $name, $rule ) {
		if ( ! isset( static::$locations[$name] ) ) {
			static::$locations[$name] = array();
		}

		static::$locations[$name][] = array( $rule );
	}

	/**
	 * Adds a field group to the local ACF cache.
	 *
	 * @return void
	 */
	public static function add_fields() {
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			foreach ( static::$block_groups as $block_group ) {
				acf_add_local_field_group(
					array(
						'key'                  => 'group_block_group_' . $block_group['id'],
						'title'                => $block_group['label'],
						'fields'               => array(
							array(
								'key'          => 'field_block_group_' . $block_group['id'],
								'label'        => $block_group['label'],
								'name'         => $block_group['name'],
								'type'         => 'flexible_content',
								'layouts'      => TW_Block::get( $block_group ),
								'button_label' => $block_group['button_label'],
							),
						),
						'location'              => static::$locations[$block_group['name']] ?? array(),
						'menu_order'            => $block_group['menu_order'],
						'position'              => 'normal',
						'style'                 => 'seamless',
						'label_placement'       => 'top',
						'instruction_placement' => 'label',
						'hide_on_screen'        => '',
						'active'                => true,
						'description'           => '',
					)
				);
			}
		}
	}

	/**
	 * Renders a block group.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public static function render( $name ) {
		if ( function_exists( 'get_field' ) ) {
			$field = get_field( $name );

			if ( $field && is_array( $field ) && isset( static::$block_groups[$name] ) ) {
				foreach ( $field as $data ) {
					TW_Block::render( $data );
				}
			}
		}
	}
}