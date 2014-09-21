<form action="?page=pods-deploy" method="post">

	<div id="icon-tools" class="icon32"><br></div>
	<h2>
		<?php _e( 'Deploy To Remote Site', 'pods-deploy' ); ?>
	</h2>

	<p>
		<label for="remote-url">
			<?php _e( 'URL To Remote Site API', 'pods-deploy' ); ?>
		</label>
		<input type="text" class="" name="remote-url" id="remote-url" value="">
		<p class="instruction">
			<?php _e( 'For example "http://example.com/wp-json"', 'pods-deploy' ); ?>
		</p>
	</p>

	<p>
		<label for="public-key">
			<?php _e( 'Remote Site Public Key', 'pods-deploy' ); ?>
		</label>
		<input type="text" class="" name="public-key" id="public-key" value="<?php echo $public_local; ?>">
		<p class="instruction">
			<?php _e( 'Public key from remote site.', 'pods-deploy' ); ?>
		</p>
	</p>

	<p>
		<label for="private-key">
			<?php _e( 'Remote Site Private Key', 'pods-deploy' ); ?>
		</label>
		<input type="text" class="" name="public-key" id="private-key" value="<?php echo $private_local; ?>">
		<p class="instruction">
			<?php _e( 'Private key from remote site.', 'pods-deploy' ); ?>
		</p>
	</p>


	<p class="submit">
		<input type="submit" class="button button-primary" name="pods-deploy-submit" value="Deploy">
	</p>
</form>
