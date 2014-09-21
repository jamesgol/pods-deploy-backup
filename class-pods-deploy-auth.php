<?php
class Pods_Deploy_Auth {
	public static $public_key_option_name = 'pods_deploy_public_key';
	public static $private_key_option_name = 'pods_deploy_private_key';

	public static function allow_access() {
		if ( self::check_auth() ) {
			add_filter( 'pods_json_api_access_components_package', '__return_true' );
			add_filter( 'pods_json_api_access_api_update_rel', '__return_true' );
		}

	}

	public static function check_auth() {
		return true;
		$token = self::get_request_token();
		$public  =  self::get_request_key();
		if ( $public  && $token ) {
			$token  = urldecode( 'token' );
			$secret = self::generate_private_key();
			if ( hash( 'md5', $secret . $public ) === $token ) {

				return true;

			}

		}

	}

	private static function get_request_token() {
		if ( pods_v( 'pods-deploy-token', $_SERVER ) ) {
			return urldecode( pods_v( 'HTTP_X_PODS_DEPLOY_TOKEN', $_SERVER ) );
		}

	}

	private static function get_request_key() {

		if ( pods_v( 'pods-deploy-key', $_SERVER ) ) {
			return urldecode( pods_v( 'HTTP_X_PODS_DEPLOY_KEY', $_SERVER ) );
		}

	}


	public static function get_keys() {

		return array(
			'public' => get_option( self::$public_key_option_name ),
			'private' => get_option( self::$private_key_option_name ),
		);

	}

	public static function generate_keys() {

		update_option( self::$public_key_option_name, self::generate_public_key() );
		update_option( self::$private_key_option_name, self::generate_private_key() );

	}

	private static function generate_public_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$public   = hash( 'md5', self::random_string() . $auth_key . date( 'U' ) );


		return $public;

	}

	private static function generate_private_key( ) {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$secret   = hash( 'md5', get_current_user_id() . $auth_key . date( 'U' ) );

		return $secret;

	}

	private static function generate_token() {
		$public = pods_v( 'public', self::get_keys() );
		$private = pods_v( 'private', self::get_keys() );

		if ( $public && $private ) {
			return hash( 'md5', $public, $private );
		}
	}

	private static function random_string() {
		return substr( str_shuffle( '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, 42);

	}
	
	public static function add_to_url( $key, $token, $url ) {
		$args = array(
			'pods-deploy-key' => urlencode( $key ),
			'pods-deploy-token' => urlencode( $token ),
		);

		return add_query_arg( $args, $url );

	}



} 
