<?php
/**
 * Dynamic blocks for every toolkit tool, in a "Film Transfer Toolkit" inserter category.
 * Each block renders through the same function as its shortcode, so blocks and shortcodes always match.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Blocks {
	const CATEGORY = 'film-transfer-toolkit';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 30 );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ), 10, 1 );
	}

	public static function category( $cats ) {
		foreach ( (array) $cats as $c ) {
			if ( isset( $c['slug'] ) && self::CATEGORY === $c['slug'] ) {
				return $cats;
			}
		}
		array_unshift( $cats, array( 'slug' => self::CATEGORY, 'title' => __( 'Film Transfer Toolkit', 'film-transfer-toolkit' ), 'icon' => null ) );
		return $cats;
	}

	/** Embeds whose module is running. */
	public static function active_embeds() {
		$out = array();
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			if ( 'on' === ( FTT_Plugin::$status[ $e['module'] ] ?? '' ) ) {
				$out[ $tag ] = $e;
			}
		}
		return $out;
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$embeds = self::active_embeds();
		if ( ! $embeds ) {
			return;
		}
		wp_register_script( 'ftt-blocks', FTT_URL . 'assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ), FTT_VERSION, true );
		wp_register_style( 'ftt-blocks-editor', FTT_URL . 'assets/css/blocks-editor.css', array( 'dashicons' ), FTT_VERSION );
		$client = array();
		foreach ( $embeds as $tag => $e ) {
			$attrs = array();
			foreach ( $e['atts'] as $a ) {
				$attrs[ $a['name'] ] = array( 'type' => 'string', 'default' => (string) $a['default'] );
			}
			register_block_type(
				$e['block'],
				array(
					'api_version'     => 2,
					'title'           => $e['label'],
					'description'     => $e['summary'],
					'category'        => self::CATEGORY,
					'icon'            => $e['icon'],
					'keywords'        => array( 'film', 'transfer', 'ftt' ),
					'attributes'      => $attrs,
					'supports'        => array( 'html' => false, 'customClassName' => false, 'className' => false ),
					'editor_script'   => 'ftt-blocks',
					'editor_style'    => 'ftt-blocks-editor',
					'render_callback' => function ( $attributes ) use ( $tag ) {
						return FTT_Blocks::render( $tag, $attributes );
					},
				)
			);
			$tools    = FTT_Registry::tools();
			$client[] = array(
				'name'      => $e['block'],
				'shortcode' => $tag,
				'aliases'   => $e['aliases'] ?? array(),
				'title'     => $e['label'],
				'summary'   => $e['summary'],
				'icon'      => $e['icon'],
				'image'     => FTT_Admin::img( $tools[ $e['module'] ]['image'] ),
				'help'      => FTT_Admin::help_url( $e['module'] ),
				'atts'      => array_map(
					function ( $a ) {
						$opts = array();
						foreach ( $a['options'] ?? array() as $v => $l ) {
							$opts[] = array( 'value' => (string) $v, 'label' => (string) $l );
						}
						return array( 'name' => $a['name'], 'type' => $a['type'], 'default' => (string) $a['default'], 'help' => $a['help'], 'options' => $opts, 'placeholder' => $a['example'] ?? '' );
					},
					$e['atts']
				),
			);
		}
		wp_add_inline_script(
			'ftt-blocks',
			'window.FTT_BLOCKS = ' . wp_json_encode(
				array(
					'blocks'  => $client,
					'i18n'    => array(
						'settings' => __( 'Settings', 'film-transfer-toolkit' ),
						'help'     => __( 'Help: options and examples', 'film-transfer-toolkit' ),
						'note'     => __( 'This box stands in for the tool while you edit. Visitors see the working tool; use Preview to try it.', 'film-transfer-toolkit' ),
						'defaults' => __( 'All options at their defaults.', 'film-transfer-toolkit' ),
						'shortcode'=> __( 'Same as the shortcode', 'film-transfer-toolkit' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/** Render a block through its shortcode handler. Only declared attributes are passed; the handler sanitizes them. */
	public static function render( $tag, $attributes ) {
		global $shortcode_tags;
		$embeds = FTT_Registry::embeds();
		if ( empty( $embeds[ $tag ] ) || empty( $shortcode_tags[ $tag ] ) || ! is_callable( $shortcode_tags[ $tag ] ) ) {
			return '';
		}
		$atts = array();
		foreach ( $embeds[ $tag ]['atts'] as $a ) {
			$v = isset( $attributes[ $a['name'] ] ) && is_scalar( $attributes[ $a['name'] ] ) ? trim( (string) $attributes[ $a['name'] ] ) : '';
			if ( '' !== $v ) {
				$atts[ $a['name'] ] = $v;
			}
		}
		return (string) call_user_func( $shortcode_tags[ $tag ], $atts, '', $tag );
	}
}
