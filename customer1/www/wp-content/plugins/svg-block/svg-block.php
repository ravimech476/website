<?php
/**
 * Plugin Name:       SVG Block
 * Description:       Display an SVG image as a block, which can be used for displaying images, icons, dividers, buttons
 * Requires at least: 6.5
 * Requires PHP:      7.1
 * Version:           1.1.25
 * Author:            Phi Phan
 * Author URI:        https://boldblocks.net
 * Plugin URI:        https://boldblocks.net?utm_source=SVG+Block&utm_campaign=visit+site&utm_medium=link&utm_content=Plugin+URI
 * License:           GPL-3.0
 *
 * @package   SVGBlock
 * @copyright Copyright(c) 2022, Phi Phan
 */

namespace SVGBlock;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define constants.
define( 'SVG_BLOCK_ROOT', __FILE__ );
define( 'SVG_BLOCK_PATH', trailingslashit( plugin_dir_path( SVG_BLOCK_ROOT ) ) );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function svg_block_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', __NAMESPACE__ . '\\svg_block_block_init' );

// Load icons library.
require_once __DIR__ . '/includes/icon-library.php';

/**
 * Render the block on the server.
 *
 * @param string   $block_content
 * @param array    $block
 * @param WP_Block $block_instance
 * @return string
 */
function svg_block_render_block( $block_content, $block, $block_instance ) {
	$attrs = $block['attrs'] ?? [];
	if ( ! ( $attrs['linkToPost'] ?? false ) && ! ( $attrs['title'] ?? false ) && ! ( $attrs['description'] ?? false ) ) {
		return $block_content;
	}

	$block_reader = new \WP_HTML_Tag_Processor( $block_content );
	if ( $attrs['linkToPost'] ?? false ) {
		if ( isset( $block_instance->context['postId'] ) ) {
			// Get post_id from the context first.
			$post_id = $block_instance->context['postId'];
		} else {
			// Fallback to the current post id.
			$post_id = get_queried_object_id();
		}

		$post_link = get_permalink( $post_id );
		if ( $post_link ) {
			if ( $block_reader->next_tag( 'a' ) ) {
				$block_reader->set_attribute( 'href', $post_link );
			}
		}
	}

	// Back compat, do nothing if there is an unique id.
	if ( ! ( $attrs['blockId'] ?? false ) ) {
		if ( $block_reader->next_tag( 'svg' ) ) {
			$id = \uniqid();
			$block_reader->set_bookmark( 'svg' );
			$block_reader->set_attribute( 'id', "svg_block_{$id}" );
			$block_reader->set_attribute( 'role', 'img' );

			if ( $attrs['title'] ?? false ) {
				$block_reader->set_attribute( 'aria-labelledby', "svg_block_{$id}_title" );
				if ( $block_reader->next_tag( 'title' ) ) {
					$block_reader->set_attribute( 'id', "svg_block_{$id}_title" );

					$block_reader->seek( 'svg' );
				}
			}

			if ( $attrs['description'] ?? false ) {
				$block_reader->set_attribute( 'aria-describedby', "svg_block_{$id}_desc" );
				if ( $block_reader->next_tag( 'desc' ) ) {
					$block_reader->set_attribute( 'id', "svg_block_{$id}_desc" );
				}
			}

			$block_reader->release_bookmark( 'svg' );
		}
	}

	return $block_reader->get_updated_html();
}
add_action( 'render_block_boldblocks/svg-block', __NAMESPACE__ . '\\svg_block_render_block', 10, 3 );
