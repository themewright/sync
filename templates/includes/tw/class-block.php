<?php
/**
 * Block handler.
 */
class TW_Block {
	/**
	 * The registered blocks and their layout with fields.
	 *
	 * @var array
	 */
	private static $blocks = array();

	/**
	 * Gets the registered block ACF layouts for a block group.
	 *
	 * @param  TW_Block_Group  $block_group
	 * @return array
	 */
	public static function get( $block_group ) {
		$layouts = array();

		foreach ( $block_group['blocks'] as $block_name ) {
			if ( isset( static::$blocks[$block_name] ) ) {
				$layouts[] = array(
					'key'        => 'field_block_group_' . $block_group['id'] . '_' . $block_name,
					'name'       => $block_name,
					'label'      => static::$blocks[$block_name]['label'],
					'type'       => 'layout',
					'display'    => 'row',
					'sub_fields' => static::prefix_field_keys(
						static::$blocks[$block_name]['fields'],
						'field_block_group_' . $block_group['id'] . '_' . $block_name . '__'
					),
				);
			}
		}

		return $layouts;
	}

	/**
	 * Registers a new block and its layout with fields.
	 *
	 * @param  string  $label
	 * @param  string  $name
	 * @param  array   $fields
	 * @return void
	 */
	public static function register( $label, $name, $fields ) {
		static::$blocks[$name] = array(
			'label'  => $label,
			'name'   => $name,
			'fields' => $fields,
		);
	}

	/**
	 * Renders a block.
	 *
	 * @param  array  $data
	 * @return void
	 */
	public static function render( $data ) {
		global $postmeta, $_postmeta, $option;
		
		$name  = $data['acf_fc_layout'] ?? false;
		$field = (object) $data;

		if ( isset( static::$blocks[$name] ) ) {
			include get_template_directory() . '/views/blocks/' . str_replace( '_', '-', $name ) . '.php';
		}
	}

	/**
	 * Adds prefixes to the field keys.
	 *
	 * @param  array   $fields
	 * @param  string  $key_prefix
	 * @return array
	 */
	private static function prefix_field_keys( $fields, $key_prefix ) {
		foreach ( $fields as $i => $field ) {
			$fields[$i]['key'] = $key_prefix . substr( $field['key'], 6 );

			if ( isset( $field['conditional_logic'] ) ) {
				foreach ( $field['conditional_logic'] as $j => $group ) {
					foreach ( $group as $k => $condition ) {
						$fields[$i]['conditional_logic'][$j][$k]['field'] = $key_prefix . substr( $condition['field'], 6 );
					}
				}
			}

			if ( isset( $field['sub_fields'] ) ) {
				$fields[$i]['sub_fields'] = static::prefix_field_keys( $field['sub_fields'], $key_prefix );
			}

			if ( isset( $field['layouts'] ) ) {
				$fields[$i]['layouts'] = static::prefix_field_keys( $field['layouts'], $key_prefix );
			}
		}

		return $fields;
	}
}