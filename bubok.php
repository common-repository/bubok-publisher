<?php
/**
 * @package Bubok
 */
/*
Plugin Name: Bubok
Plugin URI: http://www.bubok.net
Description: Plugin de exportación de blog como a libro a la plataforma de bubok
Version: 1.5
Author: bubok.net
License: Private
*/

if (!function_exists( 'add_action' )) {
	echo 'El plugin bubok no puede ser llamado directamente';
	exit;
}

define('BUBOK_VERSION', '1.0');

include_once dirname( __FILE__ ) . '/widget.php';

if (is_admin())
	require_once dirname( __FILE__ ) . '/admin.php';

function bubok_init() {
}

add_action('init', 'bubok_init');
