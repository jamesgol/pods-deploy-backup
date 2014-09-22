<?php

class Pods_Deploy_UI {

	/**
	 * Callback for adding Pods Deploy to menus.
	 *
	 * Callback is in the activation function below.
	 *
	 * @since 0.4.0
	 */
	function menu ( $admin_menus ) {

		$admin_menus[ 'pods-deploy'] = array(
			'label' => __( 'Pods Deploy', 'pods-deploy' ),
			'function' => array( $this, 'deploy_handler' ),
			'access' => 'manage_options'

		);

		return $admin_menus;

	}

	/**
	 * Handles UI output and form processing
	 *
	 * @since 0.4.0
	 */
	function deploy_handler () {

		if ( pods_v_sanitized( 'pods-deploy-submit', 'post') ) {
			if ( ! pods_deploy_dependency_check() ) {
				return;
			}

			if ( ! ( $nonce = pods_v_sanitized( '_wpnonce', $_REQUEST ) ) || ! wp_verify_nonce( $nonce, 'pods-deploy' ) ) {
				pods_error( __( 'Bad nonce.', 'pods-deploy' ) );
			}

			$remote_url = pods_v_sanitized( 'remote-url', 'post', false, true );
			$private_key = pods_v_sanitized( 'private-key', 'post' );
			$public_key = pods_v_sanitized( 'public-key', 'post' );
			$pods = pods_v_sanitized( 'pods', 'post' );
			if ( $remote_url && $private_key && $public_key ) {
				Pods_Deploy_Auth::save_local_keys( $private_key, $public_key );

				$params = array(
					'remote_url' => $remote_url,
					'private_key' => $private_key,
					'public_key' => $public_key,
					'pods'       => $pods,
				);

/*				$pod_names = $this->pod_names();
				if ( is_array( $pod_names ) ) {
					foreach ( $pod_names as $name => $label ) {
						if ( pods_v_sanitized( $name, 'POST' ) ) {
							$params[ 'pods' ][ ] = $name;
						}

					}

				} */

				$params[ 'components' ] = array( 'migrate-packages' );
				$components = $this->active_components();
				if ( is_array( $components ) ) {
					foreach (  $components as $name => $label  ) {
						if ( pods_v_sanitized( $name, 'POST' ) ) {
							$params[ 'components' ][ ] = $name;
						}
					}

				}

				pods_deploy( $params );

			}
			else{
				_e( 'Keys and URL for remote site not set', 'pods-deploy' );

				pods_error( var_dump( array($remote_url, $private_key, $public_key ) ) );
			}
		}
		elseif( pods_v_sanitized( 'pods-deploy-key-gen-submit', 'post' ) ) {
			$activate = pods_v_sanitized( 'allow-deploy', 'post' );
			if ( $activate ) {
				Pods_Deploy_Auth::allow_deploy();
				Pods_Deploy_Auth::generate_keys();
				$this->include_view();
			}
			else {
				Pods_Deploy_Auth::revoke_keys();
			}

			$this->include_view();
		}
		else {
			$this->include_view();
		}

	}

	/**
	 * Output a list of field names.
	 *
	 * @since 0.4.0
	 *
	 * @return array|mixed
	 */
	function pod_names() {
		$api = pods_api();
		$params[ 'names' ] = true;
		$pod_names = $api->load_pods( $params );

		return $pod_names;

	}

	/**
	 * Get an array of active components
	 *
	 * @since 0.4.0
	 *
	 * @return array|void
	 */
	function active_components() {
		$components = new PodsComponents();
		$components = $components->get_components();
		$component_names = $components = wp_list_pluck( $components, 'Name'  );
		$active_components = get_option( 'pods_component_settings' );
		$active_components =  json_decode( $active_components );
		$active_components = pods_v( 'components', $active_components );
		$active_components =  array_keys( (array) $active_components );

		foreach( $active_components as $component ) {
			if ( ! is_null( pods_v( $component,$component_names ) ) ) {

				$the_active_components[ $component ] = $component_names[ $component ];

			}

		}

		return $the_active_components;

	}

	/**
	 * Form fields for deploy form
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	function form_fields() {
		$keys = Pods_Deploy_Auth::get_keys( false );
		$public_local = pods_v_sanitized( 'public', $keys, '' );
		$private_local = pods_v_sanitized( 'private', $keys, '' );

		$form_fields = array(
			'remote-url' =>
				array(
					'label' => __( 'URL To Remote Site API', 'pods-deploy' ),
					'help' => __( 'For example "http://example.com/wp-json"', 'pods-deploy' ),
					'value' => '',
					'options' => '',
				),
			'public-key' =>
				array(
					'label' => __( 'Remote Site Public Key', 'pods-deploy' ),
					'help' => __( 'Public key from remote site.', 'pods-deploy' ),
					'value' => $public_local,
					'options' => '',
				),
			'private-key' =>
				array(
					'label' => __( 'Remote Site Private Key', 'pods-deploy' ),
					'help' => __( 'Private key from remote site.', 'pods-deploy' ),
					'value' => $private_local,
					'options' => '',
				),

		);
/*
		$pod_names = $this->pod_names();

		if ( is_array( $pod_names ) ) {
			foreach ( $pod_names as $name => $label ) {
				$form_fields[ $name ] = array (
					'label' => $label,
					'type'  => 'boolean',
				);
			}
		}
*/
		$active_components = $this->active_components();

		if ( is_array( $active_components ) ) {
			foreach( $active_components as $name => $label ) {
				$form_fields[ $name ] = array (
					'label' => $label,
					'type'  => 'boolean',
				);
			}
		}

		return $form_fields;

	}

	/**
	 * Include main UI view and add scope data into it.
	 *
	 * @since 0.4.0
	 *
	 * @return bool|string
	 */
	function include_view() {
		$keys           = Pods_Deploy_Auth::get_keys( true );
		$public_remote  = pods_v_sanitized( 'public', $keys, '' );
		$private_remote = pods_v_sanitized( 'private', $keys, '' );
		$deploy_active  = Pods_Deploy_Auth::deploy_active();
		wp_enqueue_style( 'pods-wizard' );
		if ( $deploy_active ) {
			$key_gen_submit = __( 'Disable Deployments', 'pods-deploy' );
			$key_gen_header = __( 'Click to revoke keys and prevent deployments to this site.', 'pods-deploy' );

		} else {
			$key_gen_submit = __( 'Allow Deployments', 'pods-deploy' );
			$key_gen_header = __( 'Click to generate new keys and allow deployments to this site', 'pods-deploy' );
		}
		$form_fields = $this->form_fields();

		$data = compact( array(
				'keys',
				'public_local',
				'private_local',
				'public_remote',
				'private_remote',
				'deploy_active',
				'key_gen_submit',
				'key_gen_header',
				'form_fields'
			) );


		return pods_view( PODS_DEPLOY_DIR . 'ui/main.php', $data );

	}

} 
