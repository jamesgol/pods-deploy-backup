<?php
class Pods_Deploy_Auth {

	public static function allow_access() {
		if ( self::check_auth() ) {
			add_filter( 'pods_json_api_access_api_package', '__return_true' );
			add_filter( 'pods_json_api_access_api_update_rel', '__return_true' );
		}

	}

	public static function check_auth() {
		return true;
	}
} 
