<?php
/**
 * The callout block: the title (the type's name unless the block sets one) above the inner blocks.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    The inner blocks, rendered.
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

defined( 'WPINC' ) || die();

$wp_docs_types = array(
	'note'      => __( 'Note', 'studio-docs' ),
	'tip'       => __( 'Tip', 'studio-docs' ),
	'important' => __( 'Important', 'studio-docs' ),
	'warning'   => __( 'Warning', 'studio-docs' ),
	'caution'   => __( 'Caution', 'studio-docs' ),
);
$wp_docs_type  = isset( $wp_docs_types[ $attributes['type'] ?? '' ] ) ? $attributes['type'] : 'note';
$wp_docs_title = trim( (string) ( $attributes['title'] ?? '' ) );
if ( '' === $wp_docs_title ) {
	$wp_docs_title = $wp_docs_types[ $wp_docs_type ];
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'is-' . $wp_docs_type, 'role' => 'note' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="studio-docs-callout__title"><?php echo wp_kses_post( $wp_docs_title ); ?></p>
	<div class="studio-docs-callout__content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered inner blocks. ?></div>
</div>
