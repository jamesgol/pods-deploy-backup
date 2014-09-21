<form action="?page=pods-deploy" method="post">

	<div id="icon-tools" class="icon32"><br></div>
	<h2>Deploy To Remote Site</h2>

	<p>
		<label for="remote-url">Remote URL:</label>
		<input type="text" class="" name="remote-url" id="remote-url" value="">
	</p>

	<p>
		<label for="public-key">public-key</label>
		<input type="text" class="" name="public-key" id="public-key" value="<?php echo $public_local; ?>">
	</p>

	<p>
		<label for="private-key">request-token:</label>
		<input type="text" class="" name="public-key" id="private-key" value="<?php echo $private_local; ?>">
	</p>


	<p class="submit">
		<input type="submit" class="button button-primary" name="pods-deploy-submit" value="Deploy">
	</p>
</form>
