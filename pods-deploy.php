<?php
/*
Plugin Name: Pods Deploy
Plugin URI: http://pods.io/
Description: Automated Pods config deploy via the WordPress REST API.
Version: 0.1.0
Author: Pods Framework Team
Author URI: http://pods.io/about/
Text Domain: pods-deploy
Domain Path: /languages/

Copyright 2014  Pods Foundation, Inc  (email : contact@podsfoundation.org)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PODS_DEPLOY_VERSION', '0.1.0' );
define( 'PODS_DEPLOY_DIR', plugin_dir_path( __FILE__ ) );

function pods_deploy_dependencies() {
	return array(
		'Pods' => 'PODS_VERSION',
		'Pods JSON API' => 'PODS_JSON_API_VERSION',
		'WordPress REST API' => 'JSON_API_VERSION',
	);
}

function pods_deploy_load_plugin() {
	$fail = false;
	foreach( pods_deploy_dependencies() as $dependency => $constant) {
		if ( ! defined( $constant ) ){
			$fail[] = __( sprintf( '<p>Pods Deploy requires %1s which is not activate.</p>', $dependency ), 'pods-deploy' );
		}

	}

	if ( is_array( $fail ) ) {
		if ( ! is_admin() ) {
			echo sprintf( '<div id="message" class="error"><p>%s</p></div>',
				implode( $fail )
			);
		}
	}

	else {
		include_once( PODS_DEPLOY_DIR . 'class-pods-depoly.php' );

	}

}
