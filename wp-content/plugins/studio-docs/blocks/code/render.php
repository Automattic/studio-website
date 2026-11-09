<?php
/**
 * The code block on the front end: the language label, the Shiki highlight pre-rendered at build
 * time (plain lines when there is none) and the copy button.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Saved markup (`<pre><code>…</code></pre>`).
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Docs
 */

use function WP_Docs\Render\highlight;
use function WP_Docs\Render\icon;
use function WP_Docs\Render\language_icon;
use function WP_Docs\Render\language_label;

defined( 'WPINC' ) || die();

$wp_docs_code = '';
if ( preg_match( '#<code[^>]*>(.*)</code>#s', (string) $content, $wp_docs_match ) ) {
	$wp_docs_code = html_entity_decode( $wp_docs_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}
$wp_docs_code = rtrim( $wp_docs_code, "\n" );
$wp_docs_lang = strtolower( (string) ( $attributes['language'] ?? '' ) );

$wp_docs_highlight = highlight( $wp_docs_lang, $wp_docs_code );
if ( $wp_docs_highlight ) {
	$wp_docs_style = (string) $wp_docs_highlight['style'];
	$wp_docs_inner = (string) $wp_docs_highlight['html'];
} else {
	$wp_docs_style = '';
	$wp_docs_inner = implode(
		"\n",
		array_map(
			static fn( $line ) => '<span class="line"><span>' . esc_html( $line ) . '</span></span>',
			explode( "\n", $wp_docs_code )
		)
	);
}

$wp_docs_attrs = array(
	'data-language' => language_label( '' === $wp_docs_lang ? 'plaintext' : $wp_docs_lang ),
);
if ( '' !== $wp_docs_style ) {
	$wp_docs_attrs['style'] = $wp_docs_style;
}
$wp_docs_icon = language_icon( $wp_docs_lang );
if ( '' !== $wp_docs_icon ) {
	$wp_docs_attrs['data-icon'] = $wp_docs_icon;
}
?>
<pre <?php echo get_block_wrapper_attributes( $wp_docs_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><code tabindex="0"><?php echo $wp_docs_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shiki output / escaped above. ?></code><button type="button" class="studio-docs-code__copy" aria-label="<?php esc_attr_e( 'Copy code', 'studio-docs' ); ?>" data-copied-label="<?php esc_attr_e( 'Copied!', 'studio-docs' ); ?>"><?php echo icon( 'copy', 14, 'studio-docs-code__copy-icon' ) . icon( 'check', 14, 'studio-docs-code__check-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button></pre>
