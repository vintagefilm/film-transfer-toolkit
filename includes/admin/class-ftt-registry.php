<?php
/**
 * One description of every toolkit tool. The setup wizard, Help pages, blocks and help tabs all read from here,
 * so a shortcode attribute is documented in exactly one place.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Registry {

	/** Yes/no choices used by several attributes. */
	private static function yn() {
		return array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), 'yes' => __( 'Yes', 'film-transfer-toolkit' ), 'no' => __( 'No', 'film-transfer-toolkit' ) );
	}

	/**
	 * Tools keyed by module key. Each tool has one or more "embeds" (a shortcode with a matching block).
	 * Attribute types: text, number, select, color.
	 */
	public static function tools() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$fmt8 = array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), '8' => '8mm', 's8' => 'Super 8', '16' => '16mm', '35-4' => '35mm 4-perf', '35-3' => '35mm 3-perf', '35-2' => '35mm 2-perf' );
		$cache = array(
			'calculator' => array(
				'title'   => __( 'Film calculator', 'film-transfer-toolkit' ),
				'icon'    => 'calculator',
				'image'   => 'calculator.webp',
				'summary' => __( 'Converts between feet, frames and running time for 8mm, Super 8, 16mm and 35mm film at any frame rate. Visitors type a length and see the running time (or the other way round).', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_calculator',
						'aliases'   => array( 'film_calculator' ),
						'block'     => 'ftt/calculator',
						'label'     => __( 'Film calculator', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Film Calculator', 'film-transfer-toolkit' ), 'slug' => 'film-calculator' ),
						'atts'      => array(
							array( 'name' => 'format', 'type' => 'select', 'default' => '', 'options' => $fmt8, 'help' => __( 'Film format selected when the page opens. Visitors can still change it.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps', 'type' => 'text', 'default' => '', 'example' => '18', 'help' => __( 'Frame rate selected when the page opens, for example 16, 18, 24 or 25.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'theme', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), 'dark' => __( 'Dark', 'film-transfer-toolkit' ), 'light' => __( 'Light', 'film-transfer-toolkit' ) ), 'help' => __( 'Dark or light colour scheme.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'accent', 'type' => 'color', 'default' => '', 'example' => '#c0392b', 'help' => __( 'Accent colour as a hex code, for example #c0392b. Leave empty for the built-in colour.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'heading_font', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), 'preset' => __( 'Built-in display font', 'film-transfer-toolkit' ), 'yeseva' => 'Yeseva One', 'theme' => __( 'Your theme font', 'film-transfer-toolkit' ), 'plain' => __( 'Plain system font', 'film-transfer-toolkit' ) ), 'help' => __( 'Font used for the calculator heading.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'extras', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the extra figures under the result (film speed in feet per minute and total seconds).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'switch', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the button that swaps between "length to time" and "time to length".', 'film-transfer-toolkit' ) ),
							array( 'name' => 'table', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the format reference table (frames per foot, common frame rates, feet per minute).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'title', 'type' => 'text', 'default' => '', 'example' => 'How long is my film?', 'help' => __( 'Heading shown above the calculator.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_calculator]'                                 => __( 'Uses the defaults from Film Transfer Toolkit > Calculator look.', 'film-transfer-toolkit' ),
							'[ftt_calculator format="s8" fps="18"]'            => __( 'Opens on Super 8 at 18 frames per second.', 'film-transfer-toolkit' ),
							'[ftt_calculator theme="light" accent="#c0392b" table="yes"]' => __( 'Light scheme, red accent, with the reel table.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'The page shows [film_calculator] as plain text.', 'film-transfer-toolkit' ) => __( 'Make sure the Film calculator module is switched on under Film Transfer Toolkit > Settings. If the standalone Film Calculator plugin is active it handles [film_calculator] itself; [ftt_calculator] always uses the toolkit.', 'film-transfer-toolkit' ),
					__( 'Which shortcode should I use?', 'film-transfer-toolkit' ) => __( 'Use [ftt_calculator] or the "Film calculator" block. [film_calculator] is kept so pages built with the older standalone plugin keep working.', 'film-transfer-toolkit' ),
					__( 'The colours clash with my theme.', 'film-transfer-toolkit' ) => __( 'Try theme="light" and set an accent colour that matches your site, or change the defaults once under Calculator look.', 'film-transfer-toolkit' ),
				),
			),
			'reels'      => array(
				'title'   => __( 'Reel size estimator', 'film-transfer-toolkit' ),
				'icon'    => 'image-rotate',
				'image'   => 'reels.webp',
				'summary' => __( 'Helps customers who do not know how much film they have. They pick a reel size or measure the film on the reel, and see the approximate footage and running time.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_reel_estimator',
						'block'     => 'ftt/reel-estimator',
						'label'     => __( 'Reel size estimator', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'How Much Film Do I Have?', 'film-transfer-toolkit' ), 'slug' => 'reel-size-estimator' ),
						'atts'      => array(
							array( 'name' => 'format', 'type' => 'select', 'default' => 's8', 'options' => array( '8' => '8mm', 's8' => 'Super 8', '16' => '16mm', '35' => '35mm' ), 'help' => __( 'Film format selected when the page opens.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps', 'type' => 'text', 'default' => '', 'example' => '18', 'help' => __( 'Frame rate used for the running time. Empty means the usual rate for the chosen format.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'title', 'type' => 'text', 'default' => '', 'example' => 'How much film do I have?', 'help' => __( 'Heading shown above the estimator.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_reel_estimator]'                        => __( 'Opens on Super 8.', 'film-transfer-toolkit' ),
							'[ftt_reel_estimator format="16" fps="24"]'   => __( 'Opens on 16mm at 24 frames per second.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'How accurate is the diameter estimate?', 'film-transfer-toolkit' ) => __( 'It is a guide, usually within about ±10%. Film thickness varies by stock and age, so the tool says "about".', 'film-transfer-toolkit' ),
					__( 'Can I put it on the same page as the estimate builder?', 'film-transfer-toolkit' ) => __( 'Yes. Any number of tools can share a page.', 'film-transfer-toolkit' ),
				),
			),
			'pricing'    => array(
				'title'   => __( 'Price table', 'film-transfer-toolkit' ),
				'icon'    => 'money-alt',
				'image'   => 'prices.webp',
				'summary' => __( 'Shows your prices from Film Transfer Toolkit > Prices as a tidy table. Change a price once and every page that shows the table (and the estimate builder) updates.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_price_table',
						'block'     => 'ftt/price-table',
						'label'     => __( 'Price table', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Film Transfer Prices', 'film-transfer-toolkit' ), 'slug' => 'film-transfer-prices' ),
						'atts'      => array(
							array( 'name' => 'service', 'type' => 'text', 'default' => '', 'example' => 'hd', 'help' => __( 'Show only one service, using its ID from the Prices page (for example hd or 4k). Empty shows every service.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'addons', 'type' => 'select', 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'film-transfer-toolkit' ), 'no' => __( 'No', 'film-transfer-toolkit' ) ), 'help' => __( 'Show the add-ons (sound, wet-gate cleaning, repairs, colour correction, USB drive and so on).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fees', 'type' => 'select', 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'film-transfer-toolkit' ), 'no' => __( 'No', 'film-transfer-toolkit' ) ), 'help' => __( 'Show order fees and minimums.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_price_table]'                         => __( 'All services, add-ons and fees.', 'film-transfer-toolkit' ),
							'[ftt_price_table service="hd" fees="no"]'  => __( 'Only the HD service, without the fees section.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'There is a yellow "EXAMPLE" banner above the table.', 'film-transfer-toolkit' ) => __( 'That shows until you enter your own prices and mark the table Final on the Prices page. It stops visitors mistaking sample prices for real ones.', 'film-transfer-toolkit' ),
					__( 'Where do I find a service ID?', 'film-transfer-toolkit' ) => __( 'On Film Transfer Toolkit > Prices, in the ID column of the Services table.', 'film-transfer-toolkit' ),
				),
			),
			'estimate'   => array(
				'title'   => __( 'Estimate builder', 'film-transfer-toolkit' ),
				'icon'    => 'list-view',
				'image'   => 'estimate.webp',
				'summary' => __( 'Customers add a line for each group of reels, pick services and add-ons, and see a running estimate from your price table. They can send it to you as a quote request; requests are saved privately under Quote requests. No payment is taken.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_estimate',
						'block'     => 'ftt/estimate',
						'label'     => __( 'Estimate builder', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Get an Estimate', 'film-transfer-toolkit' ), 'slug' => 'film-transfer-estimate' ),
						'atts'      => array(
							array( 'name' => 'title', 'type' => 'text', 'default' => '', 'example' => 'Price my film', 'help' => __( 'Heading shown above the estimate builder.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_estimate]'                      => __( 'The full estimate builder with the quote request form.', 'film-transfer-toolkit' ),
							'[ftt_estimate title="Price my film"]' => __( 'Same, with your own heading.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'Where do quote requests go?', 'film-transfer-toolkit' ) => __( 'They are saved under Quote requests in the admin menu. An email is only sent if you entered a notify address and switched mail to "Send" under Settings; until then emails are only written to the Mail log.', 'film-transfer-toolkit' ),
					__( 'Can I show the storage calculator next to it?', 'film-transfer-toolkit' ) => __( 'Yes. Put both on the same page and the storage calculator offers a "Use my estimate" button that copies the reels across.', 'film-transfer-toolkit' ),
				),
			),
			'rfq'        => array(
				'title'   => __( 'Quote comparison (RFQ)', 'film-transfer-toolkit' ),
				'icon'    => 'groups',
				'image'   => 'rfq.webp',
				'summary' => __( 'Lets customers ask several transfer providers for a quote at once and compare the answers on a private page. It uses four pieces, each on its own page: the provider picker, the request form, the provider reply page and the customer comparison page.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_rfq_providers',
						'block'     => 'ftt/rfq-providers',
						'label'     => __( 'RFQ: provider picker', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Compare Film Transfer Providers', 'film-transfer-toolkit' ), 'slug' => 'listings', 'option' => 'providers_page' ),
						'atts'      => array(),
						'examples'  => array( '[ftt_rfq_providers]' => __( 'Lists your providers with tick boxes and a "Request quotes" button.', 'film-transfer-toolkit' ) ),
					),
					array(
						'shortcode' => 'ftt_rfq_form',
						'block'     => 'ftt/rfq-form',
						'label'     => __( 'RFQ: request form', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Request Quotes', 'film-transfer-toolkit' ), 'slug' => 'request-quotes', 'option' => 'form_page' ),
						'atts'      => array(),
						'examples'  => array( '[ftt_rfq_form]' => __( 'The customer details and project form. The customer confirms by email before anything is sent.', 'film-transfer-toolkit' ) ),
					),
					array(
						'shortcode' => 'ftt_rfq_reply',
						'block'     => 'ftt/rfq-reply',
						'label'     => __( 'RFQ: provider reply', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Quote Reply', 'film-transfer-toolkit' ), 'slug' => 'rfq-reply', 'option' => 'reply_page' ),
						'atts'      => array(),
						'examples'  => array( '[ftt_rfq_reply]' => __( 'Where providers type their quote, reached from the private link in their email.', 'film-transfer-toolkit' ) ),
					),
					array(
						'shortcode' => 'ftt_rfq_compare',
						'block'     => 'ftt/rfq-compare',
						'label'     => __( 'RFQ: customer comparison', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Your Quotes', 'film-transfer-toolkit' ), 'slug' => 'rfq-compare', 'option' => 'compare_page' ),
						'atts'      => array(),
						'examples'  => array( '[ftt_rfq_compare]' => __( 'The customer\'s private side-by-side comparison, reached from the link in their email.', 'film-transfer-toolkit' ) ),
					),
				),
				'faq'     => array(
					__( 'Do the reply and comparison pages show up in search engines?', 'film-transfer-toolkit' ) => __( 'No. The toolkit marks the form, reply and comparison pages noindex automatically, and the private links contain random keys.', 'film-transfer-toolkit' ),
					__( 'Where do I add providers?', 'film-transfer-toolkit' ) => __( 'Under Film Transfer Toolkit > Quote providers (or your own listing post type, set on the RFQ requests settings page). Each provider needs an email address.', 'film-transfer-toolkit' ),
					__( 'I put the pages at other addresses.', 'film-transfer-toolkit' ) => __( 'That is fine. Choose the pages on the RFQ requests settings page (the setup wizard does this for you) so the emailed links point to the right place.', 'film-transfer-toolkit' ),
				),
			),
			'clips'      => array(
				'title'   => __( 'Clip selector', 'film-transfer-toolkit' ),
				'icon'    => 'editor-video',
				'image'   => 'clips.webp',
				'summary' => __( 'Placed under a video player on a FluentCart product page, it lets buyers mark In and Out points on stock footage and add each clip to the cart, priced from your clip rate card. Needs the FluentCart plugin.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_clip_selector',
						'aliases'   => array( 'fw_clip_cart' ),
						'block'     => 'ftt/clip-selector',
						'label'     => __( 'Clip selector', 'film-transfer-toolkit' ),
						'page'      => null,
						'atts'      => array(
							array( 'name' => 'product_id', 'type' => 'number', 'default' => '', 'example' => '123', 'help' => __( 'The FluentCart product ID the clips are added under. Required.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'clip_id', 'type' => 'text', 'default' => '', 'example' => 'FIRE-0001', 'help' => __( 'Your reference for the footage, shown in the cart.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the product\'s frame rate', 'film-transfer-toolkit' ), '23.976' => '23.976', '24' => '24', '25' => '25', '29.97df' => '29.97 drop-frame', '29.97ndf' => '29.97 non-drop', '30' => '30' ), 'help' => __( 'Timecode frame rate. Normally leave it empty and set the rate on the product.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'player', 'type' => 'select', 'default' => 'auto', 'options' => array( 'auto' => __( 'Find it automatically', 'film-transfer-toolkit' ), 'fluent' => 'FluentPlayer', 'fv' => 'FV Player' ), 'help' => __( 'Which video player sits above the selector.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'player_selector', 'type' => 'text', 'default' => '', 'example' => '#my-player video', 'help' => __( 'Advanced: a CSS selector for the player if automatic detection picks the wrong one.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'title', 'type' => 'text', 'default' => '', 'example' => 'Fire engines, 1950s', 'help' => __( 'Clip title shown in the cart. Empty uses the product name.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_clip_selector product_id="123" clip_id="FIRE-0001"]' => __( 'Place it directly under the video player on the product page.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'The selector says the cart is not available.', 'film-transfer-toolkit' ) => __( 'FluentCart must be installed and active, and product_id must be a real FluentCart product.', 'film-transfer-toolkit' ),
					__( 'The module shows "Waiting".', 'film-transfer-toolkit' ) => __( 'The older FW Clip Cart plugin is active and still handles clips. Deactivate it when you are ready to switch.', 'film-transfer-toolkit' ),
					__( 'The timecode is off by a few frames.', 'film-transfer-toolkit' ) => __( 'Check the frame rate on the product (or the fps attribute) matches the video.', 'film-transfer-toolkit' ),
				),
			),
			'storage'    => array(
				'title'   => __( 'Storage calculator', 'film-transfer-toolkit' ),
				'icon'    => 'database',
				'image'   => 'storage.webp',
				'summary' => __( 'Works out how much disk space a transfer needs for each file format (ProRes, H.264/H.265, YouTube uploads, DPX/TIFF image sequences, uncompressed video), per reel and in total, and suggests a drive size with room to spare.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_storage_calculator',
						'aliases'   => array( 'ftt_storage' ),
						'block'     => 'ftt/storage-calculator',
						'label'     => __( 'Storage calculator', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Film Storage Calculator', 'film-transfer-toolkit' ), 'slug' => 'film-storage-calculator' ),
						'atts'      => array(
							array( 'name' => 'title', 'type' => 'text', 'default' => '', 'example' => 'How big will my files be?', 'help' => __( 'Heading shown above the calculator.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'headroom', 'type' => 'number', 'default' => '20', 'example' => '25', 'help' => __( 'Extra space, in percent, added before suggesting a drive size (0 to 100).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'preset', 'type' => 'select', 'default' => 'prores-hq-hd', 'options' => array( 'prores-hq-hd' => 'ProRes 422 HQ, HD', 'prores-422-uhd' => 'ProRes 422, UHD 4K', 'h264' => 'H.264', 'youtube-4k' => 'YouTube 4K upload', 'dpx-2k' => 'DPX 2K 10-bit', 'tiff-4k' => 'TIFF 4K 16-bit' ), 'help' => __( 'File format in the first deliverable row when the page opens.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_storage_calculator]'                              => __( 'Starts with ProRes 422 HQ HD and 20% headroom.', 'film-transfer-toolkit' ),
							'[ftt_storage_calculator preset="dpx-2k" headroom="30"]' => __( 'Starts with a 2K DPX scan and 30% headroom.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'Why are there two sizes (GB and GiB)?', 'film-transfer-toolkit' ) => __( 'Drive makers use decimal units (1 TB = 1,000 GB). Windows and some apps use binary units (1 TiB = 1,024 GiB), so a "1 TB" drive shows as about 931 GB on a PC. The calculator shows both.', 'film-transfer-toolkit' ),
					__( 'Where do the data rates come from?', 'film-transfer-toolkit' ) => __( 'ProRes rates are from Apple\'s ProRes White Paper; YouTube rates from YouTube Help; DPX and TIFF sizes from the file format specifications. The full list with sources is in the plugin\'s docs/datarate-presets.md.', 'film-transfer-toolkit' ),
				),
			),
		);
		return $cache;
	}

	/** Flat list of embeds keyed by shortcode tag, each with its module key. */
	public static function embeds() {
		$out = array();
		foreach ( self::tools() as $key => $t ) {
			foreach ( $t['embeds'] as $e ) {
				$e['module']             = $key;
				$e['icon']               = $t['icon'];
				$e['summary']            = $t['summary'];
				$out[ $e['shortcode'] ] = $e;
			}
		}
		return $out;
	}

	/** Block markup for an embed with no attributes (used for new pages). */
	public static function block_markup( $embed ) {
		return '<!-- wp:' . $embed['block'] . ' /-->';
	}

	/**
	 * Pages (any status except trash) that already contain an embed, as shortcode (or alias) or block.
	 *
	 * @return WP_Post[]
	 */
	public static function find_pages( $embed, $limit = 10 ) {
		global $wpdb;
		$likes = array();
		foreach ( array_merge( array( $embed['shortcode'] ), $embed['aliases'] ?? array() ) as $tag ) {
			$likes[] = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
		}
		$likes[] = '%' . $wpdb->esc_like( '<!-- wp:' . $embed['block'] . ' ' ) . '%';
		$likes[] = '%' . $wpdb->esc_like( '<!-- wp:' . $embed['block'] . ' /-->' ) . '%';
		$where   = implode( ' OR ', array_fill( 0, count( $likes ), 'post_content LIKE %s' ) );
		$sql     = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash','auto-draft','inherit') AND ( $where ) ORDER BY ( post_status = 'publish' ) DESC, ID ASC LIMIT %d";
		$args    = array_merge( $likes, array( (int) $limit ) );
		$ids     = $wpdb->get_col( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders built above; read-only lookup.
		$out     = array();
		foreach ( $ids as $id ) {
			$p = get_post( (int) $id );
			// A [tag must be followed by ] or whitespace: [ftt_storage must not match [ftt_storage_calculator twice, etc.
			if ( $p && self::content_has( $p->post_content, $embed ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/** True when the content really contains the embed (exact tag or block name). */
	public static function content_has( $content, $embed ) {
		foreach ( array_merge( array( $embed['shortcode'] ), $embed['aliases'] ?? array() ) as $tag ) {
			if ( preg_match( '/\[' . preg_quote( $tag, '/' ) . '[\s\]\/]/', $content ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/<!-- wp:' . preg_quote( $embed['block'], '/' ) . '[\s]/', $content );
	}
}
