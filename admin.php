<?php

@set_time_limit(0);

add_action( 'admin_menu', 'bubok_admin_menu' );

function bubok_admin_init() {
    global $wp_version;

    if ( function_exists( 'get_plugin_page_hook' ) )
        $hook = get_plugin_page_hook( 'bubok-stats-display', 'index.php' );
    else
        $hook = 'dashboard_page_bubok-stats-display';
}
add_action('admin_init', 'bubok_admin_init');

$error_lib = (!function_exists('curl_init')) ? true : false;

function bubok_conf() {
	global $error_lib;
	
	include_once dirname( __FILE__ ) . '/export.php';
	
	$title = __('Exportar a Bubok');
	
	if ( isset( $_POST['download'] ) && !$error_lib) {
		$args = array();
	
		$args['content'] = 'post';
	
		if ( $_POST['cat'] )
			$args['category'] = (int) $_POST['cat'];

		if ( $_POST['post_start_date'] || $_POST['post_end_date'] ) {
			$args['start_date'] = $_POST['post_start_date'];
			$args['end_date'] = $_POST['post_end_date'];
		}
	
		if ( $_POST['post_status'] )
			$args['status'] = $_POST['post_status'];
	
		$args = apply_filters( 'export_args', $args );
	
		echo "<h3>Exportando a Bubok, por favor se paciente, este proceso puede tardar algunos minutos ...</h3>";
		
		echo "<strong>Generando archivo de importaci&oacute;n...</strong><br />";
		
		bubok_export_wp( $args );
		
		$email = trim($_POST['email']);
		$password = trim($_POST['password']);
		$server = trim($_POST['server']);
		
		
		echo "<strong>Exportando a Bubok...</strong><br />";
		flush();
		ob_flush();
		
		$post_parameters = array('email' => $email, "password" => $password, "xml" => get_xml_export());
		
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, "http://www." . $server . "/wordpress/import");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_parameters));

		$result = curl_exec($ch);
		
		curl_close($ch);
		
		bubok_garbage_collector();
		
		if (!$result) {
			echo '<strong style="color:red">Imposible conectar con bubok... deteniendo importacion</strong><br />';
		} else {
			echo '<h3>' . $result . '</h3>';
		}
		
		exit;
	}
	
	function export_date_options( $post_type = 'post' ) {
		global $wpdb, $wp_locale;
	
		$months = $wpdb->get_results( $wpdb->prepare( "
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM $wpdb->posts
			WHERE post_type = %s AND post_status != 'auto-draft'
			ORDER BY post_date DESC
		", $post_type ) );
	
		$month_count = count( $months );
		if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;
	
		foreach ( $months as $date ) {
			if ( 0 == $date->year )
				continue;
	
			$month = zeroise( $date->month, 2 );
			echo '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
		}
	}
	?>
	
    <script type="text/javascript">
	//<![CDATA[
		jQuery(document).ready(function($){
			$('#export-filters').submit(function() {
				if ($('#bb_email').val() == '' || $('#bb_password').val() == '') {
					alert('Debes llenar todos los campos');
					return false;
				}
				
			});
		});
	//]]>
	</script>
    
    <?php 	
	if ($error_lib) {
		echo "<div id='akismet-warning' class='updated fade'>"
				. "<p><strong>".__('Error: necesita la libreria curl en su servidor para usar la exportacion de Bubok.') . "</strong></p></div>";
	}
    ?>
    
	<div class="wrap"> 
	<h2><?php echo esc_html( $title ); ?></h2>
	
	<p><?php _e('Debes introducir tus datos de acceso en Bubok. Una vez pulses sobre el bot&oacute;n de exportar el libro ser&aacute; creado en tu cuenta personal de Bubok.'); ?> <a href="http://www.bubok.net" title="Bubok.net"><?php _e('&iquest;No tienes cuenta? &iexcl;Consigue la tuya!')?></a></p>
	<p><?php _e('Selecciona que deseas exportar a un libro.'); ?></p>
	
	<form action="?page=bubok" method="post" id="export-filters">
	<input type="hidden" name="download" value="true" />
	<h3><?php _e('Usuario en <strong>Bubok</strong>')?></h3>
    <ul>
    	<li><?php _e('E-mail')?></li>
        <li><input type="text" id="bb_email" name="email" /></li>
        <li><?php _e('Password')?></li>
        <li><input type="password" id="bb_password" name="password" /></li>
    </ul>
    
	<h3><?php _e( 'Elige que entradas se exportar&aacute;n' ); ?></h3>	
	<p style="display:none"><label><input type="radio" name="content" value="posts" checked="checked" /> <?php _e( 'Posts' ); ?></label></p>
	<ul id="post-filters" class="export-filters" style="margin-left:0px">
		<li>
			<label><?php _e( 'Categories:' ); ?></label>
			<?php wp_dropdown_categories( array( 'show_option_all' => __('All') ) ); ?>
		</li>
		
		<li>
			<label><?php _e( 'Date range:' ); ?></label>
			<select name="post_start_date">
				<option value="0"><?php _e( 'Start Date' ); ?></option>
				<?php export_date_options(); ?>
			</select>
			<select name="post_end_date">
				<option value="0"><?php _e( 'End Date' ); ?></option>
				<?php export_date_options(); ?>
			</select>
		</li>
		<li>
			<label><?php _e( 'Status:' ); ?></label>
			<select name="post_status">
				<option value="0"><?php _e( 'All' ); ?></option>
				<?php $post_stati = get_post_stati( array( 'internal' => false ), 'objects' );
				foreach ( $post_stati as $status ) : ?>
				<option value="<?php echo esc_attr( $status->name ); ?>"><?php echo esc_html( $status->label ); ?></option>
				<?php endforeach; ?>
			</select>
		</li>
	</ul>
	
    
	<h3><?php _e('Sitio en <strong>Bubok</strong>')?></h3>
    <ul>
    	<li><select name="server">
        	<option value="bubok.es">Bubok.es</option>
            <option value="bubok.pt">Bubok.pt</option>
            <option value="bubok.com">Bubok.com</option>
            <option value="bubok.com.ar">Bubok.com.ar</option>        
            <option value="bubok.com.br">Bubok.com.br</option>
            <option value="bubok.com.mx">Bubok.com.mx</option>
            <option value="bubok.co">Bubok.co</option>
            <option value="bubok.fr">Bubok.fr</option>
        </select></li>
    </ul>
	
	<?php 
		if (!$error_lib)
			submit_button( __('Exportar entradas a Bubok') );
	?>
    
	</form>
	</div>	
<?php
}

function bubok_stats_display() {
	$blog = urlencode( get_bloginfo('url') );

	$url = 'http://';
	if ( is_ssl() )
		$url = 'https://';

	$url .= 'bubok.es/wp-stats.php';
	?>
	<div class="wrap">
	<iframe src="<?php echo $url; ?>" width="100%" height="2500px" frameborder="0" id="akismet-stats-frame"></iframe>
	</div>
	<?php
}

function bubok_stats() {
	if ( !function_exists('did_action') || did_action( 'rightnow_end' ) ) // We already displayed this info in the "Right Now" section
		return;
		
	$path = plugin_basename(__FILE__);
	echo '<h3>Stats</h3>';

}
add_action('activity_box_end', 'bubok_stats');

// Returns true if server connectivity was OK at the last check, false if there was a problem that needs to be fixed.
function bubok_server_connectivity_ok() {	
	$servers = bubok_get_server_connectivity();
	return !( empty($servers) || !count($servers) || count( array_filter($servers) ) < count($servers) );
}

function bubok_admin_menu() {
	if ( class_exists( 'Jetpack' ) ) {
		add_action( 'jetpack_admin_menu', 'bubok_load_menu' );
	} else {
		bubok_load_menu();
	}
}

function bubok_load_menu() {
	$page = add_utility_page(__( 'Exportar a Bubok'), __( 'Exportar a Bubok'), 'administrator', 'bubok', 'bubok_conf');	
}
