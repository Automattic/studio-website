<?php
/**
 * The breadcrumb above the title: the page's group, or its parent page when it has one.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (none).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\current_doc;

defined( 'WPINC' ) || die();

$wp_docs_doc    = current_doc( $block );
$wp_docs_crumbs = $wp_docs_doc ? wp_docs_get_breadcrumbs( $wp_docs_doc ) : array();
array_pop( $wp_docs_crumbs ); // The page itself is the title.
if ( ! $wp_docs_crumbs ) {
	return;
}
$wp_docs_crumb = end( $wp_docs_crumbs );
?>
<nav <?php echo get_block_wrapper_attributes( array( 'aria-label' => __( 'Breadcrumb', 'studio-docs' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( '' !== $wp_docs_crumb['url'] ) : ?>
		<a href="<?php echo esc_url( wp_make_link_relative( $wp_docs_crumb['url'] ) ); ?>"><?php echo esc_html( $wp_docs_crumb['title'] ); ?></a>
	<?php else : ?>
		<span><?php echo esc_html( $wp_docs_crumb['title'] ); ?></span>
	<?php endif; ?>
</nav>
