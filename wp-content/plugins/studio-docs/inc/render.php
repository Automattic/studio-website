<?php
/**
 * Rendering helpers shared by the blocks: the docs page a block renders for, icons, the code
 * highlights, the table of contents, the sidebar tree, the previous and next pages.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Render;

use WP_Block;
use WP_Post;

defined( 'WPINC' ) || die();

/**
 * The docs page a block renders for: the block's `postId` context (templates pass the queried page,
 * the editor the one being edited), else the queried page, else the docs home (the Site Editor's
 * template preview, where there is no page).
 *
 * @param WP_Block|null $block Block, for its context.
 * @return WP_Post|null
 */
function current_doc( $block = null ) {
	$id = 0;
	if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
		$id = (int) $block->context['postId'];
	}
	if ( ! $id ) {
		$id = \WP_Docs\Agents::current_page_id();
	}
	if ( ! $id ) {
		$post = get_post();
		if ( $post instanceof WP_Post && \WP_Docs\POST_TYPE === $post->post_type ) {
			$id = $post->ID;
		}
	}
	if ( ! $id ) {
		$id = \WP_Docs\Permalinks::post_for_path( '' );
	}
	$post = $id ? get_post( $id ) : null;
	return $post instanceof WP_Post && \WP_Docs\POST_TYPE === $post->post_type ? $post : null;
}

/**
 * Root-relative URL of a tree node (the site is exported as static files: links never carry a host).
 *
 * @param array $node Node.
 * @return string
 */
function node_href( array $node ) {
	return wp_make_link_relative( $node['url'] );
}

/**
 * An inline Solar Linear icon. The artwork comes from the Iconify API; see NOTICE.md.
 *
 * @param string $name  Icon name.
 * @param int    $size  Width and height in pixels.
 * @param string $class Extra classes.
 * @return string
 */
function icon( $name, $size = 16, $class = '' ) {
	$paths = array(
		'arrow-left'      => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 12H4M10 18L4 12L10 6"/>',
		'arrow-right'     => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 12H20M14 18L20 12L14 6"/>',
		'arrow-up'        => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 20L12 4M6 10L12 4L18 10"/>',
		'arrow-up-right'  => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M18 15V6H9"/>',
		'check'           => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M4 12.9L7.14286 16.5L15 7.5"/><path d="M20 7.5625L11.4283 16.5625L11 16"/></g>',
		'chevron-down'    => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9L12 15L5 9"/>',
		'chevron-right'   => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5L15 12L9 19"/>',
		'copy'            => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M12 22H15C17.8284 22 19.2426 22 20.1213 21.1213C21 20.2426 21 18.8284 21 16V11C21 8.17157 21 6.75736 20.1213 5.87868C19.2426 5 17.8284 5 15 5H12C9.17157 5 7.75736 5 6.87868 5.87868C6 6.75736 6 8.17157 6 11V16C6 18.8284 6 20.2426 6.87868 21.1213C7.75736 22 9.17157 22 12 22Z"/><path d="M6 19C4.34315 19 3 17.6569 3 16V10C3 6.22876 3 4.34315 4.17157 3.17157C5.34315 2 7.22876 2 11 2H15C16.6569 2 18 3.34315 18 5"/></g>',
		'external-link'   => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path stroke-linejoin="round" d="M9 15L15 9M15 13.5V9H10.5"/><path d="M12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C22 4.92893 22 7.28595 22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22Z"/></g>',
		'file'            => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M6 14.5H14"/><path d="M6 18H11.5"/><path d="M13 2.2627V5.0003C13 7.35732 13 8.53583 13.7322 9.26806C14.4645 10.0003 15.643 10.0003 18 10.0003H21.58"/><path stroke-linejoin="round" d="M3.17139 3.17157C4.34296 2 6.23851 2 10.0296 2C11.5546 2 12.3173 2.00011 13.0093 2.26562C13.7012 2.53114 14.2651 3.03857 15.3929 4.05365L19.3516 7.61621C20.6558 8.78998 21.3078 9.3774 21.6538 10.1543C21.9998 10.9312 22 11.8079 22 13.5625V14C22 17.7712 22 19.6566 20.8284 20.8281C19.6569 21.9997 17.7712 22 14 22L9.9969 21.9997C6.22761 21.9997 4.34284 21.9997 3.17157 20.8284C2 19.6569 2 17.7712 2 14V9.9982C2 6.22817 2 4.34296 3.17139 3.17157Z"/></g>',
		'menu'            => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M20 7L4 7"/><path d="M20 12L4 12"/><path d="M20 17L4 17"/></g>',
		'pencil'          => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M14.3601 4.07866L15.2869 3.15178C16.8226 1.61607 19.3125 1.61607 20.8482 3.15178C22.3839 4.68748 22.3839 7.17735 20.8482 8.71306L19.9213 9.63993M14.3601 4.07866C14.3601 4.07866 14.4759 6.04828 16.2138 7.78618C17.9517 9.52407 19.9213 9.63993 19.9213 9.63993M19.9213 9.63993L11.4001 18.1612C10.8229 18.7383 10.5344 19.0269 10.2162 19.2751C9.84082 19.5679 9.43469 19.8189 9.00498 20.0237C8.6407 20.1973 8.25352 20.3263 7.47918 20.5844L4.19792 21.6782L3.39584 21.9456C3.01478 22.0726 2.59466 21.9734 2.31063 21.6894C2.0266 21.4053 1.92743 20.9852 2.05445 20.6042L2.32181 19.8021L3.41556 16.5208C3.67368 15.7465 3.80273 15.3593 3.97634 14.995C4.18114 14.5653 4.43213 14.1592 4.7249 13.7838C4.97308 13.4656 5.26166 13.1771 5.83882 12.5999L14.3601 4.07866M4.19792 21.6782L2.32181 19.8021"/>',
		'restart'         => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 8.05026L17.6569 7.34315C14.5327 4.21896 9.46734 4.21896 6.34315 7.34315C3.21895 10.4673 3.21895 15.5327 6.34315 18.6569C9.46734 21.7811 14.5327 21.7811 17.6569 18.6569C19.4737 16.84 20.234 14.3668 19.9377 12.0005M18.364 3.80762V8.05026H14.1213"/>',
		'search'          => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><circle cx="11.5" cy="11.5" r="9.5"/><path d="M18.2168 18.2178L21.9994 22.0004"/></g>',
	);
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"%2$s>%3$s</svg>',
		(int) $size,
		'' !== $class ? ' class="' . esc_attr( $class ) . '"' : '',
		$paths[ $name ] ?? ''
	);
}

/**
 * The shared page-actions dropdown, including destination logos.
 *
 * @param string $menu_id      Unique menu id.
 * @param string $markdown_url Page's Markdown URL.
 * @param string $variant      Header or rail.
 * @return string
 */
function page_actions_menu( $menu_id, $markdown_url, $variant ) {
	$targets = array(
		'v0'      => array( __( 'Open in v0', 'studio-docs' ), '<svg class="studio-docs-page-actions__brand" aria-hidden="true" fill="currentColor" viewBox="0 0 147 70" xmlns="http://www.w3.org/2000/svg"><path d="M56 50.2031V14H70V60.1562C70 65.5928 65.5928 70 60.1562 70C57.5605 70 54.9982 68.9992 53.1562 67.1573L0 14H19.7969L56 50.2031Z"/><path d="M147 56H133V23.9531L100.953 56H133V70H96.6875C85.8144 70 77 61.1856 77 50.3125V14H91V46.1562L123.156 14H91V0H127.312C138.186 0 147 8.81439 147 19.6875V56Z"/></svg>' ),
		'chatgpt' => array( __( 'Open in ChatGPT', 'studio-docs' ), '<svg class="studio-docs-page-actions__brand" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.872zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"/></svg>' ),
		'claude'  => array( __( 'Open in Claude', 'studio-docs' ), '<svg class="studio-docs-page-actions__brand" aria-hidden="true" fill="currentColor" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"><path clip-rule="evenodd" fill-rule="evenodd" d="M2.3545 7.9775L4.7145 6.654L4.7545 6.539L4.7145 6.475H4.6L4.205 6.451L2.856 6.4145L1.6865 6.366L0.5535 6.305L0.268 6.2445L0 5.892L0.0275 5.716L0.2675 5.5555L0.6105 5.5855L1.3705 5.637L2.5095 5.716L3.3355 5.7645L4.56 5.892H4.7545L4.782 5.8135L4.715 5.7645L4.6635 5.716L3.4845 4.918L2.2085 4.074L1.5405 3.588L1.1785 3.3425L0.9965 3.1115L0.9175 2.6075L1.2455 2.2465L1.686 2.2765L1.7985 2.307L2.245 2.65L3.199 3.388L4.4445 4.3045L4.627 4.4565L4.6995 4.405L4.709 4.3685L4.627 4.2315L3.9495 3.0085L3.2265 1.7635L2.9045 1.2475L2.8195 0.938C2.78711 0.819128 2.76965 0.696687 2.7675 0.5735L3.1415 0.067L3.348 0L3.846 0.067L4.056 0.249L4.366 0.956L4.867 2.0705L5.6445 3.5855L5.8725 4.0345L5.994 4.4505L6.0395 4.578H6.1185V4.505L6.1825 3.652L6.301 2.6045L6.416 1.257L6.456 0.877L6.644 0.422L7.0175 0.176L7.3095 0.316L7.5495 0.6585L7.516 0.8805L7.373 1.806L7.0935 3.2575L6.9115 4.2285H7.0175L7.139 4.1075L7.6315 3.4545L8.4575 2.4225L8.8225 2.0125L9.2475 1.5605L9.521 1.345H10.0375L10.4175 1.9095L10.2475 2.4925L9.7155 3.166L9.275 3.737L8.643 4.587L8.248 5.267L8.2845 5.322L8.3785 5.312L9.8065 5.009L10.578 4.869L11.4985 4.7115L11.915 4.9055L11.9605 5.103L11.7965 5.5065L10.812 5.7495L9.6575 5.9805L7.938 6.387L7.917 6.402L7.9415 6.4325L8.716 6.5055L9.047 6.5235H9.858L11.368 6.636L11.763 6.897L12 7.216L11.9605 7.4585L11.353 7.7685L10.533 7.574L8.6185 7.119L7.9625 6.9545H7.8715V7.0095L8.418 7.5435L9.421 8.4485L10.6755 9.6135L10.739 9.9025L10.578 10.13L10.408 10.1055L9.3055 9.277L8.88 8.9035L7.917 8.0935H7.853V8.1785L8.075 8.503L9.2475 10.2635L9.3085 10.8035L9.2235 10.98L8.9195 11.0865L8.5855 11.0255L7.8985 10.063L7.191 8.9795L6.6195 8.008L6.5495 8.048L6.2125 11.675L6.0545 11.86L5.69 12L5.3865 11.7695L5.2255 11.396L5.3865 10.658L5.581 9.696L5.7385 8.931L5.8815 7.981L5.9665 7.665L5.9605 7.644L5.8905 7.653L5.1735 8.6365L4.0835 10.109L3.2205 11.0315L3.0135 11.1135L2.655 10.9285L2.6885 10.5975L2.889 10.303L4.083 8.785L4.803 7.844L5.268 7.301L5.265 7.222H5.2375L2.066 9.28L1.501 9.353L1.2575 9.125L1.288 8.752L1.4035 8.6305L2.3575 7.9745L2.3545 7.9775Z"/></svg>' ),
		't3'      => array( __( 'Open in T3 Chat', 'studio-docs' ), '<svg class="studio-docs-page-actions__brand" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>' ),
		'scira'   => array( __( 'Open in Scira', 'studio-docs' ), '<svg class="studio-docs-page-actions__brand" aria-hidden="true" fill="none" viewBox="0 0 910 934" xmlns="http://www.w3.org/2000/svg"><path d="M647.664 197.775C569.13 189.049 525.5 145.419 516.774 66.8849C508.048 145.419 464.418 189.049 385.884 197.775C464.418 206.501 508.048 250.131 516.774 328.665C525.5 250.131 569.13 206.501 647.664 197.775Z" fill="currentColor" stroke="currentColor" stroke-linejoin="round" stroke-width="8"/><path d="M857.5 508.116C763.259 497.644 710.903 445.288 700.432 351.047C689.961 445.288 637.605 497.644 543.364 508.116C637.605 518.587 689.961 570.943 700.432 665.184C710.903 570.943 763.259 518.587 857.5 508.116Z" stroke="currentColor" stroke-linejoin="round" stroke-width="20"/><path d="M889.949 121.237C831.049 114.692 798.326 81.9698 791.782 23.0692C785.237 81.9698 752.515 114.692 693.614 121.237C752.515 127.781 785.237 160.504 791.782 219.404C798.326 160.504 831.049 127.781 889.949 121.237Z" fill="currentColor" stroke="currentColor" stroke-linejoin="round" stroke-width="8"/><path d="M760.632 764.337C720.719 814.616 669.835 855.1 611.872 882.692C553.91 910.285 490.404 924.255 426.213 923.533C362.022 922.812 298.846 907.419 241.518 878.531C184.19 849.643 134.228 808.026 95.4548 756.863C56.6815 705.7 30.1238 646.346 17.8129 583.343C5.50207 520.339 7.76433 455.354 24.4266 393.359C41.089 331.364 71.7099 274.001 113.947 225.658C156.184 177.315 208.919 139.273 268.117 114.442" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="30"/></svg>' ),
		'cursor'  => array( __( 'Open in Cursor', 'studio-docs' ), '<svg class="studio-docs-page-actions__brand" aria-hidden="true" viewBox="0 0 466.73 532.09" xmlns="http://www.w3.org/2000/svg"><path d="M457.43,125.94L244.42,2.96c-6.84-3.95-15.28-3.95-22.12,0L9.3,125.94c-5.75,3.32-9.3,9.46-9.3,16.11v247.99c0,6.65,3.55,12.79,9.3,16.11l213.01,122.98c6.84,3.95,15.28,3.95,22.12,0l213.01-122.98c5.75-3.32,9.3-9.46,9.3-16.11v-247.99c0-6.65-3.55-12.79-9.3-16.11h-.01ZM444.05,151.99l-205.63,356.16c-1.39,2.4-5.06,1.42-5.06-1.36v-233.21c0-4.66-2.49-8.97-6.53-11.31L24.87,145.67c-2.4-1.39-1.42-5.06,1.36-5.06h411.26c5.84,0,9.49,6.33,6.57,11.39h-.01Z" fill="currentColor"/></svg>' ),
	);
	ob_start();
	?>
	<div id="<?php echo esc_attr( $menu_id ); ?>" class="studio-docs-page-actions__dropdown" role="menu" aria-label="<?php echo esc_attr( 'rail' === $variant ? __( 'Open in chat', 'studio-docs' ) : __( 'Page actions', 'studio-docs' ) ); ?>" hidden>
		<?php if ( 'header' === $variant ) : ?>
			<button type="button" class="studio-docs-page-actions__option" role="menuitem" tabindex="-1" data-docs-copy-page>
				<?php echo icon( 'copy', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e( 'Copy page', 'studio-docs' ); ?></span>
			</button>
			<a class="studio-docs-page-actions__option" href="<?php echo esc_url( $markdown_url ); ?>" role="menuitem" tabindex="-1" target="_blank" rel="noreferrer">
				<?php echo icon( 'file', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e( 'View as Markdown', 'studio-docs' ); ?></span>
			</a>
			<hr role="separator">
		<?php endif; ?>
		<?php foreach ( $targets as $key => $target ) : ?>
			<?php if ( 'rail' === $variant && 'chatgpt' === $key ) : ?>
				<hr role="separator">
			<?php endif; ?>
			<a class="studio-docs-page-actions__option" href="#" data-open-in="<?php echo esc_attr( $key ); ?>" role="menuitem" tabindex="-1" target="_blank" rel="noreferrer">
				<?php echo $target[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $target[0] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * URL of a plugin asset.
 *
 * @param string $path Path under assets/.
 * @return string
 */
function asset( $path ) {
	return plugins_url( 'assets/' . ltrim( $path, '/' ), \WP_Docs\PLUGIN_FILE );
}

/**
 * The display name of a code block language.
 *
 * @param string $lang Fence language.
 * @return string
 */
function language_label( $lang ) {
	$labels = array(
		'astro'        => 'Astro',
		'bash'         => 'Bash',
		'c'            => 'C',
		'c#'           => 'C#',
		'c++'          => 'C++',
		'console'      => 'Console',
		'cpp'          => 'C++',
		'cs'           => 'C#',
		'csharp'       => 'C#',
		'css'          => 'CSS',
		'dart'         => 'Dart',
		'diff'         => 'Diff',
		'docker'       => 'Dockerfile',
		'dockerfile'   => 'Dockerfile',
		'elixir'       => 'Elixir',
		'go'           => 'Go',
		'gql'          => 'GraphQL',
		'graphql'      => 'GraphQL',
		'html'         => 'HTML',
		'http'         => 'HTTP',
		'ini'          => 'INI',
		'java'         => 'Java',
		'javascript'   => 'JavaScript',
		'js'           => 'JavaScript',
		'json'         => 'JSON',
		'jsonc'        => 'JSONC',
		'jsx'          => 'JSX',
		'kotlin'       => 'Kotlin',
		'kt'           => 'Kotlin',
		'lua'          => 'Lua',
		'markdown'     => 'Markdown',
		'md'           => 'Markdown',
		'mdx'          => 'MDX',
		'php'          => 'PHP',
		'plaintext'    => 'Text',
		'powershell'   => 'PowerShell',
		'ps1'          => 'PowerShell',
		'py'           => 'Python',
		'python'       => 'Python',
		'rb'           => 'Ruby',
		'rs'           => 'Rust',
		'ruby'         => 'Ruby',
		'rust'         => 'Rust',
		'scala'        => 'Scala',
		'scss'         => 'SCSS',
		'sh'           => 'Shell',
		'shell'        => 'Shell',
		'shellsession' => 'Shell Session',
		'sql'          => 'SQL',
		'svelte'       => 'Svelte',
		'swift'        => 'Swift',
		'text'         => 'Text',
		'toml'         => 'TOML',
		'ts'           => 'TypeScript',
		'tsx'          => 'TSX',
		'txt'          => 'Text',
		'typescript'   => 'TypeScript',
		'vue'          => 'Vue',
		'xml'          => 'XML',
		'yaml'         => 'YAML',
		'yml'          => 'YAML',
		'zsh'          => 'Zsh',
	);
	$lang = strtolower( (string) $lang );
	return $labels[ $lang ] ?? $lang;
}

/**
 * The icon drawn next to a code block's language label (the block's stylesheet knows these two).
 *
 * @param string $lang Fence language.
 * @return string Empty when there is none.
 */
function language_icon( $lang ) {
	switch ( strtolower( (string) $lang ) ) {
		case 'bash':
		case 'sh':
		case 'shell':
		case 'zsh':
		case 'shellscript':
			return 'gnubash';
		case 'json':
		case 'jsonc':
			return 'json';
	}
	return '';
}

/**
 * The pre-rendered Shiki highlight of a code block, from build/docs/highlights.json (written by
 * scripts/build-docs-manifest.mjs next to the manifest), keyed by language and code.
 *
 * @param string $lang Fence language.
 * @param string $code Code (text, not HTML).
 * @return array|null { style, html }
 */
function highlight( $lang, $code ) {
	static $highlights = null;
	if ( null === $highlights ) {
		$highlights = array();
		$file       = dirname( \WP_Docs\manifest_location() ) . '/highlights.json';
		if ( is_file( $file ) ) {
			$decoded    = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$highlights = is_array( $decoded ) ? $decoded : array();
		}
		/**
		 * Filters the code highlights available to the code block, keyed by sha1( "<lang>\n<code>" ).
		 *
		 * @param array $highlights key => { lang, style, html }.
		 */
		$highlights = (array) apply_filters( 'wp_docs_code_highlights', $highlights );
	}
	$key = sha1( strtolower( (string) $lang ) . "\n" . rtrim( (string) $code, "\n" ) );
	return $highlights[ $key ] ?? null;
}

/**
 * The headings a page's table of contents lists: H2 and H3 with their ids.
 *
 * @param WP_Post $post Post.
 * @return array<int,array{level: int, text: string, anchor: string}>
 */
function toc_items( WP_Post $post ) {
	$items = array();
	foreach ( \WP_Docs\Transforms\Anchors\headings( (string) $post->post_content, array( 2, 3 ) ) as $heading ) {
		if ( '' !== $heading['anchor'] ) {
			$items[] = $heading;
		}
	}
	return $items;
}

/**
 * The list of a table of contents.
 *
 * @param array $items toc_items().
 * @return string
 */
function toc_list( array $items ) {
	$html = '<ul class="studio-docs-toc__list">';
	foreach ( $items as $item ) {
		$html .= sprintf(
			'<li class="is-level-%1$d"><a href="#%2$s">%3$s</a></li>',
			(int) $item['level'],
			esc_attr( $item['anchor'] ),
			esc_html( $item['text'] )
		);
	}
	return $html . '</ul>';
}

/**
 * The pages the search dialog lists before a query is typed: the home and the first pages of the sidebar.
 *
 * @return array[] { href, label }
 */
function popular_pages() {
	$tree    = wp_docs_get_tree();
	$popular = array();
	if ( $tree['home'] && isset( $tree['nodes'][ $tree['home'] ] ) ) {
		$popular[] = $tree['nodes'][ $tree['home'] ];
	}
	foreach ( $tree['order'] as $id ) {
		if ( count( $popular ) >= 6 ) {
			break;
		}
		$popular[] = $tree['nodes'][ $id ];
	}
	return array_map(
		static fn( $node ) => array(
			'href'  => node_href( $node ),
			'label' => html_entity_decode( $node['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		),
		$popular
	);
}

/**
 * The sidebar tree as HTML: a flat list with a heading per group, and a sub-heading for a page with
 * children (listed under it, the page itself first).
 *
 * @param int $current Current post ID.
 * @return string
 */
function nav_tree( $current ) {
	$tree = wp_docs_get_tree();
	$html = '<ul class="studio-docs-nav">';
	if ( $tree['home'] && isset( $tree['nodes'][ $tree['home'] ] ) ) {
		$html .= nav_item( $tree['nodes'][ $tree['home'] ], $current );
	}

	$groups = array();
	foreach ( $tree['sections'] as $id ) {
		$node                        = $tree['nodes'][ $id ];
		$groups[ $node['group'] ]  ??= array(
			'title' => $node['group_title'],
			'items' => array(),
		);
		$groups[ $node['group'] ]['items'][] = $id;
	}
	foreach ( $groups as $group ) {
		$html .= '<li class="studio-docs-nav__group"><p class="studio-docs-nav__heading"><span>' . esc_html( $group['title'] ) . '</span></p>';
		$html .= nav_list( $group['items'], $current, $tree ) . '</li>';
	}
	return $html . '</ul>';
}

/**
 * A list of pages; a page with children becomes a sub-heading with its own list.
 *
 * @param int[] $ids     Post IDs.
 * @param int   $current Current post ID.
 * @param array $tree    The tree.
 * @return string
 */
function nav_list( array $ids, $current, array $tree ) {
	$html = '<ul>';
	foreach ( $ids as $id ) {
		$node = $tree['nodes'][ $id ];
		if ( empty( $tree['children'][ $id ] ) ) {
			$html .= nav_item( $node, $current );
			continue;
		}
		$html .= sprintf(
			'<li class="studio-docs-nav__group"><p class="studio-docs-nav__heading"><a class="studio-docs-nav__heading-link" href="%1$s"><span>%2$s</span></a></p>%3$s</li>',
			esc_url( node_href( $node ) ),
			esc_html( $node['heading'] ),
			nav_list( array_merge( array( $id ), $tree['children'][ $id ] ), $current, array_merge( $tree, array( 'children' => array_diff_key( $tree['children'], array( $id => true ) ) ) ) )
		);
	}
	return $html . '</ul>';
}

/**
 * One sidebar link.
 *
 * @param array $node    Node.
 * @param int   $current Current post ID.
 * @return string
 */
function nav_item( array $node, $current ) {
	return sprintf(
		'<li><a class="studio-docs-nav__link"%1$s href="%2$s">%3$s</a></li>',
		(int) $node['id'] === (int) $current ? ' aria-current="page"' : '',
		esc_url( node_href( $node ) ),
		esc_html( $node['label'] )
	);
}

/**
 * The previous and next pages of the current one, in sidebar order (the home is the first page).
 *
 * @param WP_Post $post Post.
 * @return array{prev: array|null, next: array|null}
 */
function adjacent( WP_Post $post ) {
	$pages  = \WP_Docs\Agents::pages_in_order();
	$result = array(
		'prev' => null,
		'next' => null,
	);
	foreach ( $pages as $i => $node ) {
		if ( (int) $node['id'] === (int) $post->ID ) {
			$result['prev'] = $pages[ $i - 1 ] ?? null;
			$result['next'] = $pages[ $i + 1 ] ?? null;
			break;
		}
	}
	return $result;
}
