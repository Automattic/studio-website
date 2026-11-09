<?php
/**
 * The docs navigation tree.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\current_doc;
use function WP_Docs\Render\nav_tree;

defined( 'WPINC' ) || die();

$wp_docs_doc = current_doc( $block );
?>
<nav <?php echo get_block_wrapper_attributes( array( 'aria-label' => __( 'Docs', 'studio-docs' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo nav_tree( $wp_docs_doc ? $wp_docs_doc->ID : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></nav>
