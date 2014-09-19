<?php

class Pods_Deploy {


	function deploy() {
		$api = pods_api();
		$params[ 'names' ] = true;
		$pod_names = $api->load_pods( $params );
		if ( is_array( $pod_names ) ) {
			$headers = self::headers();
			foreach( $pod_names as $pod ) {
				$url = self::urls( 'local', 'get_pod', $pod );
				$data = self::request( $url, $headers );
				$url = self::urls( 'remote', 'add_pod', $pod );
				$pod = self::request( $url, $headers, 'POST', $data );
			}

			$data = self::get_relationships();
			foreach( $pod_names as $pod ) {

				$url = self::urls( 'remote', 'update_rel', $pod );

				$pod = self::request( $url, $headers, 'POST', $data );
			}

		}

	}

	/**
	 * Makes request to the REST API
	 *
	 * @param string        $url        URL to make request to.
	 * @param array         $headers    Headers for request. Must include authorization.
	 * @param string        $method     Optional. Request method, must be 'GET', the default, or 'POST.
	 * @param bool|array    $data       Optional. Data to be used as body of POST requests.
	 *
	 * @return bool|string|WP_Error     Body of response on success or WP_Error on failure.
	 */
	public static function request( $url, $headers, $method = 'GET', $data = false ) {

		//only allow GET/POST requests
		if ( ! in_array( $method, array( 'GET', 'POST' ) ) ) {
			$error = new WP_Error( 'pods-deploy-bad-method' , __( 'Pods Deploy request only works with POST & GET requests', 'domain' ) );
			return $error;
		}

		//prepare args for request
		$request_args = array (
			'method' 	=> $method,
			'headers' 	=> $headers,
		);

		//add data for POST request
		if ( $method == 'POST' ) {
			if ( $data ) {
				$request_args[ 'body' ] = json_encode( $data );
			}
			else{
				$error = new WP_Error( 'pods-deploy-post-needs-data' , __( 'Pods Deploy needs data for a POST request', 'domain' ) );
				return $error;
			}
		}

		//make request
		$response = wp_remote_post( $url, $request_args );

		//make sure response isn't an error
		if ( ! is_wp_error( $response )  ) {
			echo "SUCCESS on  {$method} to {$url} \n";
			$data = wp_remote_retrieve_body( $response  );
			return $data;
		}
		else{
			echo "FAIL on {$method} to {$url} ";
			$error = new WP_Error( 'pods-deploy-bad-request' , __( 'Pods Deploy Bad Request', 'domain' ) );
			$error->add_data( $response );

			return $error;
		}

	}


	/**
	 * Get basic info about all Pods.
	 *
	 * @param string    $url        URL for pods-api endpoint.
	 * @param string    $site       Site to get details for.
	 * @param bool      $names_only Return only the names.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public static function get_pods( $url, $site = 'local', $names_only = false ) {

		$data = self::request( $url, self::headers(), 'GET' );
		if( ! is_wp_error( $data ) ) {
			if ( ! $names_only ) {
				return $data;
			}
			else {
				$pods = false;
				$data = json_decode( $data  );
				if ( is_array( $data ) ) {
					foreach( $data as $pod ) {
						$pods[ $pod->name ] = array(
							"{$site}_id" => $pod->id,
							"{$site}_config" => $pod,
						);
					}
				}

				if ( is_array( $pods ) ) {

					return $pods;

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
			foreach ( $pods as $pod_name => $pod ) {


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
						'pod' => $pod_name,
						'field' => $search,
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
						'pod'   => $pod_name,
						'field' => $search,
					);
				}

			}

		}

	}


	/**
	 * Headers for requests
	 *
	 * @TODO auth intelligently
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public static function headers() {
		$headers    = array (
			'Authorization' => 'Basic ' . base64_encode( 'pods-deploy-2' . ':' . 'pods-deploy-2' ),
		);

		return $headers;
	}

	/**
	 * Get base URL for local or remote pods-api end points of REST API
	 *
	 * @param string $site Site name, either local, the default, or remote.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public static function base_url( $site = 'local' ) {
		$urls = array(
			'local' => json_url( 'pods-api' ),
			'remote' => self::$remote_url,
		);

		return pods_v( $site, $urls  );

	}

	/**
	 * Site name
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public static function sites() {

		return array( 'local', 'remote' );

	}


	/**
	 * Get URL to make request to.
	 *
	 * @param string        $site       Site to request from local|remote
	 * @param string        $action     Action to take get_pods|add_pod|get_pod|save_pod|delete_pod|update_rel
	 * @param bool|string   $pod_name   Name of Pod. Required for get_pod|save_pod|delete_pod|update_rel not used for get_pods|add_pod|
	 *
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public static function urls( $site, $action, $pod_name = false ) {
		$url = self::base_url( $site );

		if ( $action == 'get_pods' ) {

			return trailingslashit( $url ) . "?get_pods";

		}
		elseif( $action == 'add_pod' ) {

			return trailingslashit( $url ) . "?add_pod";

		}
		elseif( ! $pod_name ) {
			new wp_error( 'pods-deploy-need-pod-name-for-url', __( sprintf( 'The action %1s requires that you specify a Pod name.', $action ), 'pods-deploy' ) );
		}
		else{
			if( $action == 'get_pod' ) {

				return trailingslashit( $url ) . "{$pod_name}?get_pod";

			}
			elseif( $action == 'save_pod' ) {

				return trailingslashit( $url ) . "{$pod_name}?save_pod";

			}
			elseif( $action == 'update_rel' ) {

				return trailingslashit( $url ) . "{$pod_name}?update_rel";

			}
			elseif( $action == 'delete_pod' ) {

				return trailingslashit( $url ) . "{$pod_name}?delete_pod";

			}

		}
	}

	/**
	 * Takes response from this->request() and decodes JSON to PHP or returns wp_error object if an error occurred in the request.
	 *
	 * @param   json|wp_error $response
	 *
	 * @since 0.1.0
	 *
	 * @return  array|wp_error
	 */
	private static function conditionally_decode( $response ) {

		if ( ! is_wp_error( $response ) ) {
			$response = json_decode( $response );
		}

		return $response;
		
	}

} 

