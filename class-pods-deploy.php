<?php

class Pods_Deploy {
	public static $remote_url;

	public static function deploy( $remote_url, $public_key, $private_key ) {

		self::$remote_url = $remote_url;

		if ( ! class_exists(  'Pods_Migrate_Packages' ) ) {
			return new WP_Error( 'pods-deploy-need-packages',  __( 'You must activate the Packages Component on both the site sending and receiving this package.', 'pods-deploy' ) );
		}

		//@todo add options for these params
		$params = array(
			'pods' => true,
			'templates' => true,
			'page' => true,
			'helpers' => true,
		);
		$data = Pods_Migrate_Packages::export( $params );

		$url = trailingslashit( $remote_url ) . 'pods-components?package';

		$request_token = Pods_Deploy_Auth::generate_token( $public_key, $private_key );

		$url = Pods_Deploy_Auth::add_to_url( $public_key, $request_token, $url );

		$data = json_encode( $data );

		$response = wp_remote_post( $url, array (
				'method'    => 'POST',
				'body'      => $data,
			)
		);

		if ( ! is_wp_error( $response ) && 201 == wp_remote_retrieve_response_code( $response ) ) {
			echo self::output_message( __( 'Package deployed successfully. ', 'pods-deploy' ), $url );

			$responses = array();
			$api = pods_api();
			$params[ 'names' ] = true;
			$pod_names = $api->load_pods( $params );
			$pod_names = array_flip( $pod_names );
			$data = Pods_Deploy::get_relationships();
			$pods_api_url = trailingslashit( $remote_url ) . 'pods-api/';

			foreach( $pod_names as $pod_name ) {
				$url = $pods_api_url. "{$pod_name}/update_rel";
				$url = Pods_Deploy_Auth::add_to_url( $public_key, $request_token, $url );
				$responses[] = $response = wp_remote_post( $url, array (
						'method'      => 'POST',
						'body'        => json_encode( $data ),
					)
				);

				if ( ! is_wp_error( $response ) && 201 == wp_remote_retrieve_response_code( $response ) ) {
					echo self::output_message(
						__( sprintf( 'Relationships for the %1s Pod were updated.', $pod_name )
						, 'pods-deploy' ),
						$url
					);
				}
				else {
					echo self::output_message(
						__( sprintf( 'Relationships for the %1s Pod were not updated.', $pod_name )
							, 'pods-deploy' ),
						$url
					);

					var_dump( $response );

				}

			}

			echo self::output_message( __( 'Deployment complete :)', 'pods-deploy' ) );

		}
		else{
			echo self::output_message( __( 'Package could not be deployed :(', 'pods-deploy' ) );
			var_dump( $response );
		}


	}


	/**
	 * Gets relationships
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public static function get_relationships(){
		$relationships = false;

		$api = pods_api();
		$pods = $api->load_pods();

		foreach( $pods as $pod ) {
			$pod_name = pods_v( 'name', $pod );
			if ( ! is_null( $local_fields = pods_v( 'fields', $pod ) ) ) {
				foreach ( $local_fields as $field_name => $field ) {
					if ( '' !== ( $sister_id = pods_v( 'sister_id', $field ) ) ) {

						$relationships[ pods_v( 'name', $field ) ] = array (
							'from' => array (
								'pod_name'   => $pod_name,
								'field_name' => pods_v( 'name', $field ),
							),
							'to'   => self::find_by_id( $sister_id, $pods ),
						);

					}

				}


			}

		}

		return $relationships;

	}

	/**
	 * Build an array of field names and IDs per Pod.
	 *

	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public static function fields_by_name_id( $pods ) {
		$fields = false;

		if ( is_array( $pods ) ) {
			foreach ( $pods as  $pod ) {

				$pod_name = pods_v( 'name', $pod );

				$local_fields = pods_v( 'fields', $pod );
				if ( $local_fields ) {
					foreach ( $local_fields as $field_name => $data ) {
						$fields[ $pod_name ][ $field_name ] = $data[ 'id' ];
					}
				}



			}

		}

		return $fields;
	}

	/**
	 * Get a field name by ID
	 *
	 * @param int       $id                 The field's ID.

	 *
	 * @since 0.1.0
	 *
	 * @return array                        Name of Pod and field name.
	 */
	public static function find_by_id( $id, $pods ) {
		$fields_by_id = self::fields_by_name_id( $pods );
		if ( is_array( $fields_by_id ) ) {
			foreach( $fields_by_id as $pod_name => $fields ) {
				$search = array_search( $id, $fields );

				if ( $search ) {

					return array(
						'pod_name' => $pod_name,
						'field_name' => $search,
					);
				}

			}

		}

	}

	/**
	 * Get a field ID by name.
	 *
	 * @param string $name              The field's name

	 *
	 *
	 * @since 0.0.3
	 *
	 * @return array                    Name of Pod and field ID
	 */
	public static function find_by_name( $name, $pods ) {
		$fields_by_name = self::fields_by_name_id( $pods );

		if ( is_array( $fields_by_name ) ) {
			$fields_by_name = array_flip( $fields_by_name );
			foreach( $fields_by_name as $pod_name => $fields ) {
				$search = array_search( $name, $fields );

				if ( $search ) {
					return array(
						'pod_name'   => $pod_name,
						'field_name' => $search,
					);
				}

			}

		}

	}

	/**
	 * Output a message during deployment, with the time in seconds message was generated.
	 *
	 * @param  string   $message Message to show.
	 * @param string    $url Optional. The URL to show for message.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public static function output_message( $message, $url = '' ){
		if ( is_string( $message ) ) {
			$time = date( 's' );

			return sprintf( '<div class="pods-deploy-message"><p>%1s</p> <span="pods-deploy-message-time">%2s</span>  <span="pods-deploy-message-url">%3s</span></div>', $message, $time, $url );
		}

	}

} 

