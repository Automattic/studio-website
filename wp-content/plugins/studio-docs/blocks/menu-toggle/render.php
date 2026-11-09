<?php
/**
 * The menu button: controls the sidebar drawer (`#studio-docs-sidebar`, the Docs Sidebar block) below
 * the sidebar breakpoint, hidden above it.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\icon;

defined( 'WPINC' ) || die();
?>
<button <?php echo get_block_wrapper_attributes( array( 'class' => 'studio-docs-icon-button' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> type="button" aria-controls="studio-docs-sidebar" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle navigation', 'studio-docs' ); ?>" data-docs-menu-toggle><?php echo icon( 'menu', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
