<?php
/**
 * The template for displaying product content within loops.
 *
 * Override this template by copying it to yourtheme/woocommerce/content-product.php
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version 	2.5.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product, $woocommerce_loop, $flatsome_opt;


// Ensure visibilty
if ( ! $product->is_visible() ) {
	return;
}

// Get avability
$post_id = $post->ID;
$stock_status = get_post_meta($post_id, '_stock_status',true) == 'outofstock';

// run add to cart variation script
if($product->is_type( array( 'variable', 'grouped') )) wp_enqueue_script('wc-add-to-cart-variation');

?>



<div class="small-12    large-4  columns   "  ><div class="column-inner"  >
		<div class="ux-box team-member ux-text-boxed  text-center">
			<div class="inner">
				<div class="inner-wrap">
					<div class="ux-box-image">
						<?php echo get_the_post_thumbnail( $post->ID, 'shop_catalog') ?>
					</div><!-- .ux-box-image -->
					<div class="ux-box-text ">
							<h4 class="uppercase">
							<?php the_title(); ?><br/>
							<span class="thin-font"><?php $price = get_post_meta( get_the_ID(), '_regular_price', true);	echo $price."kr/box"; ?></span>
						</h4>
						<div class="show-next">

							<div class="social-icons">
							</div>
						</div>
						<?php do_action( 'woocommerce_before_shop_loop_item_title' ); ?>
						<p><a href="<?php echo $product->add_to_cart_url() ;?>" class="button  primary alt-button" ><?php echo $product->add_to_cart_text();?></a>
						</p>
					</div>
							<!-- .ux-box-text-overlay -->
				</div>
			</div>
		</div>
	</div></div>