<?php

class Pods_Deploy {

	private static $config_key = 'pods_deploy_config';

	/**
	 * URL for remote site's API
	 *
	 * @since 0.1.0
	 *
	 * @var
	 */
	private static $remote_url;

	/**
	 * Run the deployment
	 *
	 * @since 0.1.0
	 *
	 * @param string $remote_url
	 */
	public static function deploy( $remote_url ) {

		self::$remote_url = $remote_url;

		//clear cached data first
		//@TODO Only do this when testing?
		self::clear_cache();

		//create Pods on remote
		$config = self::prepare_data();
		self::do_deploy( $config );


		//recreate config, this time with the info we need for setting relationships
		self::clear_cache();
		$config = self::prepare_data();

		//update Pods which sets relationships
		self::do_deploy( self::get_config(), false );

	}

	/**
	 * Process the deployment
	 *
	 *
	 * @since 0.1.0
	 *
	 * @param array     $config Configuration for deployment.
	 * @param bool      $new    If is a new deployment or an update
	 */
	public static function do_deploy( $config, $new = true ) {

		if ( is_array( $config ) || is_object( $config ) ) {
			$deploy_data = false;

			//prepare and deploy per Pod
			foreach ( $config as $pod_name => $pod ) {
				$data = false;

				//prepare data for this Pod
				if ( isset( $pod[0] )  ) {
					$pod = $pod[0];
				}
				if ( $new  ) {
					$fields = pods_v( 'fields', $pod );
					$config = pods_v( 'config', $pod );
				}
				else{
					$fields = pods_v( 'remote_fields', $pod );
					$config = pods_v( 'remote_config', $pod );
				}



				if ( ( is_object ( $fields ) || is_array( $fields ) ) && ( is_object( $config ) || is_array( $config )  )  ) {
					$data = (array) $config;
					$data[ 'fields' ] = (object) $fields;

				}
				else{
					//@TODO error
				}

				//if data checks out deploy
				if ( is_array( $data ) ) {
					$url = self::base_url( 'remote' );

					//add correct endpoint
					if ( $new ) {
						$method = 'add_pod';
						$url = untrailingslashit( $url ) . '?' . $method;
					}
					else {
						$method = 'save_pod';

						$url = trailingslashit( $url )  . "{$pod_name}?{$method}";

					}

					//make the request
					self::request( $url, self::headers(), 'POST', $data );

				}

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
	 * Builds our configuration
	 *
	 * @since 0.1.0
	 *
	 * @return bool|array
	 */
	public static function store_config(  ) {
		$pods = false;

		foreach ( self::sites() as $site ) {
			$url = self::base_url( $site );

			if ( ! is_null( $url ) ) {
				//@todo only make request when needed, most of data isn't already in config
				$data = self::get_pods( $url, $site );

				$data = json_decode( $data );
				if ( is_array( $data ) || is_object( $data ) ) {

					foreach ( $data as $pod ) {
						$pods[ $pod->name ][ "{$site}_id" ] = $pod->id;
						$pods[ $pod->name ][ "{$site}_config" ] = $pod;

					}
				}

				if ( $pods ) {
					foreach ( $pods as $name => $data ) {
						$fields = self::get_fields( $url, $name );

						if ( ! is_null( $fields ) && ( is_object( $fields ) || is_array( $fields ) ) ) {
							$fields                            = (array) $fields;
							$pods[ $name ][ "{$site}_fields" ] = $fields;
						}

					}
				}

			}

		}

		if ( is_array( $pods ) ) {

			self::save_config( $pods );

			return $pods;

		}



	}

	/**
	 * Get the config
	 *
	 * @since 0.0.3
	 * @return array|bool|mixed|null|void
	 */
	public static function get_config() {
		if ( false == ( $config = pods_transient_get( self::$config_key ) ) ){

			$config = self::store_config();

		}

		return $config;

	}

	public static function save_config( $config ) {

		pods_transient_set( self::$config_key, $config );

	}

	/**
	 * Get fields for a Pod
	 *
	 * @param string    $base_url URL for pods-api end point.
	 * @param string    $pod    Name of Pod to get fields for.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public static function get_fields( $base_url, $pod ) {
		$url = trailingslashit( $base_url ) . "{$pod}";
		$data = self::request( $url, self::headers() );
		if ( ! is_wp_error( $data ) ) {
			$data = json_decode( $data );

			$fields = pods_v( 'fields', $data );

			return $fields;

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
	 * Prepare data for a deployment
	 *
	 * @since 0.1.0
	 *
	 * @return bool|array
	 */
	public static function prepare_data() {
		$pods = self::get_config();
		$new_id = $deploy = false;
		if( is_array( $pods ) ) {
			foreach( $pods as $pod_name => $pod ) {


				if ( isset ( $pod[ 'remote_fields' ] ) ) {

					$relationships = self::find_relationships( $pods );
					foreach ( $pod[ 'remote_fields' ] as $field_name => $data ) {
						if ( array_key_exists( $field_name, $relationships ) ) {
							$relationship = $relationships[ $field_name ];
							$from_id = $data->id;
							$sister_id = $pods[ $relationship[ 'to' ][ 'pod' ] ][ 'remote_fields' ][ $relationship[ 'to'] [ 'field' ] ]->id;
							$pods[ $relationship[ 'to' ][ 'pod' ] ][ 'remote_fields' ][ $relationship[ 'to'] [ 'field' ] ]->sister_id = $from_id;
							$pods[ $relationship[ 'from' ][ 'pod' ] ][ 'remote_fields' ][ $relationship[ 'from'] [ 'field' ] ]->sister_id = $sister_id;
						}

					}

					$fields = pods_v( 'remote_fields', $pod );
					$config = pods_v( 'remote_config', $pod );


					$deploy[ $pod_name ] = array(
						'config' => $config,
						'fields' => $fields,
					);



					//Update our config with the corrected values for remote sister fields.
					self::save_config( $pods );


				}
				else{
					//no remote data yet, so clear IDs/sister IDs so we can create Pods on remote.
					foreach( $pod[ 'local_fields' ] as $field_name => $data ) {
						unset( $data->id );
						if ( isset( $data->id ) ) {

							unset( $data->sister_id );
						}
					}

				}

				$fields = pods_v( 'local_fields', $pod );
				$config = pods_v( 'local_config', $pod );

				$deploy[ $pod_name ] = array(
					'config' => $config,
					'fields' => $fields,

				);


			}



			return $deploy;

		}


	}




	/**
	 * Find relationships
	 *
	 * @param $config
	 *
	 * @return bool|array
	 */
	public static function find_relationships( $config ) {
		if ( is_array( $config ) ) {
			foreach( $config as $pod_name => $pod ) {
				$local_fields  = pods_v( 'local_fields', $pod );
				$relationships = false;

				if ( ! is_null( $local_fields ) ) {

					foreach ( $local_fields as $field_name => $field ) {
						if ( isset( $field->sister_id ) ) {
							$relationships[ pods_v( 'name', $field ) ] = array (
								'from' => array (
									'pod'   => $pod_name,
									'field' => pods_v( 'name', $field ),
								),
								'to'   => self::find_by_id( $field->sister_id ),
							);

						}

					}

				}

				if ( is_array( $relationships ) ) {

					return $relationships;

				}

			}

		}

	}

	/**
	 * Build an array of field names and IDs per Pod.
	 *
	 * @param string $local_or_remote Optional. To base on local, the default, or remote config.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public static function fields_by_name_id( $local_or_remote = 'local' ) {
		$pods = self::get_config();
		$fields = false;

		if ( is_array( $pods ) ) {
			foreach ( $pods as $pod_name => $pod ) {

				if ( isset( $pod[ "{$local_or_remote}_fields" ] ) ) {
					$local_fields = $pod[ "{$local_or_remote}_fields" ];
					foreach ( $local_fields as $field_name => $data ) {
						$fields[ $pod_name ][ $field_name ] = $data->id;
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
	 * @param string    $local_or_remote    Optional. To base on local, the default, or remote config.
	 *
	 * @since 0.1.0
	 *
	 * @return array                        Name of Pod and field name.
	 */
	public static function find_by_id( $id, $local_or_remote = 'local' ) {
		$fields_by_id = self::fields_by_name_id( $local_or_remote );
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
	 * @param string $local_or_remote   Optional. To base on local, the default, or remote config.
	 *
	 *
	 * @since 0.0.3
	 *
	 * @return array                    Name of Pod and field ID
	 */
	public static function find_by_name( $name, $local_or_remote = 'local' ) {
		$fields_by_name = self::fields_by_name_id( $local_or_remote );

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

	public static function clear_cache() {

		pods_transient_clear( self::$config_key );

	}

	/**
	 * Get URL to make request to.
	 *
	 * @param string        $site       Site to request from local|remote
	 * @param string        $action     Action to take get_pods|add_pod|get_pod|save_pod|delete_pod
	 * @param bool|string   $pod_name   Name of Pod. Required for get_pod|save_pod|delete_pod not used for get_pods|add_pod|
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

