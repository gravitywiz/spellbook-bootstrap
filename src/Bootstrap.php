<?php
/**
 * Bootstrap class for Gravity Wiz Plugins
 */


namespace Spellbook;


/**
 * Class Bootstrap
 *
 * Handles bootstrapping the plugin and displaying dependency warnings.
 */
class Bootstrap {

	/**
	 * Path to the root plugin file.
	 *
	 * @var string|null
	 */
	public $_root_file = null;

	/**
	 * In-memory map of all Bootstrap instances, keyed by plugin slug.
	 *
	 * @var array<string, Bootstrap>
	 */
	private static $instances = [];

	/**
	 * Constructor.
	 *
	 * @param string $load_file The file to load.
	 * @param string $root_file The root plugin file.
	 */
	public function __construct( $root_file ) {
		$this->_root_file = $root_file;

		// Pass false for markup and translations as this runs too early for translations to be loaded.
		$plugin_data              = get_plugin_data( $this->_root_file, false, false );
		$slug                     = sanitize_title( $plugin_data['Name'] );
		self::$instances[ $slug ] = $this;

		add_action( 'after_plugin_row_' . plugin_basename( $this->_root_file ), [ $this, 'display_dependency_warning_after_plugin_row' ], 10, 2 );

		add_action( 'admin_notices', [ $this, 'maybe_show_spellbook_notice' ] );

		add_action( 'network_admin_notices', [ $this, 'maybe_show_spellbook_notice' ] );
	}

	/**
	 * Returns all registered Bootstrap instances, keyed by plugin slug.
	 *
	 * @return array<string, Bootstrap>
	 */
	public static function get_instances() {
		return self::$instances;
	}

	/**
	 * Displays a dependency warning after the plugin row in the plugins list.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 *
	 * @return void
	 */
	public function display_dependency_warning_after_plugin_row( $plugin_file, $plugin_data ) {

		if ( $this->is_spellbook_installed() && self::is_spellbook_active() ) {
			return;
		}

		if ( self::is_plugin_active( $plugin_file ) ) :
			?>

				<style type="text/css" scoped>
					<?php printf( '#%1$s td, #%1$s th', sanitize_title( $plugin_data['Name'] ) ); ?>
					,
					<?php printf( 'tr[data-slug="%1$s"] td, tr[data-slug="%1$s"] th', sanitize_title( $plugin_data['Name'] ) ); ?>
					{
						border-bottom: 0;
						box-shadow: none !important;
						-webkit-box-shadow: none !important;
					}

					.gwp-plugin-notice td {
						padding: 0 !important;
					}

					.gwp-plugin-notice .update-message p:before {
						content: '\f534';
						font-size: 18px;
					}
				</style>

				<tr class="plugin-update-tr active gwp-plugin-notice">
					<td colspan="3" class="colspanchange">
						<div class="update-message notice inline notice-error notice-alt">
							<p>
								<?php
								printf(
									// translators: Placeholders are opening and closing anchor tags to link to the Gravity Wiz website.
									esc_html__( 'This plugin requires Spellbook. Activate it now or %1$sinstall it today!%2$s', 'spellbook' ),
									'<a href="https://gravitywiz.com/documentation/spellbook/#installation-instructions">', '</a>'
								);
								?>
							</p>
						</div>
					</td>
				</tr>

			<?php
		endif;
	}

	/**
	 * Checks whether a plugin is active, considering network context.
	 *
	 * @param string $plugin_file Path to the plugin file.
	 *
	 * @return bool Whether the plugin is active.
	 */
	private static function is_plugin_active( $plugin_file ) {
		if ( is_network_admin() ) {
			return is_plugin_active_for_network( plugin_basename( $plugin_file ) );
		}

		return is_plugin_active( plugin_basename( $plugin_file ) );
	}

	/**
	 * Checks whether the Spellbook plugin is active.
	 *
	 * @return bool Whether Spellbook is active.
	 */
	private static function is_spellbook_active() {
		return self::is_plugin_active( WP_PLUGIN_DIR . '/spellbook/spellbook.php' );
	}

	/**
	 * Checks whether the Spellbook plugin is installed.
	 *
	 * @return bool Whether Spellbook is installed.
	 */
	private static function is_spellbook_installed() {
		return file_exists( WP_PLUGIN_DIR . '/spellbook/spellbook.php' );
	}

	/**
	 * Determines whether to show the Spellbook notice on the given screen.
	 *
	 * @param \WP_Screen|null $screen The current admin screen object.
	 *
	 * @return string|null The notice type ('warning' or 'error'), or null if no notice should be shown.
	 */
	private function should_show_spellbook_notice_on_screen( $screen ) {
		if ( ! $screen ) {
			return null;
		}

		$allowed_bases = [ 'dashboard', 'update-core', 'plugins', 'plugin-install' ];
		if ( in_array( $screen->base, $allowed_bases, true ) ) {
			return 'warning';
		}

		$screen_id = (string) $screen->id;
		if ( strpos( $screen_id, 'gf_edit_forms' ) !== false || strpos( $screen_id, 'gf_settings' ) !== false ) {
			return 'error';
		}

		return null;
	}

	/**
	 * Displays an admin notice if Spellbook is not installed or active.
	 *
	 * @return void
	 */
	public function maybe_show_spellbook_notice() {
		if ( ! is_admin() ) {
			return;
		}

		if ( $this->is_spellbook_installed() && self::is_spellbook_active() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$notice_result = $this->should_show_spellbook_notice_on_screen( $screen );
		if ( ! $notice_result ) {
			return;
		}

		$name = get_plugin_data( $this->_root_file )['Name'];

		$message = sprintf(
			'%s requires Spellbook for updates. You can download Spellbook <a href="%s" target="_blank" rel="noopener noreferrer">here</a> (it&#039;s free!).',
			esc_html( $name ),
			esc_url( 'https://gravitywiz.com/documentation/spellbook/#installation-instructions' )
		);

		$classes = $notice_result === 'warning' ? 'notice notice-warning' : 'notice notice-error gf-notice';
		printf(
			'<div class="%s"><p>%s</p></div>',
			esc_attr( $classes ),
			wp_kses_post( $message )
		);
	}
}

