<?php
/**
 * LP(service) シングルテンプレート
 */

get_header();

while ( have_posts() ) :
	the_post();

	$header_data = lp_service_get_header_data( get_the_ID() );
	$design      = lp_service_get_design_template( get_the_ID() );

	get_template_part( 'template-parts/service/design-' . $design, null, array( 'header_data' => $header_data ) );
endwhile;

get_footer();
