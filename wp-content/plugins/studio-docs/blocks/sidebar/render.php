<?php
/**
 * The sidebar: `<aside id="studio-docs-sidebar">` around the inner blocks, and the backdrop that closes
 * the drawer below the sidebar breakpoint.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    The inner blocks, rendered.
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

defined( 'WPINC' ) || die();
?>
<aside <?php echo get_block_wrapper_attributes( array( 'id' => 'studio-docs-sidebar', 'aria-label' => __( 'Primary', 'studio-docs' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-docs-sidebar><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered inner blocks. ?></aside>
<button type="button" class="studio-docs-sidebar-backdrop" aria-label="<?php esc_attr_e( 'Close navigation', 'studio-docs' ); ?>" data-docs-menu-toggle tabindex="-1"></button>
