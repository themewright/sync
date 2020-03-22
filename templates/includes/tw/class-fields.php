<?php
/**
 * Getter and setter helper class for ACF meta fields.
 */
class TW_Fields {
	/**
	 * The post ID where the value is saved.
	 *
	 * Examples:
	 *  $post_id = 'post';       // Original post
	 *  $post_id = false;        // Current post
	 *  $post_id = 1;            // Post ID = 1
	 *  $post_id = 'user_2';     // User ID = 2
	 *  $post_id = 'category_3'; // Category term ID = 3
	 *  $post_id = 'event_4';    // Event (custom taxonomy) term ID = 4
	 *  $post_id = 'option';     // Options page
	 *  $post_id = 'options';    // Same as above
	 *
	 * @var mixed
	 */
	private $post_id;

	/**
	 * Class constructor.
	 *
	 * @param  mixed  $post_id
	 */
	public function __construct( $post_id = false ) {
		if ( $post_id == 'post' ) {
			$this->post_id = false;

			add_action( 'wp', array( $this, 'register_original_post_id' ) );
		} else {
			$this->post_id = $post_id;
		}
	}

	/**
	 * Gets a post meta value.
	 *
	 * @param  string  $selector
	 * @return mixed
	 */
	public function __get( string $selector ) {
		if ( function_exists( 'get_field' ) ) {
			return get_field( $selector, $this->post_id );
		}

		return null;
	}

	/**
	 * Sets a post meta value.
	 *
	 * @param  string  $selector
	 * @param  mixed  $value
	 * @return boolean
	 */
	public function __set( string $selector, $value ) {
		if ( function_exists( 'update_field' ) ) {
			return update_field( $selector, $value, $this->post_id );
		}

		return false;
	}

	/**
	 * Sets the post ID from the original post.
	 *
	 * @return void
	 */
	public function register_original_post_id() {
		$this->post_id = get_the_ID();
	}
}