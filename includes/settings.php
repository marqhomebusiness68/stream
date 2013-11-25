<?php
/**
 * Settings class
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 */
class X_Stream_Settings {

	/**
	 * Settings key/identifier
	 */
	const KEY = 'wp_stream';

	const ADMIN_PAGE_SLUG   = 'wp_stream';
	const ADMIN_PARENT_PAGE = 'options-general.php';

	/**
	 * Plugin settings
	 * 
	 * @var array
	 */
	public static $options = array();

	/**
	 * Menu page screen id
	 * 
	 * @var string
	 */
	public static $screen_id;

	/**
	 * Public constructor
	 *
	 * @return \X_Stream_Settings
	 */
	public static function load() {

		// Parse field information gathering default values
		$defaults = self::get_defaults();

		// Get options
		self::$options = apply_filters(
			'wp_stream_options',
			wp_parse_args(
				(array) get_option( self::KEY, array() ),
				$defaults
			)
		);

		if ( is_admin() ) {
			// Register settings page
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

			// Register settings, and fields
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

			// Scripts and styles for admin page
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );

			// Plugin action links
			add_filter( 'plugin_action_links', array( __CLASS__, 'admin_plugin_action_links' ), 10, 2 );
		}
	}

	/**
	 * Return true if not in admin nor in sign in/up pages
	 * @return boolean
	 */
	public static function is_front_end() {
		return ! (
			is_admin()
			||
			in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) )
		);
	}

	/**
	 * @filter plugin_action_links
	 */
	public static function admin_plugin_action_links( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$admin_page_url  = admin_url( sprintf( '%s?page=%s', self::ADMIN_PARENT_PAGE, self::ADMIN_PAGE_SLUG ) );
			$admin_page_link = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'wp_stream' ) );
			array_push( $links, $admin_page_link );
		}
		return $links;
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 * @return void
	 */
	public static function register_menu() {
		global $menu;
		$cap = apply_filters( 'wp_stream_cap', 'manage_options' );
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		add_menu_page(
			__( 'Stream', 'wp_stream' ),
			__( 'Stream', 'wp_stream' ),
			$cap,
			null,
			null,
			null,
			3
			);
		// Modify wp_stream_list with the url of edit screen
		$menu[3][2] = 'edit.php?post_type=stream';

		self::$screen_id = add_submenu_page(
			'edit.php?post_type=stream',
			__( 'Settings', 'wp_stream' ),
			__( 'Settings', 'wp_stream' ),
			'manage_options',
			self::KEY,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts/styles for admin screen
	 * 
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook !== self::$screen_id ) {
			return;
		}
		wp_enqueue_script( 'wp_stream-admin', plugins_url( 'ui/admin.js' , dirname( __FILE__ ) ), array( 'jquery' ) );
		wp_enqueue_style( 'wp_stream-admin', plugins_url( 'ui/admin.js' , dirname( __FILE__ ) ), array() );
	}

	/**
	 * Render settings page
	 * 
	 * @return void
	 */
	public static function render_page() {
		?>
		<div class="wrap">
			<?php screen_icon( 'tools' ); ?>
			<h2><?php _e( 'Stream Options', 'wp_stream' ) ?></h2>

			<?php if ( isset( $message ) ) : ?>
			<div class="updated">
				<p><?php echo $message // xss okay ?></p>
			</div>
			<?php endif ?>

			<h2 class="nav-tab-wrapper">
				<a href="#tab-settings" class="nav-tab">
					<?php esc_html_e( 'Settings', 'wp_stream' ) ?>
				</a>
			</h2>
			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="options.php">
					<?php
					settings_fields( self::KEY );
					do_settings_sections( self::KEY );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Return settings fields
	 * 
	 * @return array Multidimensional array of fields
	 */
	public static function get_fields() {
		return array(
			'data' => array(
				'title'  => __( 'Data Settings', 'wp_stream' ),
				'fields' => array(
					array(
						'name'    => 'lifetime',
						'title'   => __( 'Data lifetime', 'wp_stream' ),
						'type'    => 'text',
						'desc'    => __( 'Maximum number of days to keep data. Use -1 to keep them forever.', 'wp_stream' ),
						'default' => '-1',
						),
					),
				),
			);
	}

	/**
	 * Iterate through registered fields and extract default values
	 * 
	 * @return array Default option values
	 */
	public static function get_defaults() {
		$fields   = self::get_fields();
		$defaults = array();
		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				$defaults[$section_name.'_'.$field['name']] = isset( $field['default'] )
					? $field['default']
					: null;
			}
		}
		return $defaults;
	}

	/**
	 * Registers settings fields and sections
	 * 
	 * @return void
	 */
	public static function register_settings() {

		$fields = self::get_fields();

		register_setting( self::KEY, self::KEY );

		foreach ( $fields as $section_name => $section ) {
			add_settings_section(
				$section_name,
				$section['title'],
				'__return_false',
				self::KEY
			);

			foreach ( $section['fields'] as $field_idx => $field ) {
				if ( ! isset( $field['type'] ) ) { // No field type associated, skip, no GUI
					continue;
				}
				add_settings_field(
					$field['name'],
					$field['title'],
					(
						isset( $field['callback'] )
						? $field['callback']
						: array( __CLASS__, 'output_field' )
						),
					self::KEY,
					$section_name,
					$field + array( 'section' => $section_name )
				);
			}
		}
	}

	/**
	 * Compile HTML needed for displaying the field
	 * 
	 * @param  array  $field  Field settings
	 * @return string         HTML to be displayed
	 */
	public static function render_field( $field ) {

		switch ( $field['type'] ) {
			case 'text':
				$output = sprintf(
					'<input type="text" name="%s[%s_%s]" size="50" value="%s" />',
					self::KEY,
					$field['section'],
					esc_attr( $field['name'] ),
					self::$options[$field['section'].'_'.$field['name']]
					);
				break;
			case 'checkbox':
				$output = sprintf(
					'<input type="checkbox" name="%s[%s_%s]" value="1" %s />',
					self::KEY,
					$field['section'],
					esc_attr( $field['name'] ),
					checked( self::$options[$field['section'].'_'.$field['name']], 1, false )
					);
				break;
		}

		if ( isset( $field['desc'] ) ) {
			$output .= sprintf(
				'<p class="description">%s</p>',
				$field[ 'desc' ]
			);
		}

		return $output;
	}

	/**
	 * Render Callback for post_types field
	 * 
	 * @param $args
	 * @return void
	 */
	public static function output_field( $field ) {
		$method = 'output_' . $field['name'];
		if ( method_exists( __CLASS__, $method ) ) {
			return call_user_func( array( __CLASS__, $method ), $field );
		}

		$output = self::render_field( $field );
		echo $output; // xss okay
	}

}