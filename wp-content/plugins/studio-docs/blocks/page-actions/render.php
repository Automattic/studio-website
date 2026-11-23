<?php
/**
 * Header or rail controls for copying or opening the current page's Markdown.
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
use function WP_Docs\Render\page_actions_menu;

defined( 'WPINC' ) || die();

$wp_docs_doc = current_doc( $block );
if ( ! $wp_docs_doc ) {
	return;
}

$wp_docs_variant      = 'rail' === ( $attributes['variant'] ?? 'header' ) ? 'rail' : 'header';
$wp_docs_adjacent     = 'header' === $wp_docs_variant ? adjacent( $wp_docs_doc ) : array();
$wp_docs_markdown_url = wp_make_link_relative( wp_docs_markdown_url( $wp_docs_doc ) );
$wp_docs_menu_id      = wp_unique_id( 'studio-docs-page-actions-menu-' );
$wp_docs_wrapper      = get_block_wrapper_attributes(
	array(
		'class'                  => 'is-' . $wp_docs_variant,
		'data-docs-page-actions' => '',
		'data-markdown-url'      => $wp_docs_markdown_url,
		'data-chat-prompt'       => __( 'Read {url} so I can ask you questions about this page.', 'studio-docs' ),
		'data-copied-label'      => __( 'Copied', 'studio-docs' ),
		'data-failed-label'      => __( 'Copy failed', 'studio-docs' ),
	)
);
?>
<div <?php echo $wp_docs_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( 'rail' === $wp_docs_variant ) : ?>
		<button type="button" class="studio-docs-page-actions__item studio-docs-page-actions__copy" data-docs-copy-page><?php echo icon( 'copy', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span data-docs-copy-label><?php esc_html_e( 'Copy as Markdown', 'studio-docs' ); ?></span></button>
		<div class="studio-docs-page-actions__menu">
			<button type="button" class="studio-docs-page-actions__item studio-docs-page-actions__toggle" data-docs-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $wp_docs_menu_id ); ?>"><?php echo icon( 'external-link', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Open in chat', 'studio-docs' ); ?></span><?php echo icon( 'chevron-down', 14, 'studio-docs-page-actions__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php echo page_actions_menu( $wp_docs_menu_id, $wp_docs_markdown_url, $wp_docs_variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php else : ?>
		<div class="studio-docs-page-actions__split">
			<button type="button" class="studio-docs-page-actions__item studio-docs-page-actions__copy" data-docs-copy-page><?php echo icon( 'copy', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span data-docs-copy-label><?php esc_html_e( 'Copy page', 'studio-docs' ); ?></span></button>
			<div class="studio-docs-page-actions__menu">
				<button type="button" class="studio-docs-page-actions__item studio-docs-page-actions__toggle" data-docs-menu-toggle aria-label="<?php esc_attr_e( 'More copy options', 'studio-docs' ); ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $wp_docs_menu_id ); ?>"><?php echo icon( 'chevron-down', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<?php echo page_actions_menu( $wp_docs_menu_id, $wp_docs_markdown_url, $wp_docs_variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<div class="studio-docs-page-actions__navigation-group">
			<?php if ( $wp_docs_adjacent['prev'] ) : ?>
				<a class="studio-docs-page-actions__item studio-docs-page-actions__navigation" href="<?php echo esc_url( node_href( $wp_docs_adjacent['prev'] ) ); ?>" rel="prev" aria-label="<?php /* translators: %s: previous page title. */ echo esc_attr( sprintf( __( 'Previous page: %s', 'studio-docs' ), $wp_docs_adjacent['prev']['heading'] ) ); ?>"><?php echo icon( 'arrow-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php endif; ?>
			<?php if ( $wp_docs_adjacent['next'] ) : ?>
				<a class="studio-docs-page-actions__item studio-docs-page-actions__navigation" href="<?php echo esc_url( node_href( $wp_docs_adjacent['next'] ) ); ?>" rel="next" aria-label="<?php /* translators: %s: next page title. */ echo esc_attr( sprintf( __( 'Next page: %s', 'studio-docs' ), $wp_docs_adjacent['next']['heading'] ) ); ?>"><?php echo icon( 'arrow-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<span class="screen-reader-text" role="status" aria-live="polite" data-docs-copy-status></span>
</div>
