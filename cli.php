<?php

/**
 * Manages the WP Super Cache plugin
 */
class WPSuperCache_Command extends WP_CLI_Command {

	/**
	 * Clear something from the cache.
	 *
	 * @synopsis [--post_id=<post-id>] [--permalink=<permalink>]
	 */
	function flush( $args = array(), $assoc_args = array() ) {
		if ( isset($assoc_args['post_id']) ) {
			if ( is_numeric( $assoc_args['post_id'] ) ) {
				wp_cache_post_change( $assoc_args['post_id'] );
			} else {
				WP_CLI::error('This is not a valid post id.');
			}

			wp_cache_post_change( $assoc_args['post_id'] );
		}
		elseif ( isset( $assoc_args['permalink'] ) ) {
			$id = url_to_postid( $assoc_args['permalink'] );

			if ( is_numeric( $id ) ) {
				wp_cache_post_change( $id );
			} else {
				WP_CLI::error('There is no post with this permalink.');
			}
		} else {
			global $file_prefix;

			wp_cache_clean_cache( $file_prefix, true );

			WP_CLI::success( 'Cache cleared.' );
		}
	}

	/**
	 * Get the status of the cache.
	 */
	function status( $args = array(), $assoc_args = array() ) {
		$cache_stats = get_option( 'supercache_stats' );

		if ( !empty( $cache_stats ) ) {
			if ( $cache_stats['generated'] > time() - 3600 * 24 ) {
				global $super_cache_enabled;
				WP_CLI::line( 'Cache status: ' . ($super_cache_enabled ? '%gOn%n' : '%rOff%n') );
				WP_CLI::line( 'Cache content on ' . date('r', $cache_stats['generated'] ) . ': ' );
				WP_CLI::line();
				WP_CLI::line( '    WordPress cache:' );
				WP_CLI::line( '        Cached: ' . $cache_stats[ 'wpcache' ][ 'cached' ] );
				WP_CLI::line( '        Expired: ' . $cache_stats[ 'wpcache' ][ 'expired' ] );
				WP_CLI::line();
				WP_CLI::line( '    WP Super Cache:' );
				WP_CLI::line( '        Cached: ' . $cache_stats[ 'supercache' ][ 'cached' ] );
				WP_CLI::line( '        Expired: ' . $cache_stats[ 'supercache' ][ 'expired' ] );
			} else {
				WP_CLI::error('The WP Super Cache stats are too old to work with (older than 24 hours).');
			}
		} else {
			WP_CLI::error('No WP Super Cache stats found.');
		}
	}

	/**
	 * Enable the WP Super Cache.
	 */
	function enable( $args = array(), $assoc_args = array() ) {
		global $super_cache_enabled;

		wp_super_cache_enable();

		if($super_cache_enabled) {
			WP_CLI::success( 'The WP Super Cache is enabled.' );
		} else {
			WP_CLI::error('The WP Super Cache is not enabled, check its settings page for more info.');
		}
	}

	/**
	 * Disable the WP Super Cache.
	 */
	function disable( $args = array(), $assoc_args = array() ) {
		global $super_cache_enabled;

		wp_super_cache_disable();

		if(!$super_cache_enabled) {
			WP_CLI::success( 'The WP Super Cache is disabled.' );
		} else {
			WP_CLI::error('The WP Super Cache is still enabled, check its settings page for more info.');
		}
	}

	/**
	 * Primes the cache by creating static pages before users visit them
	 *
	 * @synopsis [--status] [--cancel]
	 */
	function preload( $args = array(), $assoc_args = array() ) {
		global $super_cache_enabled;
		$preload_counter = get_option( 'preload_cache_counter' );
		$preloading      = is_array( $preload_counter ) && $preload_counter['c'] > 0;
		$pending_cancel  = get_option( 'preload_cache_stop' );
			
		// Bail early if caching or preloading is disabled
		if( ! $super_cache_enabled ) {
			WP_CLI::error( 'The WP Super Cache is not enabled.' );
		}

		if ( defined( 'DISABLESUPERCACHEPRELOADING' ) && true == DISABLESUPERCACHEPRELOADING ) {
			WP_CLI::error( 'Cache preloading is not enabled.' );
		}

		// Display status
		if ( isset( $assoc_args['status'] ) ) {
			$this->preload_status( $preload_counter, $pending_cancel );
			exit();
		}

		// Cancel preloading if in progress
		if ( isset( $assoc_args['cancel'] ) ) {
			if ( $preloading ) {
				if ( $pending_cancel ) {
					WP_CLI::error( 'There is already a pending preload cancel. It may take up to a minute for it to cancel completely.' );
				} else {
					update_option( 'preload_cache_stop', true );
					WP_CLI::success( 'Scheduled preloading of cache almost cancelled. It may take up to a minute for it to cancel completely.' );
					exit();
				}
			} else {
				WP_CLI::error( 'Not currently preloading.' );
			}
		}
		 
		// Start preloading if not already in progress
		if ( $preloading ) {
			WP_CLI::warning( 'Cache preloading is already in progress.' );
			$this->preload_status( $preload_counter, $pending_cancel );
			exit();
		} else {
			wp_schedule_single_event( time(), 'wp_cache_full_preload_hook' );
			WP_CLI::success( 'Scheduled preload for next cron run.' );
		}
	}

	/**
	 * Outputs the status of preloading
	 *
	 * @param $preload_counter
	 * @param $pending_cancel
	 */
	protected function preload_status( $preload_counter, $pending_cancel ) {
		if ( is_array( $preload_counter ) && $preload_counter['c'] > 0 ) {
			WP_CLI::line( sprintf( 'Currently caching from post %d to %d.', $preload_counter[ 'c' ] - 100, $preload_counter[ 'c' ] ) );
			
			if ( $pending_cancel ) {
				WP_CLI::warning( 'Pending preload cancel. It may take up to a minute for it to cancel completely.' );
			}
		} else {
			WP_CLI::line( 'Not currently preloading.' );
		}
	}

	/**
	 * Install default config file
	 */
	function defaults ( $args = array(), $assoc_args = array() ) {
		global $wp_cache_shutdown_gc, $wp_cache_shutdown_gc, $cache_schedule_type, $wp_cache_config_file, $wp_cache_mobile_enabled;
		global $wp_cache_not_logged_in, $wp_cache_no_cache_for_get, $wp_cache_mod_rewrite, $cache_compression, $cache_compression;
		global $cache_rebuild_files, $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $mobile_groups;

		// Nasty workaround - plugin checks that the current user is an administrator
		$admins = get_users('role=administrator');
		wp_set_current_user($admins[0]->id);

		// Creates config file if it doesn't already exist.
		ob_start();
		wp_cache_manager_error_checks();
		ob_end_clean();

		// Set some sensible defaults
		if ( ( !isset( $wp_cache_shutdown_gc ) || $wp_cache_shutdown_gc == 0 ) && false == wp_next_scheduled( 'wp_cache_gc' ) ) {
		  if ( false == isset( $cache_schedule_type ) ) {
			$cache_schedule_type = 'interval';
			$cache_time_interval = 600;
			$cache_max_time = 1800;
			wp_cache_replace_line('^ *\$cache_schedule_type', "\$cache_schedule_type = '$cache_schedule_type';", $wp_cache_config_file);
			wp_cache_replace_line('^ *\$cache_time_interval', "\$cache_time_interval = '$cache_time_interval';", $wp_cache_config_file);
			wp_cache_replace_line('^ *\$cache_max_time', "\$cache_max_time = '$cache_max_time';", $wp_cache_config_file);
		  }
		  wp_schedule_single_event( time() + 600, 'wp_cache_gc' );
		}

		$wp_cache_mobile_enabled = 1;
		$wp_cache_not_logged_in = 1;
		$wp_cache_no_cache_for_get = 1;
		$wp_cache_mod_rewrite = 1;
		$cache_compression = 1;
		$cache_rebuild_files = 1;
		wp_cache_replace_line('^ *\$wp_cache_mobile_enabled', "\$wp_cache_mobile_enabled = 1;", $wp_cache_config_file);
		wp_cache_replace_line('^ *\$wp_cache_mod_rewrite', '$wp_cache_mod_rewrite = 1;', $wp_cache_config_file);
		wp_cache_replace_line('^ *\$cache_rebuild_files', "\$cache_rebuild_files = 1;", $wp_cache_config_file);
		wp_cache_replace_line('^ *\$wp_cache_not_logged_in', "\$wp_cache_not_logged_in = 1;", $wp_cache_config_file);
		wp_cache_replace_line('^ *\$wp_cache_no_cache_for_get', "\$wp_cache_no_cache_for_get = 1;", $wp_cache_config_file);
		if ( 1 == ini_get( 'zlib.output_compression' ) || "on" == strtolower( ini_get( 'zlib.output_compression' ) ) ) {
			//
		} else {
		  wp_cache_replace_line('^ *\$cache_compression', "\$cache_compression = 1;", $wp_cache_config_file);
		}
		$wp_cache_mobile_browsers = array( '2.0 MMP', '240x320', '400X240', 'AvantGo', 'BlackBerry', 'Blazer', 'Cellphone', 'Danger', 'DoCoMo', 'Elaine/3.0', 'EudoraWeb', 'Googlebot-Mobile', 'hiptop', 'IEMobile', 'KYOCERA/WX310K', 'LG/U990', 'MIDP-2.', 'MMEF20', 'MOT-V', 'NetFront', 'Newt', 'Nintendo Wii', 'Nitro', 'Nokia', 'Opera Mini', 'Palm', 'PlayStation Portable', 'portalmmm', 'Proxinet', 'ProxiNet', 'SHARP-TQ-GX10', 'SHG-i900', 'Small', 'SonyEricsson', 'Symbian OS', 'SymbianOS', 'TS21i-10', 'UP.Browser', 'UP.Link', 'webOS', 'Windows CE', 'WinWAP', 'YahooSeeker/M1A1-R2D2', 'iPhone', 'iPod', 'Android', 'BlackBerry9530', 'LG-TU915 Obigo', 'LGE VX', 'webOS', 'Nokia5800' );
		$wp_cache_mobile_prefixes = array( 'w3c ', 'w3c-', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac', 'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'htc_', 'inno', 'ipaq', 'ipod', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-', 'lg/u', 'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-', 'newt', 'noki', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox', 'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar', 'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-', 'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp', 'wapr', 'webc', 'winw', 'winw', 'xda ', 'xda-' ); // from http://svn.wp-plugins.org/wordpress-mobile-pack/trunk/plugins/wpmp_switcher/lite_detection.php
		$mobile_groups = apply_filters( 'cached_mobile_groups', array() );
		update_cached_mobile_ua_list( $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $mobile_groups );

		wp_cache_enable();
		wpsc_update_htaccess();
		extract( wpsc_get_htaccess_info() );
		// Need to work out how $cache_path is set here 
		$cache_path = WP_CONTENT_DIR . '/cache/';
		$gziprules = insert_with_markers( $cache_path . '.htaccess', 'supercache', explode( "\n", $gziprules ) );
	}

}

WP_CLI::add_command( 'super-cache', 'WPSuperCache_Command' );

