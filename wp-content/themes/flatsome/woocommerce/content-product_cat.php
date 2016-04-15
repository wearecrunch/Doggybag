<?php
/**
 * The template for displaying product category thumbnails within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product_cat.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you (the theme developer).
 * will need to copy the new files to your theme to maintain compatibility. We try to do this.
 * as little as possible, but it does happen. When this occurs the version of the template file will.
 * be bumped and the readme will list any important changes.
 *
 * @see 	    http://docs.woothemes.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $woocommerce_loop, $flatsome_opt;

// Store loop count we're currently on
if ( empty( $woocommerce_loop['loop'] ) )
	$woocommerce_loop['loop'] = 0;

// Increase loop count
$woocommerce_loop['loop']++;

// set cat style
if(isset($flatsome_opt['cat_style'])){
	if($flatsome_opt['cat_style'] && !isset($style)) $style = $flatsome_opt['cat_style'];
}
if(!isset($style)) $style = "text-badge";

?>



	<div class="small-12    large-4  columns   "  ><div class="column-inner"  >
<div class="ux-box team-member ux-text-boxed  text-center">
	<div class="inner">
		<div class="inner-wrap">
			<div class="ux-box-image">
				<?php do_action( 'woocommerce_before_subcategory_title', $category ); ?>
			</div><!-- .ux-box-image -->
			<div class="ux-box-text ">
				<h4 class="uppercase">
					<?php 	echo $category->name; ?><br/>
					<span class="thin-font"><?php 	echo $category->description; ?></span>
				</h4>
				<div class="show-next">

					<div class="social-icons">
					</div>
				</div>
				<p><a href="<?php 	echo $category->slug; ?>" class="button  primary alt-button" ><?php echo sprintf( __( 'select', 'text_domain' ), 'select' ); ?></a>
				</p>
			</div><!-- .ux-box-text-overlay -->
		</div>
	</div>
</div>
</div></div>