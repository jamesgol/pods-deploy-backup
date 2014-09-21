<form action="?page=pods-deploy" method="post">

	<div id="icon-tools" class="icon32"><br></div>
	<h2>
		<?php _e( sprintf( 'Pods Deploy: %1s', $key_gen_header ),  'pods-deploy' ); ?>
	</h2>

	<p class="submit">
		<input type="submit" class="button button-primary" name="pods-deploy-key-gen-submit" value="<?php echo $key_gen_submit; ?>">
	</p>
</form>

<?php if ( $deploy_active ) : ?>
	<div id="current-keys">
		<p>
			<?php _e( sprintf( 'Public Key: %1s', $remote_public, 'pods-deploy' ) ); ?>
		</p>
		<p>
			<?php _e( sprintf( 'Private Key: %1s', $private_remote, 'pods-deploy' ) ); ?>
		</p>

	</div>
<?php endif; ?>
