<?php
/**
 * The search button and dialog. The script loads /docs/search-index.json on first open and searches
 * it in the browser; before a query is typed it lists the popular pages embedded here.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\icon;
use function WP_Docs\Render\popular_pages;

defined( 'WPINC' ) || die();

$wp_docs_placeholder = trim( (string) ( $attributes['placeholder'] ?? '' ) );
if ( '' === $wp_docs_placeholder ) {
	$wp_docs_placeholder = __( 'Search documentation…', 'studio-docs' );
}
$wp_docs_wrapper = get_block_wrapper_attributes(
	array(
		'data-docs-search' => '',
		'data-index'       => wp_make_link_relative( wp_docs_path_url( '' ) ) . 'search-index.json',
	)
);
?>
<div <?php echo $wp_docs_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<button type="button" class="studio-docs-icon-button" aria-label="<?php esc_attr_e( 'Search', 'studio-docs' ); ?>" aria-keyshortcuts="Meta+K Control+K" title="<?php esc_attr_e( 'Search (⌘K)', 'studio-docs' ); ?>" data-docs-search-open><?php echo icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	<dialog class="studio-docs-search__dialog" aria-label="<?php esc_attr_e( 'Search docs', 'studio-docs' ); ?>" data-docs-search-dialog>
		<div class="studio-docs-search__field">
			<?php echo icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="search" role="combobox" aria-autocomplete="list" aria-controls="studio-docs-search-listbox" aria-expanded="false" aria-label="<?php esc_attr_e( 'Search docs', 'studio-docs' ); ?>" autocomplete="off" placeholder="<?php echo esc_attr( $wp_docs_placeholder ); ?>" data-docs-search-input>
			<kbd>Esc</kbd>
		</div>
		<div class="studio-docs-search__body" data-docs-search-body>
			<div class="studio-docs-search__list">
				<div class="studio-docs-search__filters" data-docs-search-filters hidden></div>
				<div class="studio-docs-search__results" id="studio-docs-search-listbox" role="listbox" aria-label="<?php esc_attr_e( 'Search docs', 'studio-docs' ); ?>" data-docs-search-results></div>
				<p class="studio-docs-search__message" data-docs-search-message hidden></p>
			</div>
			<div class="studio-docs-search__preview" data-docs-search-preview></div>
		</div>
		<div class="studio-docs-search__footer">
			<span></span>
			<div class="studio-docs-search__keys">
				<span><kbd>↑</kbd><kbd>↓</kbd><?php esc_html_e( 'navigate', 'studio-docs' ); ?></span>
				<span><kbd>↵</kbd><?php esc_html_e( 'open', 'studio-docs' ); ?></span>
				<span class="studio-docs-search__preview-key"><kbd dir="ltr" data-docs-search-preview-key>⌘J</kbd><?php esc_html_e( 'preview', 'studio-docs' ); ?></span>
			</div>
		</div>
	</dialog>
	<script type="application/json" data-docs-search-popular><?php echo wp_json_encode( popular_pages(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON. ?></script>
</div>
