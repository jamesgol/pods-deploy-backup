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


global $pods_deploy_menu_page;

/**
 *
 */
add_action( 'admin_menu', 'pods_deploy_tools_menu' );
function pods_deploy_tools_menu () {
	global $pods_deploy_menu_page;

	$pods_deploy_menu_page = add_management_page( 'Pods Deploy', 'Pods Deploy', 'manage_options', 'pods-deploy', 'pods_deploy_handler' );
}

/**
 * Callback for admin page
 *
 * Determines which function to call to generate admin
 *
 * @since 0.2.0
 */
function pods_deploy_handler () {

	if ( pods_v_sanitized( 'pods-deploy-submit', 'post') ) {

		if( pods_v_sanitized( 'remote-base-url', 'post' ) ) {

			pods_deploy_oauth_handler();

		}
		elseif ( pods_v_sanitized( 'oauth-verifier', 'post' ) ) {

			pods_deploy_oauth_handler_step_2();

		}
	}
	else {
		include 'ui/oauth-1.php';
	}
}

/**
 * Handles first step in oAuth process
 *
 * @since 0.2.0
 */
function pods_deploy_oauth_handler() {

		if (  isset( $_REQUEST[ '_wpnonce' ] ) ) {
			//@todo verify nonce
			$remote_base_url = pods_v_sanitized( 'remote-base-url', 'post' );
			if ( $remote_base_url ) {
				$response = wp_remote_get( $remote_base_url, array ( 'method' => 'GET' ) );
				if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
					pods_error( var_Dump( $response ) );
				}

				$response = json_decode( wp_remote_retrieve_body( $response  ) );

				$auth = pods_v_sanitized( 'authentication', $response );

				if ( $auth ) {
					$oauth_paths = pods_v( 'oauth1', $auth );

					if ( $oauth_paths ) {
						$oauth[ 'base-url' ] = $remote_base_url;
						$oauth[ 'request-url' ] = $request_token_url = pods_v( 'request', $oauth_paths );
						$oauth[ 'auth-url' ] = $authorizeUrl = pods_v( 'authorize', $oauth_paths );
						$oauth[ 'access-url' ] = $accessUrl = pods_v( 'access', $oauth_paths );
						$oauth [ 'version' ] = $oauth_version = pods_v( 'version', $oauth_paths );

						$oauth[ 'consumer-key' ] = $consumer_key = pods_v_sanitized( 'consumer-key', 'post' );
						$oauth[ 'consumer-secret' ] = $consumer_secret = pods_v_sanitized( 'consumer-secret', 'post' );

						update_option( 'pods_deploy_oauth', $oauth );

						if ( $request_token_url && $consumer_key && $consumer_secret && $authorizeUrl ) {
							$oauth_signature_method = "HMAC-SHA1";
							$oauth_timestamp   = time();
							$nonce = wp_create_nonce();

							$args = pods_deploy_oauth_url_args( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $oauth_version );

							$oauth_sig = pods_deploy_oauth_sig_base( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $consumer_secret, $request_token_url, $oauth_version );

							$request_url = add_query_arg( $args, $request_token_url );
							$request_url = add_query_arg(
								array(
									'oauth_signature' => rawurlencode( $oauth_sig ),
								), $request_url );



							$response = wp_remote_get( $request_url, array ( 'method' => 'GET' ) );
							if ( ! is_wp_error( $response ) ) {
								$response = wp_remote_retrieve_body( $response );
							} else {

								pods_error( var_dump( $response ) );
							}

							parse_str( $response, $values );
							if ( pods_v( 'oauth_token', $values ) && pods_v( 'oauth_token_secret', $values ) )  {
								$_SESSION[ "requestToken" ]  = $values[ "oauth_token" ];
								$_SESSION[ "requestTokenSecret" ] = $values[ "oauth_token_secret" ];

								$redirectUrl = $authorizeUrl . "?oauth_token=" . $_SESSION[ "requestToken" ];
								$redirectUrl .= '&output=embed';
								echo sprintf( '<iframe width="500" height="500" src="%1s"></iframe>', $redirectUrl );

								include( PODS_DEPLOY_DIR . 'ui/oauth-2.php' );
							}else {
								pods_error( __( 'Could not get oAuth tokens.', 'pods-deploy' ) );
							}

						}

						}

					}

				}

		}

}

/**
 * Handles second step in oAuth process
 *
 * @since 0.0.2
 */
function pods_deploy_oauth_handler_step_2() {
	if (  isset( $_REQUEST[ '_wpnonce' ] ) ) {
		//@todo verify nonce
		$oauth = get_option( 'pods_deploy_oauth', array() );

		$oauth_verifier = pods_v_sanitized( 'oauth-verifier', 'post' );

		if ( $oauth_verifier && ! empty( $oauth ) ) {
			$request_token_url = pods_v( 'request-url', $oauth );

			$consumer_key     = pods_v( 'consumer-key', $oauth );
			$consumer_secret  = pods_v( 'consumer-secret', $oauth );
			$access_token_url  = pods_v( 'access-url', $oauth );
			if ( $request_token_url && $consumer_key && $consumer_secret && $access_token_url  ) {
				$oauth_signature_method = "HMAC-SHA1";
				$oauth_version         = $oauth [ 'version' ];
				$oauth_timestamp       = time();
				$nonce                = wp_create_nonce();

			}

			$oauth_sig = pods_deploy_oauth_sig_base( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $consumer_secret, $request_token_url, $oauth_version );

			$additional_args = array(
				'oauth_token' => rawurlencode( $_SESSION[ 'requestToken'] ),
				'oauth_verifier' => rawurlencode( $oauth_verifier ),
				'oauth_signature' => rawurlencode( $oauth_sig ),
			);

			$args = pods_deploy_oauth_url_args( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $oauth_version );

			$request_url = add_query_arg( $args, $access_token_url );
			$request_url = add_query_arg( $additional_args, $request_url );


			$response = wp_remote_get( $request_url, array( 'method' => 'GET' ) );

			if ( ! is_wp_error( $response ) ) {
				$response = wp_remote_retrieve_body( $response );
			}
			else{
				pods_error( var_dump( $response ) );
			}

			var_dump( $response );

			$request_url = trailingslashit(  $oauth[ 'base-url' ] ) . 'pods-api/jedi/4725?';
			parse_str( $response, $values );
			if ( pods_v( 'oauth_token', $values ) && pods_v( 'oauth_token_secret', $values ) ) {
				$_SESSION[ "requestToken" ]       = $values[ "oauth_token" ];
				$_SESSION[ "requestTokenSecret" ] = $values[ "oauth_token_secret" ];

				$nonce = wp_create_nonce();
				$oauth_timestamp = time();

				$oauth_sig = pods_deploy_oauth_sig_base( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $consumer_secret, $request_token_url, $oauth_version );

				$additional_args = array(
					'oauth_token' => rawurlencode( $_SESSION[ 'requestToken'] ),
					'oauth_verifier' => rawurlencode( $oauth_verifier ),
					'oauth_signature' => rawurlencode( $oauth_sig ),
				);

				$args = pods_deploy_oauth_url_args( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $oauth_version );

				$request_url = add_query_arg( $args, $request_url );
				$request_url = add_query_arg( $additional_args, $request_url );

				$data        = array ( 'lightsaber_color' => 'green' );
				$data        = json_encode( $data );

				var_dump( $request_url );
				$response    = wp_remote_get( $request_url, array ( 'method' => 'POST', 'body' => $data ) );

				var_dump( $response );

			}

		}

	}

}

/**
 * URL args for oAuth
 *
 * @param      $consumer_key
 * @param      $nonce
 * @param      $oauth_signature_method
 * @param      $oauth_timestamp
 * @param      $oauth_version
 * @param bool $additional_args
 *
 * @since 0.2.0
 *
 * @return array
 */
function pods_deploy_oauth_url_args( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $oauth_version, $additional_args = false ) {
	$args = array(
		'oauth_consumer_key' => rawurlencode( $consumer_key ),
		'oauth_nonce' => rawurlencode( $nonce ),
		'oauth_signature_method' =>  rawurlencode( $oauth_signature_method ),
		'oauth_timestamp' => rawurlencode( $oauth_timestamp ),
		'oauth_version' => rawurlencode( $oauth_version ),
	);

	if ( is_array( $additional_args ) ) {
		$args = array_merge( $args, $additional_args );
	}

	return $args;

}

/**
 * Build a oAuth signature
 *
 * @param $consumer_key
 * @param $nonce
 * @param $oauth_signature_method
 * @param $oauth_timestamp
 * @param $consumer_secret
 * @param $request_token_url
 * @param $oauth_version
 *
 * @since 0.2.0
 *
 * @return string
 */
function pods_deploy_oauth_sig_base( $consumer_key, $nonce, $oauth_signature_method, $oauth_timestamp, $consumer_secret, $request_token_url, $oauth_version ) {
	$sigBase  = "GET&" . rawurlencode( $request_token_url ) . "&"
	            . rawurlencode( "oauth_consumer_key=" . rawurlencode( $consumer_key )
	                            . "&oauth_nonce=" . rawurlencode( $nonce )
	                            . "&oauth_signature_method=" . rawurlencode( $oauth_signature_method )
	                            . "&oauth_timestamp=" . $oauth_timestamp
	                            . "&oauth_version=" . $oauth_version );
	$sigKey  = $consumer_secret . "&";

	$oauth_sig = base64_encode( hash_hmac( "sha1", $sigBase, $sigKey, true ) );

	return $oauth_sig;

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

	if ( is_array( $fail ) ) {
		if ( ! is_admin() ) {
			echo sprintf( '<div id="message" class="error"><p>%s</p></div>',
				implode( $fail )
			);
		}
	}

	else {

		include_once( PODS_DEPLOY_DIR . 'class-pods-deploy.php' );

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
function pods_deploy( $remote_url = false ) {

	if ( ! $remote_url  ) {
		$remote_url = get_option( 'pods_deploy_remote_url' );
	}

	if ( ! $remote_url ) {
		if (  is_admin() ) {
			//@todo admin nag
		}

		return;

	}

	return Pods_Deploy::deploy( $remote_url );

}

//add_action( 'init', 'pods_deploy_auth' );
function pods_deploy_auth() {
	$one = pods_v( 'HTTP_0', $_SERVER );
	$two = pods_v( 'HTTP_1', $_SERVER );
	if ( $one && $two ) {

		if ( $one === get_option( 'pods_deploy_secret_key_1', 'foo' ) && $two === get_option( 'pods_deploy_secret_key_2', 'bar' ) ) {
			add_filter( 'pods_json_api_access_api_package', '__return_true' );
			add_filter( 'pods_json_api_access_api_update_rel', '__return_true' );
		}

	}

}


