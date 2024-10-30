<?php if (!is_admin()) : ?>
	<?php
	require('settings.php');
	?>

	<?php if ('open' == $post->comment_status) : ?>

	<a name="comments" id="comments"></a><div id="mccomments"></div>
	<script type="text/javascript">
		/* <![CDATA[ */
		var matchchatPostID = <?php echo get_the_ID(); ?>;
		(function() {
			var mc = document.createElement('script'); mc.type = 'text/javascript';
			mc.async = true;
			mc.src = '//<?php echo $MC_SETTINGS['api_endpoint'] ?>/comments/js/wordpress.js';
			(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(mc);
		})();
		/* ]]> */
	</script>

	<?php else : ?>
		<p style="color:#333;font-size:18px;font-weight:300;margin:30px 0px;margin-left:20px; line-height:20px;">Comments are closed on this article.</p>
	<?php endif; ?>
<?php endif; ?>