<?php
/*
 Plugin Name: Debug Bar
 Plugin URI: http://wordpress.org/extend/plugins/debug-bar/
 Description: Adds a debug menu to the admin bar that shows query, cache, and other helpful debugging information.
 Author: wordpressdotorg
 Version: 0.8.2
 Author URI: http://wordpress.org/
 */

/***
 * Debug Functions
 *
 * When logged in as a super admin, these functions will run to provide
 * debugging information when specific super admin menu items are selected.
 *
 * They are not used when a regular user is logged in.
 */

class Debug_Bar {
	var $panels = array();

	function __construct() {
		add_action( 'init', array( $this, 'enqueue' ) );
		add_action( 'gp_footer', array( $this, 'init' ) );
	}

	function init() {
		add_action( 'gp_footer',                    array( $this, 'admin_bar_menu' ), 1000 );
		add_action( 'gp_footer',                    array( $this, 'render' ), 1000 );
		add_filter( 'body_class',                   array( $this, 'body_class' ) );

		$this->requirements();
		$this->init_panels();
	}

	function requirements() {
		$path = plugin_dir_path( __FILE__ );
		require_once( $path . '/compat.php' );

		$recs = array( 'panel', 'php', 'queries', 'request', 'object-cache', 'deprecated', 'js' );

		foreach ( $recs as $rec ) {
			require_once "$path/panels/class-debug-bar-$rec.php";
		}
	}

	function enqueue() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

		wp_enqueue_style( 'debug-bar', gp_url_base_root() .'plugins/debug-bar/css/debug-bar' . $suffix . '.css', array(), '20120317' );

		wp_enqueue_script( 'debug-bar', gp_url_base_root() .'plugins/debug-bar/js/debug-bar' . $suffix . '.js', array( 'jquery' ), '20121228.2', true );

		do_action('debug_bar_enqueue_scripts');
	}

	function init_panels() {
		$classes = array(
			'Debug_Bar_PHP',
			'Debug_Bar_Queries',
			'Debug_Bar_Deprecated',
			'Debug_Bar_Request',
			'Debug_Bar_Object_Cache',
			'Debug_Bar_JS',
		);

		foreach ( $classes as $class ) {
			$this->panels[] = new $class;
		}

		$this->panels = apply_filters( 'debug_bar_panels', $this->panels );
	}

	// memory_get_peak_usage is PHP >= 5.2.0 only
	function safe_memory_get_peak_usage() {
		if ( function_exists( 'memory_get_peak_usage' ) ) {
			$usage = memory_get_peak_usage();
		} else {
			$usage = memory_get_usage();
		}

		return $usage;
	}

	function admin_bar_menu() {
		global $wp_admin_bar;

		$classes = apply_filters( 'debug_bar_classes', array() );
		$classes = implode( " ", $classes );

		echo '<a id="wp-admin-bar-debug-bar" class="' . $classes . '">' . apply_filters( 'debug_bar_title', __('Debug', 'debug-bar') ) . '</a>';
	}

	function body_class( $classes ) {
		if ( is_array( $classes ) )
			$classes[] = 'debug-bar-maximized';
		else
			$classes .= ' debug-bar-maximized ';

		if ( isset( $_GET['debug-bar'] ) ) {
			if ( is_array( $classes ) )
				$classes[] = 'debug-bar-visible';
			else
				$classes .= ' debug-bar-visible ';
		}

		return $classes;
	}

	function render() {
		global $gpdb;

		if ( empty( $this->panels ) )
			return;

		foreach ( $this->panels as $panel_key => $panel ) {
			$panel->prerender();
			if ( ! $panel->is_visible() )
				unset( $this->panels[ $panel_key ] );
		}

		?>
	<div id='querylist'>

	<div id="debug-bar-actions">
		<span class="maximize">+</span>
		<span class="restore">&ndash;</span>
		<span class="close">&times;</span>
	</div>

	<div id='debug-bar-info'>
		<div id="debug-status">
			<?php //@todo: Add a links to information about GP_DEBUG, PHP version, MySQL version, and Peak Memory.
			$statuses   = array();
			$statuses[] = array( 'php', __('PHP', 'debug-bar'), phpversion() );
			$db_title   = empty( $gpdb->is_mysql ) ? __( 'DB', 'debug-bar' ) : 'MySQL';
			$statuses[] = array( 'db', $db_title, $gpdb->db_version() );
			$statuses[] = array( 'memory', __('Memory Usage', 'debug-bar'), sprintf( __('%s bytes', 'debug-bar'), number_format( $this->safe_memory_get_peak_usage() ) ) );

			if ( ! GP_DEBUG ) {
				$statuses[] = array( 'warning', __('Please Enable', 'debug-bar'), 'GP_DEBUG' );
			}

			$statuses = apply_filters( 'debug_bar_statuses', $statuses );

			foreach ( $statuses as $status ):
				list( $slug, $title, $data ) = $status;

				?><div id='debug-status-<?php echo esc_attr( $slug ); ?>' class='debug-status'>
					<div class='debug-status-title'><?php echo $title; ?></div>
					<?php if ( ! empty( $data ) ): ?>
						<div class='debug-status-data'><?php echo $data; ?></div>
					<?php endif; ?>
				</div><?php
			endforeach;
			?>
		</div>
	</div>

	<div id='debug-bar-menu'>
		<ul id="debug-menu-links">

	<?php
		$current = ' current';
		foreach ( $this->panels as $panel ) :
			$class = get_class( $panel );
			?>
			<li><a
				id="debug-menu-link-<?php echo esc_attr( $class ); ?>"
				class="debug-menu-link<?php echo $current; ?>"
				href="#debug-menu-target-<?php echo esc_attr( $class ); ?>">
				<?php
				// Not escaping html here, so panels can use html in the title.
				echo $panel->title();
				?>
			</a></li>
			<?php
			$current = '';
		endforeach; ?>

		</ul>
	</div>

	<div id="debug-menu-targets"><?php
	$current = ' style="display: block"';
	foreach ( $this->panels as $panel ) :
		$class = get_class( $panel ); ?>

		<div id="debug-menu-target-<?php echo $class; ?>" class="debug-menu-target" <?php echo $current; ?>>
			<?php $panel->render(); ?>
		</div>

		<?php
		$current = '';
	endforeach;
	?>
	</div>

	<?php do_action( 'debug_bar' ); ?>
	</div>
	<?php
	}

}

$GLOBALS['debug_bar'] = new Debug_Bar();
