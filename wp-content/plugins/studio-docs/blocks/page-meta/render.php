<?php
/**
 * The end of a docs page: when it was last updated in the Studio repository (the `updated` field the
 * staging script reads from the file's last commit) and the link to edit it on GitHub.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\current_doc;
use function WP_Docs\Render\icon;

defined( 'WPINC' ) || die();

$wp_docs_doc = current_doc( $block );
if ( ! $wp_docs_doc ) {
	return;
}
$wp_docs_meta    = wp_docs_get_page_meta( $wp_docs_doc );
$wp_docs_updated = ! empty( $attributes['showUpdated'] ) ? (string) $wp_docs_meta['updated'] : '';
$wp_docs_edit    = ! empty( $attributes['showEditLink'] ) ? (string) $wp_docs_meta['edit_url'] : '';
if ( '' === $wp_docs_updated && '' === $wp_docs_edit ) {
	return;
}
$wp_docs_time = strtotime( $wp_docs_updated . ' 00:00:00 UTC' );
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( '' !== $wp_docs_edit ) : ?>
		<a class="studio-docs-page-meta__edit" href="<?php echo esc_url( $wp_docs_edit ); ?>"><?php echo icon( 'pencil', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Edit this page on GitHub', 'studio-docs' ); ?></a>
	<?php endif; ?>
	<?php if ( '' !== $wp_docs_updated && $wp_docs_time ) : ?>
		<span class="studio-docs-page-meta__updated"><?php esc_html_e( 'Updated', 'studio-docs' ); ?> <time datetime="<?php echo esc_attr( $wp_docs_updated ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ), $wp_docs_time, new DateTimeZone( 'UTC' ) ) ); ?></time></span>
	<?php endif; ?>
</div>
