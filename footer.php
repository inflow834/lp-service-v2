<?php
/**
 * フッター
 */

if ( is_singular( 'service' ) ) {
	$lp_operator_id = (int) get_post_meta( get_queried_object_id(), '_lp_operator_id', true );
	if ( $lp_operator_id && 'operator_info' === get_post_type( $lp_operator_id ) && 'publish' === get_post_status( $lp_operator_id ) ) {
		?>
		<div class="lp-service-footer">
			<a class="lp-service-footer__operator-link" href="<?php echo esc_url( get_permalink( $lp_operator_id ) ); ?>">運営者情報</a>
		</div>
		<?php
	}
}
?>
	<?php wp_footer(); ?>
</body>
</html>
