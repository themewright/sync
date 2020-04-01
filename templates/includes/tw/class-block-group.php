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
	 * @param  int     $id
	 * @param  string  $label
	 * @param  string  $name
	 * @param  array   $blocks
	 * @return void
	 */
	public static function register( $id, $label, $name, $blocks ) {
		if ( ! static::$block_groups ) {
			add_action( 'acf/init', 'TW_Block_Group::add_fields' );
		}

		static::$block_groups[$name] = array(
			'id'     => $id,
			'label'  => $label,
			'name'   => $name,
			'blocks' => $blocks,
		);
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
		foreach ( static::$block_groups as $block_group ) {
			acf_add_local_field_group(
				array(
					'key'      => 'group_block_group_' . $block_group['id'],
					'title'    => $block_group['label'],
					'fields'   => array(
						array(
							'key'          => 'field_block_group_' . $block_group['id'],
							'label'        => $block_group['label'],
							'name'         => $block_group['name'],
							'type'         => 'flexible_content',
							'layouts'      => TW_Block::get( $block_group ),
							'button_label' => 'Add Block', // @todo make generic $block_group['button_label']
						),
					),
					'location' => static::$locations[$block_group['name']] ?? array(),
					'style'    => 'seamless',
				)
			);
		}
	}

	/**
	 * Renders a block group.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public static function render( $name ) {
		$field = get_field( $name );

		if ( $field && is_array( $field ) && isset( static::$block_groups[$name] ) ) {
			foreach ( $field as $data ) {
				TW_Block::render( $data );
			}
		}
	}
}