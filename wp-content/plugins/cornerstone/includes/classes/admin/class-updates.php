<?php

class Cornerstone_Updates extends Cornerstone_Plugin_Component {

	protected $plugin_file =  'cornerstone/cornerstone.php';

	public function setup() {

		if ( ! is_admin() ) return;

		add_filter( 'themeco_update_api', array( $this, 'register' ), -100 );
    add_filter( 'themeco_update_cache', array( $this, 'cache_updates' ), 10, 2 );

		add_action( 'plugins_api', array( $this, 'plugins_api' ), 100, 3 );

		if ( empty( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'do-core-reinstall', 'do-core-upgrade' ), true ) ) {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_site_transient_update_plugins' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'add_script_data' ), -100 );

	}

	public function add_script_data() {
		$this->plugin->component( 'Admin' )->add_script_data( 'cs-updates', array( $this, 'script_data' ) );
	}

  public function ajax_update_check() {

    delete_site_transient( 'update_plugins' );
    cs_tco()->updates()->refresh();
    $errors = cs_tco()->updates()->get_errors();

    if ( empty( $errors ) ) {
      cs_send_json_success( array(
        'latest' => esc_html( $this->get_latest_version() )
      ) );
    }

    cs_send_json_error( array( 'errors' => $errors ) );

  }

  public function script_data() {
    return array(
      'complete'    => __( 'Nothing to report.', 'cornerstone' ),
      'completeNew' => __( 'New version available!', 'cornerstone' ),
      'error'       => __( 'Unable to check for updates. Try again later.', 'cornerstone' ),
      'checking'    => __( 'Checking&hellip;', 'cornerstone' ),
      'latest'      => esc_html( $this->get_latest_version() )
    );
  }

	public function get_plugin_data( $use_local_defaults = true ) {

		$data = cs_tco()->updates()->get_update_cache();

		$cornerstone = ( isset( $data['plugins'] ) && isset( $data['plugins'][ $this->plugin_file ] ) ) ? $data['plugins'][ $this->plugin_file ] : array();
		$defaults = array();

		if ( $use_local_defaults ) {
			$defaults = array(
				'slug' => 'cornerstone',
				'name' => $this->plugin->common()->properTitle(),
				'new_version' => CS()->version(),
				'author' => '<a href="http://theme.co/cornerstone/">Themeco</a>'
			);
		}

		return wp_parse_args( $cornerstone, $defaults );

	}

	public function get_latest_version() {

		$data = $this->get_plugin_data();
		return $data['new_version'];

  }

	public function plugins_api( $res = false, $action, $args ) {

		if ( ! isset( $args->slug ) || 'cornerstone' !== $args->slug ) {
			return $res;
		}

		$data = $this->get_plugin_data();

		$result = array(
			'slug'    => $data['slug'],
			'name'    => $data['name'],
			'author'  => $data['author'],
			'version' => $data['new_version'],
			'sections' => array(
				'changelog' => __( 'Visit the <a href="http://theme.co/changelog/#cornerstone">Themeco Changelog</a> for more information.' )
			)
		);

		if ( 'query_plugins' === $action || 'plugin_information' === $action ) {
			$result = (object) $result;
		}

		return $result;

	}

	public function pre_set_site_transient_update_plugins( $data ) {

		//
		// Only run after other plugins are checked.
		//

		if ( empty( $data->checked ) ) {
			return $data;
		}

		cs_tco()->updates()->refresh();
		$remote = $this->get_plugin_data();

		if ( empty( $remote ) ) {
			return $data;
		}

		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$installed_plugins = get_plugins();

		if ( ! isset( $installed_plugins[ $this->plugin_file ] ) ) {
			return $data;
		}

		$local = $installed_plugins[ $this->plugin_file ];

		// Version check
		if ( version_compare( $remote['new_version'], $local['Version'], '>' ) ) {

			if ( ! $remote['package'] ) {
				$remote['upgrade_notice'] = X_Addons_Updates::get_validation_html_plugin_updates();
			}

			$data->response[ $this->plugin_file ] = (object) $remote;

		}

		return $data;

	}

	public function register( $args ) {

		$args['api-key'] = esc_attr( get_option( 'cs_product_validation_key', '' ) );
		$args['cs-version'] = CS()->version();
		$args['php-version'] = PHP_VERSION;

		return $args;
  }

  public function cache_updates( $updates, $data ) {

		if ( !isset( $updates['plugins'] ) ) {
			$updates['plugins'] = array();
		}

		$plugin_updates = array();

		if ( isset( $data['plugins'] ) && isset( $data['plugins']['cornerstone'] ) ) {
			$plugin = $data['plugins']['cornerstone'];
			$plugin_updates[$plugin['plugin']] = $plugin;
		}

		$updates['plugins'] = array_merge( $updates['plugins'], $plugin_updates );

		return $updates;

  }

}
