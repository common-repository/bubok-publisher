<?php
/**
 * @package Bubok
 */
class Bubok_Widget extends WP_Widget {

	function __construct() {
		parent::__construct(
			'bubok_widget',
			__('Bubok Widget'),
			array( 'description' => __('') )
		);

		//if (is_active_widget( false, false, $this->id_base)) {
			//add_action('wp_head', array( $this, 'css'));
		//}
	}

	function css() {
?>
<style type="text/css">
</style>
<?php
	}

	function form( $instance ) {
		if ( $instance ) {
			$title = esc_attr( $instance['title'] );
		}
		else {
			$title = __( 'Bubok auto-publishing' );
		}
?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
<?php 
	}

	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}

	function widget( $args, $instance ) {
		$count = get_option( 'akismet_spam_count' );

		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'];
			echo esc_html( $instance['title'] );
			echo $args['after_title'];
		}
?>
	<div class="a-stats">
		<a href="http://bubok.es" target="_blank" title="">Bubok.es</a>
	</div>
<?php
		echo $args['after_widget'];
	}
}

function bubok_register_widgets() {
	register_widget( 'Bubok_Widget' );
}

add_action( 'widgets_init', 'bubok_register_widgets' );
