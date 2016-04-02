<?php
/**
 * The main plugin file for Merged_Recent_Comments Comments.
 *
 * @package   Merged_Recent_Comments
 * @author    Patryk Ostrzołek <patryk.ostrzolek@gmail.com>
 * @license   GPL-2.0+
 * @link      http://github.com/patrykostrzolek
 * @copyright 2016 Patryk Ostrzołek
 *
 * @wordpress-plugin
 * Plugin Name:       Merged Recent Comments
 * Description:       This widget merges recent comments and wraps them in current language titles and links. This requires 'WPML merged comments plugin' for proper working. Plugin is compatible with PolyLang and WPML.  
 * Version:           1.0.0
 * Author:            Patryk Ostrzołek
 * Author URI:        http://github.com/patrykostrzolek
 * Text Domain:       merged-recent-comments
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Core class used to implement a Recent Comments widget.
 *
 * @since 2.8.0
 *
 * @see WP_Widget
 */
class Widget_Merged_Recent_Comments extends WP_Widget {
	
	/**
	 * Sets up a new Recent Comments widget instance.
	 *
	 * @since 2.8.0
	 * @access public
	 */
	public function __construct() {
		$widget_ops = array('classname' => 'Widget_Merged_Recent_Comments', 'description' => __( 'Merged Comments wrapped in current language.', 'merged-recent-comments') );
		parent::__construct('merged-recent-comments', __('Merged Recent Comments', 'merged-recent-comments'), $widget_ops );
		$this->alt_option_name = 'widget_recent_comments';

		if ( is_active_widget(false, false, $this->id_base) )
			add_action( 'wp_head', array($this, 'recent_comments_style') );
	}

 	/**
	 * Outputs the default styles for the Recent Comments widget.
	 *
	 * @since 2.8.0
	 * @access public
	 */
	public function recent_comments_style() {
		/**
		 * Filter the Recent Comments default widget styles.
		 *
		 * @since 3.1.0
		 *
		 * @param bool   $active  Whether the widget is active. Default true.
		 * @param string $id_base The widget ID.
		 */
		if ( ! current_theme_supports( 'widgets' ) // Temp hack #14876
			|| ! apply_filters( 'show_recent_comments_widget_style', true, $this->id_base ) )
			return;
		?>
		<style type="text/css">.recentcomments a{display:inline !important;padding:0 !important;margin:0 !important;}</style>
		<?php
	}
	
	/**
	 * Outputs the comment url and title in user language if translated version exists.
	 *
	 * @since ???
	 * @access private
	 *
	 */
	private function comment_url_and_title( $comment ) {
		$type = is_page( $comment->comment_post_ID ) ? 'page' : 'post';
		$commonID = icl_object_id( $comment->comment_post_ID, $type, true );
		$permalink = get_permalink( $commonID ) . '#comment-' . $comment->comment_ID;
		$title = get_the_title( $commonID );
		$post_lang = function_exists('pll_get_post_language') ? pll_get_post_language($comment->comment_post_ID) : 
		$GLOBALS['sitepress']->get_language_for_element( $comment->comment_post_ID, 'post_' . $type);
        
		return '<a href="' . $permalink . '">' . $title . '</a> <span>[' . $post_lang . ']</span>';
	}

	
	/**
	 * Outputs the content for the current Recent Comments widget instance.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Recent Comments widget instance.
	 */
	public function widget( $args, $instance ) {
		
			
		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		$output = '';

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Recent Comments' );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 5;
		if ( ! $number )
			$number = 5;

		/**
		 * Filter the arguments for the Recent Comments widget.
		 *
		 * @since 3.4.0
		 *
		 * @see WP_Comment_Query::query() for information on accepted arguments.
		 *
		 * @param array $comment_args An array of arguments used to retrieve the recent comments.
		 */
		
		$comments = get_comments( apply_filters( 'widget_comments_args', array(
			'number'      => $number,
			'status'      => 'approve',
			'post_status' => 'publish',
		) ) );
		//Turn on filter after turned off while generating recent comments
		//add_filter('comments_clauses', array(&$polylang->filters, 'comments_clauses'));
		
		$output .= $args['before_widget'];
		if ( $title ) {
			$output .= $args['before_title'] . $title . $args['after_title'];
		}

		$output .= '<ul id="recentcomments">';
		if ( is_array( $comments ) && $comments ) {
			// Prime cache for associated posts. (Prime post term cache if we need it for permalinks.)
			$post_ids = array_unique( wp_list_pluck( $comments, 'comment_post_ID' ) );
			_prime_post_caches( $post_ids, strpos( get_option( 'permalink_structure' ), '%category%' ), false );

			foreach ( (array) $comments as $comment ) {
				$output .= '<li class="recentcomments">';
				/* translators: comments widget: 1: comment author, 2: post link */
				$output .= sprintf( _x( '%1$s on %2$s', 'widgets' ),
					'<span class="comment-author-link">' . get_comment_author_link( $comment ) . '</span>',
					$this->comment_url_and_title($comment)
				);
				$output .= '</li>';
			}
		}
		$output .= '</ul>';
		
		$output .= $args['after_widget'];
		//$output .= '<pre>' . print_r($polylang->filters, true) .  '</pre>';

		echo $output;
	}

	/**
	 * Handles updating settings for the current Recent Comments widget instance.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['number'] = absint( $new_instance['number'] );
		return $instance;
	}

	/**
	 * Outputs the settings form for the Recent Comments widget.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of comments to show:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" /></p>
		<!--
		<p><label for="<?php echo $this->get_field_id( 'show-post-lang' ); ?>"><?php _e( 'Show user lang:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'show-post-lang' ); ?>" name="<?php echo $this->get_field_name( 'show-post-lang' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" /></p>-->
		<?php
	}

	/**
	 * Flushes the Recent Comments widget cache.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @deprecated 4.4.0 Fragment caching was removed in favor of split queries.
	 */
	public function flush_widget_cache() {
		_deprecated_function( __METHOD__, '4.4' );
	}
}

	function polylang_remove_comments_filter() {
		global $polylang;
        global $sitepress;
		remove_filter( 'comments_clauses', array( &$polylang->filters, 'comments_clauses'));
        remove_filter( 'comments_clauses', array( &$sitepress, 'comments_clauses' ));
	}
	add_action('wp','polylang_remove_comments_filter');
	
	function mrc_textdomain() {
		load_plugin_textdomain( 'merged-recent-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}
	add_action('plugins_loaded', 'mrc_textdomain');
	
	
	function register_merged_comments_widget() {
		if ( function_exists('icl_object_id') )
		 register_widget( 'Widget_Merged_Recent_Comments' );
	}
	add_action( 'widgets_init', 'register_merged_comments_widget');
