<?php
/**
 * Export API basada en Export Tool de Wordpress
 *
 **/

define( 'WXR_VERSION', '1.2' );

global $xml_export, $in_file, $file_path;

$xml_export = '';
$in_file = true;
$file_path = '';

function get_xml_export() {
	global $xml_export, $in_file, $file_path;
	if ($in_file)
		return file_get_contents($file_path);
	
	return $xml_export;
}

function bubok_garbage_collector() {
	global $file_path;
	if (file_exists($file_path) && is_file($file_path)) {
	//	@unlink($file_path);
	}
}

function bubok_export_wp( $args = array() ) {
	global $wpdb, $post, $xml_export, $in_file, $file_path;

	$defaults = array( 'content' => 'all', 'author' => false, 'category' => false,
		'start_date' => false, 'end_date' => false, 'status' => false,
	);
	$args = wp_parse_args( $args, $defaults );

	do_action( 'export_wp' );

	if ( 'all' != $args['content'] && post_type_exists( $args['content'] ) ) {
		$ptype = get_post_type_object( $args['content'] );
		if ( ! $ptype->can_export )
			$args['content'] = 'post';

		$where = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $args['content'] );
	} else {
		$post_types = get_post_types( array( 'can_export' => true ) );
		$esses = array_fill( 0, count($post_types), '%s' );
		$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types );
	}

	if ( $args['status'] && ( 'post' == $args['content'] || 'page' == $args['content'] ) )
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_status = %s", $args['status'] );
	else
		$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";

	$join = '';
	if ( $args['category'] && 'post' == $args['content'] ) {
		if ( $term = term_exists( $args['category'], 'category' ) ) {
			$join = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
			$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term['term_taxonomy_id'] );
		}
	}

	if ( 'post' == $args['content'] || 'page' == $args['content'] ) {
		if ( $args['author'] )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $args['author'] );

		if ( $args['start_date'] )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date >= %s", date( 'Y-m-d', strtotime($args['start_date']) ) );

		if ( $args['end_date'] )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date < %s", date( 'Y-m-d', strtotime('+1 month', strtotime($args['end_date'])) ) );
	}

	// grab a snapshot of post IDs, just in case it changes during the export
	$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

	// get the requested terms ready, empty unless posts filtered by category or all content
	$cats = $tags = $terms = array();
	if ( isset( $term ) && $term ) {
		$cat = get_term( $term['term_id'], 'category' );
		$cats = array( $cat->term_id => $cat );
		unset( $term, $cat );
	} else if ( 'all' == $args['content'] ) {
		$categories = (array) get_categories( array( 'get' => 'all' ) );
		$tags = (array) get_tags( array( 'get' => 'all' ) );

		$custom_taxonomies = get_taxonomies( array( '_builtin' => false ) );
		$custom_terms = (array) get_terms( $custom_taxonomies, array( 'get' => 'all' ) );

		// put categories in order with no child going before its parent
		while ( $cat = array_shift( $categories ) ) {
			if ( $cat->parent == 0 || isset( $cats[$cat->parent] ) )
				$cats[$cat->term_id] = $cat;
			else
				$categories[] = $cat;
		}

		// put terms in order with no child going before its parent
		while ( $t = array_shift( $custom_terms ) ) {
			if ( $t->parent == 0 || isset( $terms[$t->parent] ) )
				$terms[$t->term_id] = $t;
			else
				$custom_terms[] = $t;
		}

		unset( $categories, $custom_taxonomies, $custom_terms );
	}

	function wxr_cdata( $str ) {
		if ( seems_utf8( $str ) == false )
			$str = utf8_encode( $str );

		// $str = ent2ncr(esc_html($str));
		$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

		return $str;
	}
	function wxr_site_url() {
		// ms: the base url
		if ( is_multisite() )
			return network_home_url();
		// wp: the blog url
		else
			return get_bloginfo_rss( 'url' );
	}

	function wxr_cat_name( $category ) {
		if ( empty( $category->name ) )
			return;

		return '<wp:cat_name>' . wxr_cdata( $category->name ) . '</wp:cat_name>';
	}

	function wxr_category_description( $category ) {
		if ( empty( $category->description ) )
			return;

		return '<wp:category_description>' . wxr_cdata( $category->description ) . '</wp:category_description>';
	}

	function wxr_tag_name( $tag ) {
		if ( empty( $tag->name ) )
			return;

		return '<wp:tag_name>' . wxr_cdata( $tag->name ) . '</wp:tag_name>';
	}

	function wxr_tag_description( $tag ) {
		if ( empty( $tag->description ) )
			return;

		return '<wp:tag_description>' . wxr_cdata( $tag->description ) . '</wp:tag_description>';
	}

	function wxr_term_name( $term ) {
		if ( empty( $term->name ) )
			return;

		return '<wp:term_name>' . wxr_cdata( $term->name ) . '</wp:term_name>';
	}

	function wxr_term_description( $term ) {
		if ( empty( $term->description ) )
			return;

		return '<wp:term_description>' . wxr_cdata( $term->description ) . '</wp:term_description>';
	}

	function wxr_authors_list() {
		global $wpdb;

		$authors = array();
		$results = $wpdb->get_results( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE post_status != 'auto-draft'" );
		foreach ( (array) $results as $result )
			$authors[] = get_userdata( $result->post_author );

		$authors = array_filter( $authors );

		foreach ( $authors as $author ) {
			in_output("\t<wp:author>");
			in_output('<wp:author_id>' . $author->ID . '</wp:author_id>');
			in_output('<wp:author_login>' . $author->user_login . '</wp:author_login>');
			in_output('<wp:author_email>' . $author->user_email . '</wp:author_email>');
			in_output('<wp:author_display_name>' . wxr_cdata( $author->display_name ) . '</wp:author_display_name>');
			in_output('<wp:author_first_name>' . wxr_cdata( $author->user_firstname ) . '</wp:author_first_name>');
			in_output('<wp:author_last_name>' . wxr_cdata( $author->user_lastname ) . '</wp:author_last_name>');
			in_output("</wp:author>\n");
		}
	}

	function wxr_nav_menu_terms() {
		$nav_menus = wp_get_nav_menus();
		if ( empty( $nav_menus ) || ! is_array( $nav_menus ) )
			return;

		foreach ( $nav_menus as $menu ) {
			in_output("\t<wp:term><wp:term_id>{$menu->term_id}</wp:term_id><wp:term_taxonomy>nav_menu</wp:term_taxonomy><wp:term_slug>{$menu->slug}</wp:term_slug>");
			wxr_term_name( $menu );
			in_output("</wp:term>\n");
		}
	}

	function wxr_post_taxonomy() {
		$post = get_post();

		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( empty( $taxonomies ) )
			return;
		$terms = wp_get_object_terms( $post->ID, $taxonomies );

		foreach ( (array) $terms as $term ) {
			in_output("\t\t<category domain=\"{$term->taxonomy}\" nicename=\"{$term->slug}\">" . wxr_cdata( $term->name ) . "</category>\n");
		}
	}

	function wxr_filter_postmeta( $return_me, $meta_key ) {
		if ( '_edit_lock' == $meta_key )
			$return_me = true;
		return $return_me;
	}
	
	function in_output($r) {
		global $xml_export, $in_file, $file_path;
		
		if ($in_file) {
			file_put_contents($file_path, $r, FILE_APPEND);
		} else {
			$xml_export .= $r;
		}
	}
	
	add_filter( 'wxr_export_skip_postmeta', 'wxr_filter_postmeta', 10, 2 );
	
	$path = dirname(__FILE__) . "/../../uploads/";
	$file_path = $path . date('d_m_Y_h_s') . ".xml";
	
	if (!file_exists($path) || @file_put_contents($file_path, '') === false)
		$in_file = false;

	in_output('<?xml version="1.0" encoding="' . get_bloginfo('charset') . "\" ?>\n");

	in_output('
	<rss version="2.0"
		xmlns:excerpt="http://wordpress.org/export/' . WXR_VERSION . '/excerpt/"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:wp="http://wordpress.org/export/' . WXR_VERSION . '/"
	>');

	in_output('<channel>
		<title>' . get_bloginfo_rss( 'name' ) . '</title>
		<link>' . get_bloginfo_rss( 'url' ) . '</link>
		<description>' . get_bloginfo_rss( 'description' ) . '</description>
		<pubDate>' . date( 'D, d M Y H:i:s +0000' ) . '</pubDate>
		<language>' . get_bloginfo_rss( 'language' ) . '</language>
		<wp:wxr_version>' . WXR_VERSION . '</wp:wxr_version>
		<wp:base_site_url>' . wxr_site_url() . '</wp:base_site_url>
		<wp:base_blog_url>' . get_bloginfo_rss( 'url' ) . '</wp:base_blog_url>');
		
	wxr_authors_list();

	foreach ( $cats as $c ) : 
		in_output('<wp:category><wp:term_id>' . $c->term_id . '</wp:term_id><wp:category_nicename>' . $c->slug . '</wp:category_nicename><wp:category_parent>' . ($c->parent ? $cats[$c->parent]->slug : '') . '</wp:category_parent>' . wxr_cat_name( $c ) . '' . wxr_category_description( $c ) . '</wp:category>');
 	endforeach;
	
	foreach ( $tags as $t ) : 
		in_output('<wp:tag><wp:term_id>' . $t->term_id . '</wp:term_id><wp:tag_slug>' . $t->slug . '</wp:tag_slug>' . wxr_tag_name( $t ) . '' . wxr_tag_description( $t ) . '</wp:tag>');
	endforeach; 
	
	foreach ( $terms as $t ) :
		in_output('<wp:term><wp:term_id>' . $t->term_id . '</wp:term_id><wp:term_taxonomy>' . $t->taxonomy . '</wp:term_taxonomy><wp:term_slug>' .  $t->slug . '</wp:term_slug><wp:term_parent>' . ($t->parent ? $terms[$t->parent]->slug : '') . '</wp:term_parent>' . wxr_term_name( $t ) . '' . wxr_term_description( $t ) . '</wp:term>');
	endforeach;
	
	if ( 'all' == $args['content'] ) wxr_nav_menu_terms();

	if ( $post_ids ) {
		global $wp_query;
		$wp_query->in_the_loop = true; // Fake being in the loop.

		// fetch 20 posts at a time rather than loading the entire table into memory
		while ( $next_posts = array_splice( $post_ids, 0, 20 ) ) {
			$where = 'WHERE ID IN (' . join( ',', $next_posts ) . ')';
			$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $where" );
	
			// Begin Loop
			foreach ( $posts as $post ) {
				setup_postdata( $post );
				$is_sticky = is_sticky( $post->ID ) ? 1 : 0;
				
				in_output('<item>
					<title>' . apply_filters( 'the_title_rss', $post->post_title ) . '</title>
					<link>' .  get_permalink() . '</link>
					<pubDate>' . mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ) . '</pubDate>
					<dc:creator>' . get_the_author_meta( 'login' ) . '</dc:creator>
					<guid isPermaLink="false">' . esc_url(get_the_guid()) . '</guid>
					<description></description>
					<content:encoded>' . wxr_cdata( apply_filters( 'the_content_export', $post->post_content ) ) . '</content:encoded>
					<excerpt:encoded>' .  wxr_cdata( apply_filters( 'the_excerpt_export', $post->post_excerpt ) ) . '</excerpt:encoded>
					<wp:post_id>' . $post->ID . '</wp:post_id>
					<wp:post_date>' . $post->post_date . '</wp:post_date>
					<wp:post_date_gmt>' . $post->post_date_gmt . '</wp:post_date_gmt>
					<wp:comment_status>' . $post->comment_status . '</wp:comment_status>
					<wp:ping_status>' . $post->ping_status . '</wp:ping_status>
					<wp:post_name>' . $post->post_name . '</wp:post_name>
					<wp:status>' . $post->post_status . '</wp:status>
					<wp:post_parent>' . $post->post_parent . '</wp:post_parent>
					<wp:menu_order>' . $post->menu_order . '</wp:menu_order>
					<wp:post_type>' . $post->post_type . '</wp:post_type>
					<wp:post_password>' . $post->post_password . '</wp:post_password>
					<wp:is_sticky>' . $is_sticky . '</wp:is_sticky>');
	
					wxr_post_taxonomy();
					
					$postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );
					foreach ( $postmeta as $meta ) :
						if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) )
							continue;
						in_output('
							<wp:postmeta>
								<wp:meta_key>' . $meta->meta_key . '</wp:meta_key>
								<wp:meta_value>' . wxr_cdata( $meta->meta_value ) . '</wp:meta_value>
							</wp:postmeta>');
					endforeach;
					in_output('</item>');
			}
		}
	}
	in_output('</channel></rss>');
}
