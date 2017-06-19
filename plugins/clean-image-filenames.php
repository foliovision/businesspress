<?php 
/**
 * Original Plugin Name: Clean Image Filenames
 * Description: Filenames with special characters or language accent characters can sometimes be a problem. This plugin takes care of that by cleaning the filenames.
 * Version: 1.1.1
 * Author: Upperdog
 * Author URI: http://upperdog.com
 * Author Email: hello@upperdog.com
 * License: GPL2
 */

/*  Copyright 2014 Upperdog (email : hello@upperdog.se)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if(!defined('ABSPATH')) {
	exit;
}

class CleanImageFilenames {

	function __construct() {
		add_action('wp_handle_upload_prefilter', array($this, 'upload_filter'));
	}


	/**
	 * This function runs when files are being uploaded to the WordPress media 
	 * library.
	 */
	function upload_filter($file) {
		$file = $this->clean_filename($file);
	    return $file;
	}


	/**
	 * Performs the filename cleaning.
	 *
	 * This function performs the actual cleaning of the filename. It takes an 
	 * array with the file information, cleans the filename and sends the file 
	 * information back to where the function was called from. 
	 *
	 * @since 1.1
	 * @param array File details including the filename in $file['name'].
	 * @return array The $file array with cleaned filename.
	 */

	function clean_filename($file) {

		$path = pathinfo($file['name']);
		$new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file['name']);
		$file['name'] = sanitize_title($new_filename) . '.' . $path['extension'];

		return $file;
	}
}

$clean_image_filenames = new CleanImageFilenames();