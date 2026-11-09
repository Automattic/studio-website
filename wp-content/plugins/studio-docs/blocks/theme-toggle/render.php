<?php
/**
 * The color scheme toggle: a sun in the light scheme, a moon in the dark one.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\icon;

defined( 'WPINC' ) || die();
?>
<button <?php echo get_block_wrapper_attributes( array( 'class' => 'studio-docs-icon-button' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> type="button" aria-label="<?php esc_attr_e( 'Toggle theme', 'studio-docs' ); ?>" title="<?php esc_attr_e( 'Toggle theme', 'studio-docs' ); ?>" data-docs-theme-toggle><?php echo icon( 'sun', 18, 'studio-docs-theme-toggle__light' ) . icon( 'moon', 18, 'studio-docs-theme-toggle__dark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
