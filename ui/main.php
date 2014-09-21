<?php
	$keys = Pods_Deploy_Auth::get_keys( false );
	$public_local = pods_v_sanitized( 'public', $keys, '' );
	$private_local = pods_v_sanitized( 'private', $keys, '' );
	$keys = Pods_Deploy_Auth::get_keys( true );
	$public_remote = pods_v_sanitized( 'public', $keys, '' );
	$private_remote = pods_v_sanitized( 'private', $keys, '' );
	$deploy_active = Pods_Deploy_Auth::deploy_active();
	if ( $deploy_active ) {
		$key_gen_submit = __( 'Disable Deployments', 'pods-deploy' );
		$key_gen_header = __( 'Click to revoke keys and prevent deployments to this site.', 'pods-deploy' );

	}
	else{
		$key_gen_submit = __( 'Allow Deployments', 'pods-deploy' );
		$key_gen_header = __( 'Click to generate new keys and allow deployments to this site', 'pods-deploy' );
	}

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

	$api = pods_api();
	$params[ 'names' ] = true;
	$pod_names = $api->load_pods( $params );

	foreach( $pod_names as $name => $label ) {
		$form_fields[ $name ] = array(
			'label' => $label,
			'type' => 'boolean',
		);
	}



?>
<div class="wrap pods-admin">
	<form action="" method="post">

		<div id="icon-pods" class="icon32"><br /></div>

		<?php
		$default = 'deploy';

		$tabs = array(
			'deploy' => __( 'Deploy From This Site', 'pods-deploy' ),
			'key-gen' => __( 'Allow Deploying To This Site', 'pods-deploy' )
		);
		?>

		<h2 class="nav-tab-wrapper">
			<?php
			foreach ( $tabs as $tab => $label ) {
				$class = '';

				if ( $tab == pods_v( 'tab', 'get', $default ) ) {
					$class = ' nav-tab-active';

					$label = $label;
				}

				$url = pods_query_arg( array( 'tab' => $tab ), array( 'page' ) );
				?>
				<a href="<?php echo $url; ?>" class="nav-tab<?php echo $class; ?>">
					<?php echo $label; ?>
				</a>
			<?php
			}
			?>
		</h2>
		<img src="<?php echo PODS_URL; ?>ui/images/pods-logo-notext-rgb-transparent.png" class="pods-leaf-watermark-right" />

		<?php
		$tab = pods_v( 'tab', 'get', $default );
		$tab = sanitize_title( $tab );

		$data = compact( array( 'keys', 'public_local', 'private_local', 'public_remote', 'private_remote', 'deploy_active', 'key_gen_submit', 'key_gen_header', 'form_fields' ) );
		echo pods_view( PODS_DEPLOY_DIR . 'ui/' . $tab . '.php', $data );
		?>
	</form>
</div>
