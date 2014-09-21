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
define( 'PODS_DEPLOY_MIN_JSON_API_VERSION', '0.2' );
define( 'PODS_DEPLOY_MIN_PODS_VERSION', '2.4.3' );


global $pods_deploy_menu_page;

/**
 *
 */
add_filter( 'pods_admin_menu', 'pods_deploy_tools_menu' );
function pods_deploy_tools_menu ( $admin_menus ) {

	$admin_menus[ 'pods-deploy'] = array(
		'label' => __( 'Pods Deploy', 'pods-deploy' ),
		'function' => 'pods_deploy_handler',
		'access' => 'manage_options'

	);

	return $admin_menus;

}

/**
 *
 */
function pods_deploy_handler () {

	if ( pods_v_sanitized( 'pods-deploy-submit', 'post') ) {

		$remote_url = pods_v_sanitized( 'remote-url', 'post', false, true );
		$private_key = pods_v_sanitized( 'private-key', 'post' );
		$public_key = pods_v_sanitized( 'public-key', 'post' );
		if ( $remote_url && $private_key && $public_key ) {
			Pods_Deploy_Auth::save_local_keys( $private_key, $public_key );

			pods_deploy( $remote_url, $private_key, $public_key );
		}
		else{
			pods_error( var_dump( array($remote_url, $private_key, $public_key ) ) );
		}
	}
	elseif( pods_v_sanitized( 'pods-deploy-key-gen-submit', 'post' ) ) {
		$activate = pods_v_sanitized( 'allow-deploy', 'post' );
		if ( $activate ) {
			Pods_Deploy_Auth::allow_deploy();
			Pods_Deploy_Auth::generate_keys();
			include 'ui/main.php';
		}
		else {
			Pods_Deploy_Auth::revoke_keys();
		}

		include_once( 'ui/main.php' );
	}
	else {
		include_once( 'ui/main.php' );
	}
}

/**
 * An array of dependencies to check for before loading class.
 *
 * @since 0.1.0
 *
 * @return array
 */
function pods_deploy_dependencies() {
	return array(
		'Pods' => 'PODS_VERSION',
		'Pods JSON API' => 'PODS_JSON_API_VERSION',
		'WordPress REST API' => 'JSON_API_VERSION',
	);
}

/**
 * Check for dependencies and load main class if decencies are present
 *
 * @since 0.1.0
 */
add_action( 'plugins_loaded', 'pods_deploy_load_plugin' );
function pods_deploy_load_plugin() {
	$fail = false;
	foreach( pods_deploy_dependencies() as $dependency => $constant) {
		if ( ! defined( $constant ) ){
			$fail[] = __( sprintf( '<p>Pods Deploy requires %1s which is not activate.</p>', $dependency ), 'pods-deploy' );
		}

	}

	if ( ! $fail && 1==9 ) {
		if ( ! version_compare( PODS_JSON_API_VERSION, PODS_DEPLOY_MIN_JSON_API_VERSION ) <= 0 ) {
			$fail[ ] = sprintf( 'Pods Deploy requires Pods JSON API version %1s or later.', PODS_DEPLOY_MIN_JSON_API_VERSION );
		}

		if ( ! version_compare( PODS_JSON_API_VERSION, PODS_DEPLOY_MIN_PODS_VERSION ) <= 0 ) {
			$fail[] = sprintf( 'Pods Deploy requires Pods version %1s or later.', PODS_DEPLOY_MIN_PODS_VERSION );
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
		include_once( PODS_DEPLOY_DIR . 'class-pods-deploy-auth.php' );
		include_once( PODS_DEPLOY_DIR . 'class-pods-deploy.php' );

		return true;

	}

}

/**
 * Run the deployment
 *
 * @TODO Ability to deploy only specific Pods.
 *
 * @since 0.1.0
 *
 * @param $remote_url
 */
function pods_deploy( $remote_url = false, $private_key, $public_key ) {

	if ( ! $remote_url  ) {
		$remote_url = get_option( 'pods_deploy_remote_url' );
	}

	if ( ! $remote_url ) {
		if (  is_admin() ) {
			//@todo admin nag
		}

		return;

	}

	return Pods_Deploy::deploy( $remote_url, $private_key, $public_key );

}

/**
 * Allow remote deployments to site if enabled in UI and keys match.
 *
 * @since 0.3.0
 */
add_action( 'init', 'pods_deploy_auth' );
function pods_deploy_auth() {
	if ( get_option( Pods_Deploy_Auth::$allow_option_name, true ) ) {

		include_once( PODS_DEPLOY_DIR . 'class-pods-deploy-auth.php' );
		
		return Pods_Deploy_Auth::allow_access();

	}

}

