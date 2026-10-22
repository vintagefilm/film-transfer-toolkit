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
							array( 'name' => 'format', 'label' => __( 'Film format', 'film-transfer-toolkit' ), 'default_note' => __( 'The default film format (Calculator look settings)', 'film-transfer-toolkit' ), 'setting' => 'calc:default_format', 'type' => 'select', 'default' => '', 'options' => $fmt8, 'help' => __( 'Film format selected when the page opens. Visitors can still change it.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps', 'label' => __( 'Starting frame rate (fps)', 'film-transfer-toolkit' ), 'default_note' => __( 'The default frame rate (Calculator look settings)', 'film-transfer-toolkit' ), 'setting' => 'calc:default_fps', 'type' => 'text', 'default' => '', 'example' => '18', 'help' => __( 'Frame rate selected when the page opens, for example 16, 18, 24 or 25.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'theme', 'label' => __( 'Colour scheme', 'film-transfer-toolkit' ), 'default_note' => __( 'The colour scheme (Calculator look settings)', 'film-transfer-toolkit' ), 'setting' => 'calc:theme', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), 'site' => __( 'Site theme', 'film-transfer-toolkit' ), 'dark' => __( 'Dark', 'film-transfer-toolkit' ), 'light' => __( 'Light', 'film-transfer-toolkit' ) ), 'help' => __( 'Site theme (matches every FTT tool), or a dark or light panel.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'accent', 'label' => __( 'Accent colour', 'film-transfer-toolkit' ), 'default_note' => __( 'The accent colour (Calculator look settings), or the built-in colour', 'film-transfer-toolkit' ), 'setting' => 'calc:accent', 'type' => 'color', 'default' => '', 'example' => '#c0392b', 'help' => __( 'Accent colour as a hex code, for example #c0392b. Leave empty for the built-in colour.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'heading_font', 'label' => __( 'Heading font', 'film-transfer-toolkit' ), 'default_note' => __( 'The heading font (Calculator look settings)', 'film-transfer-toolkit' ), 'setting' => 'calc:heading_font', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), 'preset' => __( 'Built-in display font', 'film-transfer-toolkit' ), 'yeseva' => 'Yeseva One', 'theme' => __( 'Your theme font', 'film-transfer-toolkit' ), 'plain' => __( 'Plain system font', 'film-transfer-toolkit' ) ), 'help' => __( 'Font used for the calculator heading.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'meters', 'label' => __( 'Show Length (meters)', 'film-transfer-toolkit' ), 'default_note' => __( 'The Fields setting (Calculator look)', 'film-transfer-toolkit' ), 'setting' => 'calc:show_meters', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the Length (meters) field.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'frames', 'label' => __( 'Show Frames', 'film-transfer-toolkit' ), 'default_note' => __( 'The Fields setting (Calculator look)', 'film-transfer-toolkit' ), 'setting' => 'calc:show_frames', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the Frames field (frame count).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps_picker', 'label' => __( 'Show frame rate picker', 'film-transfer-toolkit' ), 'default_note' => __( 'The Fields setting (Calculator look)', 'film-transfer-toolkit' ), 'setting' => 'calc:show_fps', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the Frame rate (fps) picker. When hidden, the calculator uses the fps attribute or the default frame rate.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'speed', 'label' => __( 'Show film speed (ft/min)', 'film-transfer-toolkit' ), 'default_note' => __( 'The Fields setting (Calculator look)', 'film-transfer-toolkit' ), 'setting' => 'calc:show_speed', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the film speed readout (feet per minute) under the result.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'seconds', 'label' => __( 'Show total seconds', 'film-transfer-toolkit' ), 'default_note' => __( 'The Fields setting (Calculator look)', 'film-transfer-toolkit' ), 'setting' => 'calc:show_seconds', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the total seconds readout under the result.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'extras', 'label' => __( 'Show speed and seconds (older option)', 'film-transfer-toolkit' ), 'default_note' => __( 'The Fields setting (Calculator look)', 'film-transfer-toolkit' ), 'setting' => 'calc:show_extras', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Older shortcut for speed and seconds together. speed="" and seconds="" win when both are given.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'switch', 'label' => __( 'Show the direction switch', 'film-transfer-toolkit' ), 'default_note' => __( 'The Calculator look setting', 'film-transfer-toolkit' ), 'setting' => 'calc:show_switch', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the button that swaps between "length to time" and "time to length".', 'film-transfer-toolkit' ) ),
							array( 'name' => 'table', 'label' => __( 'Show the format reference table', 'film-transfer-toolkit' ), 'default_note' => __( 'The Calculator look setting', 'film-transfer-toolkit' ), 'setting' => 'calc:show_table', 'type' => 'select', 'default' => '', 'options' => self::yn(), 'help' => __( 'Show the format reference table (frames per foot, common frame rates, feet per minute).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'title', 'label' => __( 'Heading', 'film-transfer-toolkit' ), 'default_note' => __( '"Film Calculator"', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'How long is my film?', 'help' => __( 'Heading shown above the calculator.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_calculator]'                                 => __( 'Uses the defaults from FTT > Settings > Film calculator.', 'film-transfer-toolkit' ),
							'[ftt_calculator format="s8" fps="18"]'            => __( 'Opens on Super 8 at 18 frames per second.', 'film-transfer-toolkit' ),
							'[ftt_calculator theme="light" accent="#c0392b" table="yes"]' => __( 'Light scheme, red accent, with the reel table.', 'film-transfer-toolkit' ),
							'[ftt_calculator meters="no" frames="no" seconds="no"]' => __( 'Feet, running time and film speed only.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'The page shows [film_calculator] as plain text.', 'film-transfer-toolkit' ) => __( 'Make sure the Film calculator module is switched on under FTT > Tools. If the standalone Film Calculator plugin is active it handles [film_calculator] itself; [ftt_calculator] always uses the toolkit.', 'film-transfer-toolkit' ),
					__( 'Which shortcode should I use?', 'film-transfer-toolkit' ) => __( 'Use [ftt_calculator] or the "Film calculator" block. [film_calculator] is kept so pages built with the older standalone plugin keep working.', 'film-transfer-toolkit' ),
					__( 'The colours clash with my theme.', 'film-transfer-toolkit' ) => __( 'Try theme="light" and set an accent colour that matches your site, or change the defaults once under Calculator look.', 'film-transfer-toolkit' ),
					__( 'Can I hide the meters, frames, frame rate or seconds fields?', 'film-transfer-toolkit' ) => __( 'Yes. Untick them under Calculator look > Fields for the whole site, or use meters="no", frames="no", fps_picker="no", speed="no" or seconds="no" (block: Fields panel) on one page.', 'film-transfer-toolkit' ),
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
							array( 'name' => 'format', 'label' => __( 'Film format', 'film-transfer-toolkit' ), 'type' => 'select', 'default' => 's8', 'options' => array( '8' => '8mm', 's8' => 'Super 8', '16' => '16mm', '35' => '35mm' ), 'help' => __( 'Film format selected when the page opens.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps', 'label' => __( 'Frame rate (fps)', 'film-transfer-toolkit' ), 'default_note' => __( 'The usual speed for the chosen format', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => '18', 'help' => __( 'Frame rate used for the running time. Empty means the usual rate for the chosen format.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'title', 'label' => __( 'Heading', 'film-transfer-toolkit' ), 'default_note' => __( '"How much film do I have?"', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'How much film do I have?', 'help' => __( 'Heading shown above the estimator.', 'film-transfer-toolkit' ) ),
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
				'summary' => __( 'Shows your prices from FTT > Settings > Prices as a tidy table. Change a price once and every page that shows the table (and the estimate builder) updates.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_price_table',
						'block'     => 'ftt/price-table',
						'label'     => __( 'Price table', 'film-transfer-toolkit' ),
						'page'      => array( 'title' => __( 'Film Transfer Prices', 'film-transfer-toolkit' ), 'slug' => 'film-transfer-prices' ),
						'atts'      => array(
							array( 'name' => 'service', 'label' => __( 'Service ID', 'film-transfer-toolkit' ), 'default_note' => __( 'All services', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'hd', 'help' => __( 'Show only one service, using its ID from the Prices page (for example hd or 4k). Empty shows every service.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'addons', 'label' => __( 'Show add-ons', 'film-transfer-toolkit' ), 'type' => 'select', 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'film-transfer-toolkit' ), 'no' => __( 'No', 'film-transfer-toolkit' ) ), 'help' => __( 'Show the add-ons (sound, wet-gate cleaning, repairs, colour correction, USB drive and so on).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fees', 'label' => __( 'Show fees', 'film-transfer-toolkit' ), 'type' => 'select', 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'film-transfer-toolkit' ), 'no' => __( 'No', 'film-transfer-toolkit' ) ), 'help' => __( 'Show order fees and minimums.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_price_table]'                         => __( 'All services, add-ons and fees.', 'film-transfer-toolkit' ),
							'[ftt_price_table service="hd" fees="no"]'  => __( 'Only the HD service, without the fees section.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'There is a yellow "EXAMPLE" banner above the table.', 'film-transfer-toolkit' ) => __( 'That shows until you enter your own prices and mark the table Final on the Prices page. It stops visitors mistaking sample prices for real ones.', 'film-transfer-toolkit' ),
					__( 'Where do I find a service ID?', 'film-transfer-toolkit' ) => __( 'On FTT > Settings > Prices, in the ID column of the Services table.', 'film-transfer-toolkit' ),
					__( 'How do I add, rename, reorder or remove a service?', 'film-transfer-toolkit' ) => __( 'On FTT > Settings > Prices, in the Services table. Change the Name to rename (the ID never changes, so rates and old quote requests keep working), use the arrows to reorder, Remove to delete (its rates go too), and Add service for a new one. Each service can have an optional description that customers see. A service is offered for a format once it has a rate in the Rates table. New sites start with HD 1080p scan, 4K scan, 4K output and 1080p output; sites upgraded from 1.5.1 get 4K output and 1080p output added at the end with no rates, so no price changes until you add them.', 'film-transfer-toolkit' ),
					__( 'What is a "per custom unit" add-on?', 'film-transfer-toolkit' ) => __( 'An add-on priced per something you name, for example USB drive, DVD copy or hour. Pick "per custom unit" and type the unit name. Customers enter how many in the estimate builder and the estimate reads "3 × USB drive @ $15.00". Quantities are whole numbers from 1 to 999; it applies to the whole order.', 'film-transfer-toolkit' ),
					__( 'Can customers see a description of an add-on?', 'film-transfer-toolkit' ) => __( 'Yes. Every add-on and service has an optional Description on the Prices page. It shows as small helper text in the estimate builder and the price table. It is plain text (no HTML).', 'film-transfer-toolkit' ),
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
							array( 'name' => 'title', 'label' => __( 'Heading', 'film-transfer-toolkit' ), 'default_note' => __( '"Build your film transfer estimate"', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'Price my film', 'help' => __( 'Heading shown above the estimate builder.', 'film-transfer-toolkit' ) ),
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
					__( 'Where do I add providers?', 'film-transfer-toolkit' ) => __( 'Under FTT > Quote providers (or your own listing post type, set on the RFQ requests settings page). Each provider needs an email address.', 'film-transfer-toolkit' ),
					__( 'I put the pages at other addresses.', 'film-transfer-toolkit' ) => __( 'That is fine. Choose the pages on the RFQ requests settings page (the setup wizard does this for you) so the emailed links point to the right place.', 'film-transfer-toolkit' ),
				),
			),
			'clips'      => array(
				'title'   => __( 'Clip selector', 'film-transfer-toolkit' ),
				'icon'    => 'editor-video',
				'image'   => 'clips.webp',
				'summary' => __( 'Placed under any video player (a video block, an uploaded video, a YouTube or Vimeo embed, FluentPlayer or FV Player), it lets visitors mark In and Out points and build a list of clips priced from your clip rate card. With FluentCart active the clips go to the cart; without it visitors send the list to you as a clip request.', 'film-transfer-toolkit' ),
				'embeds'  => array(
					array(
						'shortcode' => 'ftt_clip_selector',
						'aliases'   => array( 'fw_clip_cart' ),
						'block'     => 'ftt/clip-selector',
						'label'     => __( 'Clip selector', 'film-transfer-toolkit' ),
						'page'      => null,
						'atts'      => array(
							array( 'name' => 'heading', 'label' => __( 'Heading', 'film-transfer-toolkit' ), 'default_note' => __( 'The heading from the Clip selector settings', 'film-transfer-toolkit' ), 'setting' => 'clip:heading', 'type' => 'text', 'default' => '', 'example' => 'Select Stock Footage', 'help' => __( 'Title shown above the clip selector, in the site\'s heading font. Empty uses the Clip selector settings (default "Select Stock Footage"; a blank setting hides it). heading="none" hides it on this selector.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'player', 'label' => __( 'Video player', 'film-transfer-toolkit' ), 'type' => 'select', 'default' => 'auto', 'options' => array( 'auto' => __( 'Find it automatically', 'film-transfer-toolkit' ), 'html5' => __( 'Video block / uploaded video', 'film-transfer-toolkit' ), 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'fluent' => 'FluentPlayer', 'fv' => 'FV Player' ), 'help' => __( 'Which kind of player the selector works with. Automatic picks the nearest player above the selector (or the first on the page).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'player_selector', 'label' => __( 'Player CSS selector', 'film-transfer-toolkit' ), 'default_note' => __( 'The nearest video player above the selector (or the first on the page)', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => '#interview video', 'help' => __( 'Attach to one particular player: a CSS selector such as #interview (an HTML anchor set on the video block) or .my-player iframe.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'clip_id', 'label' => __( 'Clip ID', 'film-transfer-toolkit' ), 'default_note' => __( 'The product\'s clip ID, otherwise the page slug in capitals', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'FIRE-0001', 'help' => __( 'Your reference for the footage (letters, numbers, dot, dash, underscore). Empty uses the page slug.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'title', 'label' => __( 'Footage title', 'film-transfer-toolkit' ), 'default_note' => __( 'The product title, otherwise the page title', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'Fire engines, 1950s', 'help' => __( 'Clip title used for clip names. Empty uses the product or page title.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'fps', 'label' => __( 'Timecode frame rate', 'film-transfer-toolkit' ), 'default_note' => __( 'The product\'s frame rate, otherwise the default fps (Clip selector settings)', 'film-transfer-toolkit' ), 'setting' => 'clip:default_fps', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the product or settings frame rate', 'film-transfer-toolkit' ), '23.976' => '23.976', '24' => '24', '25' => '25', '29.97df' => '29.97 drop-frame', '29.97ndf' => '29.97 non-drop', '30' => '30' ), 'help' => __( 'Timecode frame rate of the video.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'product_id', 'label' => __( 'FluentCart product ID', 'film-transfer-toolkit' ), 'default_note' => __( 'The default clip product (Clip selector settings); with no product the selector sends a request instead of adding to a cart', 'film-transfer-toolkit' ), 'setting' => 'clip:default_product_id', 'type' => 'number', 'default' => '', 'example' => '123', 'help' => __( 'FluentCart only: the product the clips are added under. Empty uses the default clip product from the Clip selector settings; with neither (or without FluentCart) visitors send a clip request instead.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_clip_selector]'                                     => __( 'Put it under any video. Clips are priced from the rate card and sent as a request (or to the cart when FluentCart and a clip product are set up).', 'film-transfer-toolkit' ),
							'[ftt_clip_selector player_selector="#interview" clip_id="INT-01"]' => __( 'Attach to the video whose HTML anchor is "interview" when a page has several players.', 'film-transfer-toolkit' ),
							'[ftt_clip_selector product_id="123" clip_id="FIRE-0001"]' => __( 'FluentCart: add the clips to the cart under product 123.', 'film-transfer-toolkit' ),
							'[ftt_clip_selector heading="Choose your clip"]'          => __( 'Show your own heading above the selector; heading="none" hides it.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'How is a clip\'s length rounded?', 'film-transfer-toolkit' ) => __( 'Clips are billed in whole seconds. FTT > Settings > Clip selector > Advanced > "Rounding of clip length": up (default; a 30.03 s clip bills 31 s), down (in the customer\'s favour; 30 s) or nearest. The minimum billed seconds and minimum charge still apply, and the on-page estimate uses the same rule as the cart. Developers: rate card key "rounding" (filter ftt/clip/rate_card) and filter ftt/clip/billable_seconds.', 'film-transfer-toolkit' ),
					__( 'How do I go back to my In or Out point?', 'film-transfer-toolkit' ) => __( 'Use "Jump to In" or "Jump to Out" at the top of the clip editor (or Shift+I / Shift+O). The video moves to that frame and pauses. Both are ordinary buttons, so Tab to them and press Enter or Space.', 'film-transfer-toolkit' ),
					__( 'Can I change or hide the "Select Stock Footage" heading?', 'film-transfer-toolkit' ) => __( 'Yes. FTT > Settings > Clip selector > Heading sets it for every selector; leave it blank to hide it. For one selector use heading="..." in the shortcode or the Heading box in the block sidebar; heading="none" hides it there.', 'film-transfer-toolkit' ),
					__( 'Which video players work?', 'film-transfer-toolkit' ) => __( 'The core Video block and any uploaded video (HTML5), YouTube and Vimeo embeds (including the Embed block), FluentPlayer and FV Player. YouTube and Vimeo are controlled through their own player messages, so the video must allow embedding.', 'film-transfer-toolkit' ),
					__( 'Do I need FluentCart?', 'film-transfer-toolkit' ) => __( 'No. Without FluentCart (or without a clip product) the button reads "Send clip request": visitors enter their name and email and the list is saved under Clip requests, with an email to you when mail is switched to Send.', 'film-transfer-toolkit' ),
					__( 'The module shows "Waiting".', 'film-transfer-toolkit' ) => __( 'The older FW Clip Cart plugin is active and still handles clips. Deactivate it when you are ready to switch.', 'film-transfer-toolkit' ),
					__( 'The timecode is off by a few frames.', 'film-transfer-toolkit' ) => __( 'Check the frame rate (the fps attribute, the product, or the Clip selector settings) matches the video. YouTube and Vimeo report time less precisely than an uploaded video, so trim with the frame buttons.', 'film-transfer-toolkit' ),
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
							array( 'name' => 'title', 'label' => __( 'Heading', 'film-transfer-toolkit' ), 'default_note' => __( '"How much storage will my transfer need?"', 'film-transfer-toolkit' ), 'type' => 'text', 'default' => '', 'example' => 'How big will my files be?', 'help' => __( 'Heading shown above the calculator.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'headroom', 'label' => __( 'Drive headroom (%)', 'film-transfer-toolkit' ), 'type' => 'number', 'default' => '20', 'example' => '25', 'help' => __( 'Extra space, in percent, added before suggesting a drive size (0 to 100).', 'film-transfer-toolkit' ) ),
							array( 'name' => 'format', 'label' => __( 'Starting film format', 'film-transfer-toolkit' ), 'default_note' => __( 'The starting film format (Storage calculator settings)', 'film-transfer-toolkit' ), 'setting' => 'storage:default_format', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the settings default', 'film-transfer-toolkit' ), '8' => '8mm', 's8' => 'Super 8', '16' => '16mm', '35-4' => '35mm 4-perf', '35-3' => '35mm 3-perf', '35-2' => '35mm 2-perf' ), 'help' => __( 'Film format in the first reel row when the page opens.', 'film-transfer-toolkit' ) ),
							array( 'name' => 'preset', 'label' => __( 'Starting deliverable', 'film-transfer-toolkit' ), 'default_note' => __( 'The default deliverables (Storage calculator settings)', 'film-transfer-toolkit' ), 'setting' => 'storage:deliverables', 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'Use the settings default deliverables', 'film-transfer-toolkit' ), 'prores-hq-uhd' => 'ProRes 422 HQ, UHD 4K', 'prores-hq-hd' => 'ProRes 422 HQ, HD', 'prores-422-uhd' => 'ProRes 422, UHD 4K', 'h264' => 'H.264', 'youtube-4k' => 'YouTube 4K upload', 'dpx-2k' => 'DPX 2K 10-bit', 'tiff-4k' => 'TIFF 4K 16-bit' ), 'help' => __( 'Start with this one file format instead of the default deliverables list from the Storage calculator settings.', 'film-transfer-toolkit' ) ),
						),
						'examples'  => array(
							'[ftt_storage_calculator]'                              => __( 'Starts with your default film format and deliverables (as shipped: 16mm, ProRes 422 HQ UHD and H.264 16 Mbps, both at the film frame rate with PCM audio) and 20% headroom.', 'film-transfer-toolkit' ),
							'[ftt_storage_calculator preset="dpx-2k" headroom="30"]' => __( 'Starts with a 2K DPX scan and 30% headroom.', 'film-transfer-toolkit' ),
						),
					),
				),
				'faq'     => array(
					__( 'Why are there two sizes (GB and GiB)?', 'film-transfer-toolkit' ) => __( 'Drive makers use decimal units (1 TB = 1,000 GB). Windows and some apps use binary units (1 TiB = 1,024 GiB), so a "1 TB" drive shows as about 931 GB on a PC. The calculator shows both.', 'film-transfer-toolkit' ),
					__( 'Can I change what the calculator starts with?', 'film-transfer-toolkit' ) => __( 'Yes. Under FTT > Storage calculator (or the Storage step of the setup wizard) choose the film format and up to four deliverables every storage calculator starts with.', 'film-transfer-toolkit' ),
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

	/* ---------------------------------------------------------- option docs (1.4.0)
	 * Everything below is derived from tools(), so the Help tables, the "All options" page and every block's
	 * sidebar always list the same options with the same defaults. */

	/** Human label for an attribute (falls back to the attribute name). */
	public static function att_label( $a ) {
		return isset( $a['label'] ) && '' !== $a['label'] ? (string) $a['label'] : (string) $a['name'];
	}

	/** Allowed values as [ value => label ] (select), or an empty array for free text / numbers / colours. */
	public static function att_values( $a ) {
		$out = array();
		foreach ( $a['options'] ?? array() as $v => $l ) {
			if ( '' !== (string) $v ) {
				$out[ (string) $v ] = (string) $l;
			}
		}
		return $out;
	}

	/** One-line description of the allowed values, e.g. "8, s8, 16" or "A number". */
	public static function att_values_text( $a ) {
		$vals = self::att_values( $a );
		if ( $vals ) {
			return implode( ', ', array_keys( $vals ) );
		}
		switch ( $a['type'] ) {
			case 'number':
				return __( 'A number', 'film-transfer-toolkit' );
			case 'color':
				return __( 'A hex colour such as #c0392b', 'film-transfer-toolkit' );
			default:
				return isset( $a['example'] ) && is_numeric( $a['example'] ) ? __( 'A number', 'film-transfer-toolkit' ) : __( 'Any text', 'film-transfer-toolkit' );
		}
	}

	/** The value a site setting currently supplies when the attribute is left empty ('' if none). */
	public static function current_value( $a ) {
		if ( empty( $a['setting'] ) ) {
			return '';
		}
		list( $src, $key ) = explode( ':', $a['setting'], 2 );
		$v = null;
		if ( 'calc' === $src && function_exists( 'ftt_calc_get_options' ) ) {
			$o = ftt_calc_get_options();
			$v = $o[ $key ] ?? null;
			if ( 0 === strpos( $key, 'show_' ) ) {
				return $v ? __( 'Yes', 'film-transfer-toolkit' ) : __( 'No', 'film-transfer-toolkit' );
			}
			if ( 'accent' === $key && '' === (string) $v ) {
				return __( 'built-in colour', 'film-transfer-toolkit' );
			}
		} elseif ( 'clip' === $src && class_exists( 'FTT_Clip_Settings' ) ) {
			$o = FTT_Clip_Settings::get();
			$v = $o[ $key ] ?? null;
			if ( 'default_product_id' === $key ) {
				$v = (int) $v;
				return $v ? '#' . $v . ' ' . get_the_title( $v ) : __( 'none (request mode)', 'film-transfer-toolkit' );
			}
		} elseif ( 'storage' === $src && function_exists( 'ftt_storage_get_options' ) ) {
			$o = ftt_storage_get_options();
			if ( 'deliverables' === $key ) {
				return implode( ' + ', array_map( 'ftt_storage_deliverable_label', $o['deliverables'] ) );
			}
			$v = $o[ $key ] ?? null;
		}
		if ( null === $v || '' === (string) $v ) {
			return '';
		}
		$vals = self::att_values( $a );
		return isset( $vals[ (string) $v ] ) ? $vals[ (string) $v ] . ' (' . $v . ')' : (string) $v;
	}

	/** What happens when the attribute is left out: the fixed default, or the setting it comes from and its current value. */
	public static function default_text( $a ) {
		if ( '' !== (string) $a['default'] ) {
			$vals = self::att_values( $a );
			$d    = (string) $a['default'];
			return isset( $vals[ $d ] ) && $vals[ $d ] !== $d ? $d . ' (' . $vals[ $d ] . ')' : $d;
		}
		$note = isset( $a['default_note'] ) ? (string) $a['default_note'] : __( 'none', 'film-transfer-toolkit' );
		$cur  = self::current_value( $a );
		/* translators: 1: where the default comes from, 2: its current value */
		return '' !== $cur ? sprintf( __( '%1$s, currently %2$s', 'film-transfer-toolkit' ), $note, $cur ) : $note;
	}

	/** Copy-ready shortcode listing every option at its default (empty = use the default). */
	public static function full_example( $e ) {
		$parts = array( $e['shortcode'] );
		foreach ( $e['atts'] as $a ) {
			$parts[] = $a['name'] . '="' . str_replace( '"', '', (string) $a['default'] ) . '"';
		}
		return '[' . implode( ' ', $parts ) . ']';
	}

	/** Copy-ready block markup with the same options (only non-empty example values are filled in). */
	public static function block_example( $e ) {
		$attrs = array();
		foreach ( $e['atts'] as $a ) {
			if ( '' !== (string) $a['default'] ) {
				$attrs[ $a['name'] ] = (string) $a['default'];
			}
		}
		return '<!-- wp:' . $e['block'] . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' ) . ' /-->';
	}
}
