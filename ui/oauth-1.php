<?php $oauth = get_option( 'pods_deploy_oauth', array() ); ?>
<div class="wrap pods-deploy-admin">
	<form action="?page=pods-deploy" method="post">

		<div id="icon-tools" class="icon32"><br></div>
		<h2>Pods Deploy</h2>
		<?php echo wp_nonce_field(); ?>
		<p>
			<label for="remote-base-url">
				<?php _e( 'URL For Remote Site REST API', 'pods-deploy' ); ?>
			</label>
			<input type="text" class="" name="remote-base-url" id="remote-base-url" value="<?php echo pods_v( 'base-url', $oauth ); ?>">
			<p class="instruction">
				<?php _e( 'Example: "http://example.com/wp-json"', 'pods-deploy' ); ?>
			</p>
		</p>

		<p>
			<label for="consumer-key">
				<?php _e( 'Consumer Key', 'pods-deploy' ); ?>
			</label>
			<input type="text" class="" name="consumer-key" id="consumer-key" value="<?php echo pods_v( 'consumer-key', $oauth ); ?>">
		<p class="instruction">
			<?php _e( 'Your unique consumer key from remote site.', 'pods-deploy' ); ?>
		</p>
		</p>
		<p>
			<label for="consumer-secret">
				<?php _e( 'Consumer Secret', 'pods-deploy' ); ?>
			</label>
			<input type="text" class="" name="consumer-secret" id="consumer-secret" value="<?php echo pods_v( 'consumer-secret', $oauth ); ?>">
		</p>
		<p class="instruction">
			<?php _e( 'Your unique consumer secret key from remote site.', 'pods-deploy' ); ?>
		</p>
		<p class="submit">
			<input type="submit" class="button button-primary" name="pods-deploy-submit" value="Deploy">
		</p>
	</form>
</div>
