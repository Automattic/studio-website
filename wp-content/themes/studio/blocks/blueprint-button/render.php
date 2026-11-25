<?php
/**
 * Open in WordPress Studio generator. The tabs and the copy buttons are the theme's (studio.js);
 * view.js fills the three panels from the field.
 *
 * @package studio
 */

$formats = array(
	'markdown' => 'Markdown',
	'html'     => 'HTML',
	'url'      => 'URL',
);
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'blueprint-button' ) ); ?>>
	<label class="blueprint-button__label" for="blueprint-button-input">Blueprint</label>
	<textarea id="blueprint-button-input" class="blueprint-button__input" rows="6" spellcheck="false" autocomplete="off" placeholder="https://example.com/blueprint.json, Base64 or JSON" aria-describedby="blueprint-button-error"></textarea>
	<p id="blueprint-button-error" class="blueprint-button__error" role="alert" hidden></p>
	<div class="wp-block-buttons is-style-tabs blueprint-button__tabs">
		<?php foreach ( $formats as $format => $label ) : ?>
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#blueprint-button-<?php echo esc_attr( $format ); ?>"><?php echo esc_html( $label ); ?></a></div>
		<?php endforeach; ?>
	</div>
	<?php foreach ( $formats as $format => $label ) : ?>
		<pre id="blueprint-button-<?php echo esc_attr( $format ); ?>" class="wp-block-code is-wrapped blueprint-button__output"><code data-format="<?php echo esc_attr( $format ); ?>"></code></pre>
	<?php endforeach; ?>
	<p class="blueprint-button__try" hidden><a class="blueprint-button__link" href="#">Try your link</a></p>
</div>
