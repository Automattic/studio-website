<?php
/**
 * "On this page": the H2 and H3 headings of the page. In the rail: a title and the list. Collapsible:
 * a `<details>` above the article, shown only when the rail is hidden, with the mount the page actions
 * move into there (kept even without headings, so the actions always have a place).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\current_doc;
use function WP_Docs\Render\icon;
use function WP_Docs\Render\toc_items;
use function WP_Docs\Render\toc_list;

defined( 'WPINC' ) || die();

$wp_docs_doc   = current_doc( $block );
$wp_docs_items = $wp_docs_doc ? toc_items( $wp_docs_doc ) : array();

if ( ! empty( $attributes['collapsible'] ) ) {
	if ( ! $wp_docs_items ) {
		?>
		<div <?php echo get_block_wrapper_attributes( array( 'class' => 'is-collapsible is-empty' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><div class="studio-docs-toc__actions" data-docs-page-actions-mount></div></div>
		<?php
		return;
	}
	?>
	<details <?php echo get_block_wrapper_attributes( array( 'class' => 'is-collapsible' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<summary class="studio-docs-toc__summary"><span><?php esc_html_e( 'On this page', 'studio-docs' ); ?></span><?php echo icon( 'chevron-down', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
		<?php echo toc_list( $wp_docs_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="studio-docs-toc__actions" data-docs-page-actions-mount></div>
	</details>
	<?php
	return;
}

if ( ! $wp_docs_items ) {
	return;
}
?>
<nav <?php echo get_block_wrapper_attributes( array( 'aria-label' => __( 'On this page', 'studio-docs' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="studio-docs-toc__title"><?php esc_html_e( 'On this page', 'studio-docs' ); ?></p>
	<?php echo toc_list( $wp_docs_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</nav>
