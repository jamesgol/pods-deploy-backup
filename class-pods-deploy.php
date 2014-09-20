<?php

class Pods_Deploy {
	public static $remote_url;

	public static function deploy( $remote_url ) {
		$remote_url = trailingslashit( $remote_url ) . 'pods-api/';
		self::$remote_url = $remote_url;

		$headers = self::headers();

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

		$url = $remote_url . 'package';
		$response = wp_remote_post( $url, array (
				'method' => 'POST',
				'headers'     => $headers,
				'body' => $data,
			)
		);

		//@TODO check && 201 == wp_remote_retrieve_response_code( $response )
		if ( ! is_wp_error( $response ) ) {
			$responses = array();
			$api = pods_api();
			$params[ 'names' ] = true;
			$pod_names = $api->load_pods( $params );
			$data = Pods_Deploy::get_relationships();
			foreach( $pod_names as $pod_name ) {
				$url = $remote_url. "{$pod_name}/update_rel";
				$responses[] = wp_remote_post( $url, array (
						'method'      => 'POST',
						'headers'     => $headers,
						'body'        => json_encode( $data ),
					)
				);

			}

			if ( empty( $responses ) ) {
				foreach( $responses as $response ) {
					echo wp_remote_retrieve_body( $response );
				}

			}

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
	 * Headers for requests
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public static function headers( $key, $token ) {
		$headers    = array (
			'pods_deploy_key' => $key,
			'pods_deploy_token' => $token,
		);

		$headers = json_encode( $headers );

		return $headers;
	}

} 

