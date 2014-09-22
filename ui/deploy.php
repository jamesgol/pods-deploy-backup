<div id="pods-meta-box" class="postbox" style="width:100%;">

	<div id="pods-wizard-box">
		<form action="?page=pods-deploy" method="post" class="pods-submittable">
			<div id="pods-wizard-main">
				<div id="pods-wizard-panel-1" class="pods-wizard-panel" style="display: block;">
					<div id="icon-tools" class="icon32"><br></div>
					<h2>
						<?php _e( 'Deploy To Remote Site', 'pods-deploy' ); ?>
					</h2>

					<?php

					$form     = Pods_Form();
					$fields[] = $form::field( '_wpnonce', wp_create_nonce( 'pods-deploy' ), 'hidden' );

					foreach ( $form_fields as $name => $field ) {

						$fields[] = '<li>';
						$fields[] = $form::label(
							$name,
							pods_v( 'label', $field, '' ),
							pods_v( 'help', $field, '' )
						);
						$fields[] = $form::field(
							$name,
							pods_v( 'value', $field ),
							pods_v( 'type', $field, 'text' ),
							pods_v( 'options', $field )
						);
						$fields[] = '</li>';

					}

					echo sprintf( '<ul>%1s</ul>', implode( $fields ) );
					?>
					<p>
						<a href="#toggle" class="button pods-wizard-display-content"
							><?php _e( 'Show items to export', 'pods' ); ?></a>
					</p>
					<?php pods_view( PODS_DEPLOY_DIR . 'ui/pods-wizard.php' ); ?>




					<p class="submit">
						<input type="submit" class="button button-primary" name="pods-deploy-submit" value="Deploy">
					</p>
				</div>
			</div>
		</form>

	</div>
</div>
<script>
	jQuery(function ($) {

		$('.pods-wizard-display-content').on('click', function () {

			if ($(this).text() === '<?php _e( 'Show items to export', 'pods' ); ?>') {
				$(this).text('<?php _e( 'Hide items to export', 'pods' ); ?>');
			} else {
				$(this).text('<?php _e( 'Show items to export', 'pods' ); ?>');
			}
			$('.pods-wizard-option-content').toggle();
		});
	});
</script>


