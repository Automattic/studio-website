<?php
/**
 * Previous and next pages, named by their sidebar heading (the section name for a page with children).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\adjacent;
use function WP_Docs\Render\current_doc;
use function WP_Docs\Render\icon;
use function WP_Docs\Render\node_href;

defined( 'WPINC' ) || die();

$wp_docs_doc = current_doc( $block );
if ( ! $wp_docs_doc ) {
	return;
}
$wp_docs_adjacent = adjacent( $wp_docs_doc );
if ( ! $wp_docs_adjacent['prev'] && ! $wp_docs_adjacent['next'] ) {
	return;
}
?>
<nav <?php echo get_block_wrapper_attributes( array( 'aria-label' => __( 'Pagination', 'studio-docs' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $wp_docs_adjacent['prev'] ) : ?>
		<a class="studio-docs-pagination__link is-previous" href="<?php echo esc_url( node_href( $wp_docs_adjacent['prev'] ) ); ?>" rel="prev">
			<?php echo icon( 'arrow-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="studio-docs-pagination__text"><span class="studio-docs-pagination__label"><?php esc_html_e( 'Previous', 'studio-docs' ); ?></span><span class="studio-docs-pagination__title"><?php echo esc_html( $wp_docs_adjacent['prev']['heading'] ); ?></span></span>
		</a>
	<?php endif; ?>
	<?php if ( $wp_docs_adjacent['next'] ) : ?>
		<a class="studio-docs-pagination__link is-next" href="<?php echo esc_url( node_href( $wp_docs_adjacent['next'] ) ); ?>" rel="next">
			<span class="studio-docs-pagination__text"><span class="studio-docs-pagination__label"><?php esc_html_e( 'Next', 'studio-docs' ); ?></span><span class="studio-docs-pagination__title"><?php echo esc_html( $wp_docs_adjacent['next']['heading'] ); ?></span></span>
			<?php echo icon( 'arrow-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	<?php endif; ?>
</nav>
