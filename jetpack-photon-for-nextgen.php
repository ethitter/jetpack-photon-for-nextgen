<?php
/*
 * Plugin Name: CDN-Powered NextGEN Galleries with Jetpack Photon
 * Plugin URI: https://ethitter.com/plugins/jetpack-photon-for-nextgen/
 * Description: Apply Jetpack's Photon module to images in NextGEN Galleries. Requires both the <a href="http://wordpress.org/extend/plugins/jetpack/" target="_blank">Jetpack</a> and <a href="http://wordpress.org/extend/plugins/nextgen-gallery/" target="_blank">NextGEN Gallery</a> plugins to be of any use.
 * Author: Erick Hitter
 * Version: 0.2
 * Author URI: https://ethitter.com/
 * License: GPL2+

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Jetpack_Photon_for_NextGEN {
	/**
	 * Class variables
	 */
	// Oh look, a singleton
	private static $__instance = null;

	// NextGEN image settings
	private $src_width = false;
	private $src_height = false;

	private $thumb_width = false;
	private $thumb_height = false;
	private $thumb_transform = 'resize';

	/**
	 * Singleton implementation
	 *
	 * @uses this::setup
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Jetpack_Photon_for_NextGEN' ) ) {
			self::$__instance = new Jetpack_Photon_for_NextGEN;
			self::$__instance->setup();
		}

		return self::$__instance;
	}

	/**
	 * Silence is golden.
	 */
	private function __construct() {}

	/**
	 * Since this plugin relies on Jetpack, defer adding its actions until Jetpack's presence can be reliably determined.
	 *
	 * @uses add_action
	 * @return null
	 */
	private function setup() {
		add_action( 'wp_loaded', array( $this, 'action_wp_loaded' ) );
	}

	/**
	 * Load plugin's actions if Jetpack is present and Photon module is active.
	 *
	 * @uses Jetpack::get_active_modules, is_admin, apply_filters, this::parse_nextgen_options, add_action
	 * @return null
	 */
	public function action_wp_loaded() {
		// Requires Jetpack
		if ( ! function_exists( 'jetpack_photon_url' ) || ! in_array( 'photon', Jetpack::get_active_modules() ) )
			return;

		// Don't mess with the admin, unless someone really wants to
		if ( is_admin() && ! apply_filters( 'jpn_apply_in_admin', false ) )
			return;

		// Populate options from NextGEN
		$this->parse_nextgen_options();

		// Filter image properties
		add_action( 'ngg_get_image', array( $this, 'action_ngg_get_image' ) );
	}

	/**
	 * Parse NextGEN settings relevant to our resizing
	 *
	 * @uses get_option
	 * @return null
	 */
	private function parse_nextgen_options() {
		$options = get_option( 'ngg_options' );

		if ( is_array( $options ) && ! empty( $options ) ) {
			// Full-size image
			if ( array_key_exists( 'imgWidth', $options ) )
				$this->src_width = (int) $options['imgWidth'];
			if ( array_key_exists( 'imgHeight', $options ) )
				$this->src_height = (int) $options['imgHeight'];

			// Thumbnails
			if ( array_key_exists( 'thumbwidth', $options ) )
				$this->thumb_width = (int) $options['thumbwidth'];
			if ( array_key_exists( 'thumbheight', $options ) )
				$this->thumb_height = (int) $options['thumbheight'];

			// Thumbnail resizing type
			if ( array_key_exists( 'thumbfix', $options ) )
				$this->thumb_transform = (bool) $options['thumbfix'] ? 'resize' : 'fit';
		}
	}

	/**
	 * Filter NextGEN image objects to point to Photon.
	 * Dimensions aren't available, so only CDN aspect will be utilized.
	 *
	 * @param object $image
	 * @uses jetpack_photon_url
	 * @return null
	 */
	public function action_ngg_get_image( $image ) {
		// Alias image URLs
		$src = $src_orig = $image->imageURL;
		$thumb = $thumb_orig = $image->thumbURL;

		// Pass fullsize image to Photon
		$src_args = array();
		if ( is_int( $this->src_width ) && is_int( $this->src_height ) )
			$src_args['fit'] = $this->src_width . ',' . $this->src_height;

		$src = jetpack_photon_url( $src, $src_args );

		// Pass thumbnail to Photon
		$thumb_args = array();
		if ( is_int( $this->thumb_width ) && is_int( $this->thumb_height ) )
			$thumb_args[ $this->thumb_transform ] = $this->thumb_width . ',' . $this->thumb_height;

		$thumb = jetpack_photon_url( empty( $thumb_args ) ? $thumb : $src_orig, $thumb_args );

		// Update image URLs
		$image->imageURL = $src;
		$image->thumbURL = $thumb;

		//Update markup
		$properties = array( 'href', 'imageHTML', 'thumbHTML' );

		foreach ( $properties as $property ) {
			$image->{$property} = str_replace( $src_orig, $src, $image->{$property} );
			$image->{$property} = str_replace( $thumb_orig, $thumb, $image->{$property} );
		}
	}
}

Jetpack_Photon_for_NextGEN::instance();