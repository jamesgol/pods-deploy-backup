
<div class="wrap pods-deploy-admin">
	<form action="?page=pods-deploy" method="post">

		<div id="icon-tools" class="icon32"><br></div>
		<h2>Pods Deploy</h2>
		<h5>
			<?php _e( 'Click the authorize button above and then enter the verification code below.', 'pods-deploy' ); ?>
		</h5>
		<?php echo wp_nonce_field(); ?>
		<p>
			<label for="oauth-verifier">
				<?php _e( 'Verification Code', 'pods-deploy' ); ?>
			</label>
			<input type="text" class="" name="oauth-verifier" id="oauth-verifier" value="">
			<p class="instruction">
				<?php _e( 'Enter the oauth verification code from above.', 'pods-deploy' ); ?>
			</p>

		</p>

		<p class="submit">
			<input type="submit" class="button button-primary" name="pods-deploy-submit" value="Deploy">
		</p>
	</form>
</div>
