<?php
/*
 Plugin Name: Pods Deploy
 */

class pods_deploy {
	private $config_key = 'pods_deploy_config';

	function __construct() {


	}

	function deploy() {
		//clear cached data first
		//@TODO Only do this when testing?
		$this->clear_cache();

		//create Pods on remote
		$config = $this->prepare_data();
		$this->do_deploy( $config );


		//recreate config, this time with the info we need for setting relationships
		$this->clear_cache();
		$config = $this->prepare_data();

		//update Pods which sets relationships
		$this->do_deploy( $this->get_config(), false );

	}

	function do_deploy( $config, $new = true ) {

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
					$url = $this->base_url( 'remote' );

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
					$this->request( $url, $this->headers(), 'POST', $data );

				}

			}


		}


	}

	function request( $url, $headers, $method = 'GET', $data = false ) {

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

	function store_config(  ) {
		$pods = false;

		foreach ( $this->sites() as $site ) {
			$url = $this->base_url( $site );

			if ( ! is_null( $url ) ) {
				//@todo
				$data = $this->get_pods( $url, $site );

				$data = json_decode( $data );
				if ( is_array( $data ) || is_object( $data ) ) {

					foreach ( $data as $pod ) {
						$pods[ $pod->name ][ "{$site}_id" ] = $pod->id;
						$pods[ $pod->name ][ "{$site}_config" ] = $pod;

					}
				}

				if ( $pods ) {
					foreach ( $pods as $name => $data ) {
						$fields = $this->get_fields( $url, $name );

						if ( ! is_null( $fields ) && ( is_object( $fields ) || is_array( $fields ) ) ) {
							$fields                            = (array) $fields;
							$pods[ $name ][ "{$site}_fields" ] = $fields;
						}

					}
				}

			}

		}

		if ( is_array( $pods ) ) {

			$this->save_config( $pods );

			return $pods;

		}



	}

	function get_config() {
		if ( false == ( $config = pods_transient_get( $this->config_key ) ) ){

			$config = $this->store_config();

		}

		return $config;

	}

	function save_config( $config ) {

		pods_transient_set( $this->config_key, $config );

	}

	function get_fields( $base_url, $pod ) {
		$url = trailingslashit( $base_url ) . "{$pod}";
		$data = $this->request( $url, $this->headers() );
		if ( ! is_wp_error( $data ) ) {
			$data = json_decode( $data );

			$fields = pods_v( 'fields', $data );

			return $fields;

		}

	}

	function get_pods( $url, $site = 'local', $names_only = false ) {
		$data = $this->request( $url, $this->headers(), 'GET' );
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

	function prepare_data() {
		$pods = $this->get_config();
		$new_id = $deploy = false;
		if( is_array( $pods ) ) {
			foreach( $pods as $pod_name => $pod ) {


				if ( isset ( $pod[ 'remote_fields' ] ) ) {

					$relationships = $this->find_relationships( $pods );
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
					$this->save_config( $pods );


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
	 * @param $config
	 *
	 * @return bool|array
	 */
	function find_relationships( $config ) {
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
								'to'   => $this->find_by_id( $field->sister_id ),
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


	function fields_by_name_id( $local_or_remote = 'local' ) {
		$pods = $this->get_config();
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

	function find_by_id( $id, $local_or_remote = 'local' ) {
		$fields_by_id = $this->fields_by_name_id( $local_or_remote );
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

	function find_by_name( $name, $local_or_remote = 'local' ) {
		$fields_by_name = $this->fields_by_name_id( $local_or_remote );

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



	function headers() {
		$headers    = array (
			'Authorization' => 'Basic ' . base64_encode( 'admin' . ':' . 'password' ),
		);

		return $headers;
	}

	function base_url( $site = 'local' ) {
		$urls = array(
			'local' => json_url( 'pods-api' ),
			'remote' => 'http://local.wordpress.dev/wp-json/pods-api',
		);

		return pods_v( $site, $urls  );

	}

	function sites() {

		return array( 'local', 'remote' );

	}

	function clear_cache() {

		pods_transient_clear( $this->config_key );

	}

} 

