<?php
/**
 * Adds a custom menu page in the WP admin.
 */
class TW_Menu_Page {
	/**
	 * The text to be displayed in the title tags of the page when the menu is selected.
	 *
	 * @var string
	 */
	private $page_title;

	/**
	 * The text to be used for the menu.
	 *
	 * @var string
	 */
	private $menu_title;

	/**
	 * The capability required for this menu to be displayed to the user.
	 *
	 * @var string
	 */
	private $capability;

	/**
	 * The slug name to refer to this menu by.
	 *
	 * Should be unique for this menu page and only include lowercase alphanumeric, dashes,
	 * and underscores characters to be compatible with sanitize_key().
	 *
	 * @var string
	 */
	private $menu_slug;

	/**
	 * The slug name for the parent menu (or the file name of a standard WordPress admin page).
	 *
	 * @var string
	 */
	private $parent_slug;

	/**
	 * The URL to the icon to be used for this menu.
	 *
	 * @var string
	 */
	private $icon_url;

	/**
	 * The position in the menu order this item should appear.
	 *
	 * @var int
	 */
	private $position;

	/**
	 * The indicator for using a stylesheet for this menu page.
	 *
	 * @var bool
	 */
	private $scss;

	/**
	 * The indicator for using JavaScript for this menu page.
	 *
	 * @var bool
	 */
	private $js;

	/**
	 * The resulting page's hook_suffix, or false if the user does not have the capability required.
	 *
	 * @var string|false
	 */
	private $hook_suffix;

	/**
	 * Class constructor.
	 *
	 * @param  array  $data
	 */
	public function __construct( $data ) {
		$this->page_title  = $data['page_title'];
		$this->menu_title  = $data['menu_title'];
		$this->capability  = $data['capability'];
		$this->menu_slug   = $data['menu_slug'];
		$this->parent_slug = $data['parent_slug'] ?? null;
		$this->icon_url    = $data['icon_url'] ?? '';
		$this->position    = $data['position'] ?? null;
		$this->scss        = $data['scss'];
		$this->js          = $data['js'];

		add_action( 'admin_menu', array( $this, 'on_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'on_admin_enqueue_scripts' ) );
	}

	/**
	 * Fires before the administration menu loads in the admin.
	 *
	 * @return void
	 */
	public function on_admin_menu() {
		if ( $this->parent_slug ) {
			$this->hook_suffix = add_submenu_page(
				$this->parent_slug,
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->menu_slug,
				array( $this, 'render' ),
				$this->position
			);
		} else {
			$this->hook_suffix = add_menu_page(
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->menu_slug,
				array( $this, 'render' ),
				$this->icon_url,
				$this->position
			);
		}
	}

	/**
	 * Enqueues styles and scripts for the admin page.
	 *
	 * @param  string  $hook_suffix
	 * @return void
	 */
	public function on_admin_enqueue_scripts( $hook_suffix ) {
		if ( $hook_suffix != $this->hook_suffix ) {
			return;
		}

		if ( $this->scss ) {
			wp_enqueue_style(
				$this->hook_suffix,
				get_template_directory_uri() . '/assets/css/' . $this->menu_slug . '.menu-page.css',
				array(),
				filemtime( get_template_directory() . '/assets/css/' . $this->menu_slug . '.menu-page.css' )
			);
		}

		if ( $this->js ) {
			wp_enqueue_script(
				$this->hook_suffix,
				get_template_directory_uri() . '/assets/js/dist/' . $this->menu_slug . '.menu-page.js',
				array(),
				filemtime( get_template_directory() . '/assets/js/dist/' . $this->menu_slug . '.menu-page.js' ),
				true
			);
		}
	}

	/**
	 * Outputs the content for the page.
	 *
	 * @return void
	 */
	public function render() {
		include get_template_directory() . '/views/menu-pages/' . $this->menu_slug . '.php';
	}
}