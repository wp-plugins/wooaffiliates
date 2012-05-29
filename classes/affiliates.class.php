<?php
/**
 * WooThemes Affiliate Links Display.
 *
 * Display a link to a specific WooTheme, the most popular themes or random themes, 
 * containing a link using your WooThemes affiliate ID.
 *
 * @category Widget
 * @package WordPress
 * @subpackage Plugins
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * - $api_url
 * - $woo_widget_cssclass
 * - $woo_widget_description
 * - $woo_widget_idbase
 * - $woo_widget_title
 *
 * - $plugin_url
 *
 * - $defaults
 *
 * - constructor()
 * - widget()
 * - update()
 * - form()
 *
 * - shortcode()
 *
 * - get_contents()
 * - display_contents()
 * - get_stored_data()
 * - get_api_data()
 *
 * - enqueue_styles()
 *
 * Register the widget on `widgets_init`.
 */

class Woo_Affiliates extends WP_Widget {

	var $api_url = 'http://www.woothemes.com/api/';
	var $woo_widget_cssclass;
	var $woo_widget_description;
	var $woo_widget_idbase;
	var $woo_widget_title;
	
	var $plugin_url;
	
	var $defaults = array(
                        'zlink' => '', 
						'display_type' => 'latest', 
						'products' => array(), 
						'display_image' => 1, 
						'display_description' => 1, 
						'display_category' => 1, 
						'display_productname' => 1, 
						'display_meta' => 1, 
						'custom_description' => ''
					);
	
	/**
	 * Woo_Affiliates function.
	 * 
	 * @access public
	 * @return void
	 */
	function Woo_Affiliates () {
		/* Widget variable settings. */
		$this->woo_widget_cssclass = 'widget_woo_affiliates';
		$this->woo_widget_description = __( 'This is a WooThemes standardized affiliates widget.', 'woothemes' );
		$this->woo_widget_idbase = 'woo_affiliates';
		$this->woo_widget_title = __('Woo - Affiliates', 'woothemes' );
		
		/* Plugin URL/path settings. */
		$this->plugin_url = str_replace( '/classes', '', plugins_url( plugin_basename( dirname( __FILE__ ) ) ) );
		
		/* Widget settings. */
		$widget_ops = array( 'classname' => $this->woo_widget_cssclass, 'description' => $this->woo_widget_description );

		/* Widget control settings. */
		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => $this->woo_widget_idbase );

		/* Create the widget. */
		$this->WP_Widget( $this->woo_widget_idbase, $this->woo_widget_title, $widget_ops, $control_ops );
		
		/* Register the shortcode. */
		add_shortcode( 'woo_affiliate', array( &$this, 'shortcode' ) );
		
		/* Enqueue styles. */
		if ( ! is_admin() ) { add_action( 'wp_print_styles', array( &$this, 'enqueue_styles' ) ); }
	} // End Constructor

	/**
	 * widget function.
	 * 
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	function widget( $args, $instance ) {  
		$html = '';
		
		/* Don't display anything if we don't have the affiliate's Zferral link. */
		if ( ! isset( $instance['zlink'] ) || ( isset( $instance['zlink'] ) && ( $instance['zlink'] == '' ) ) ) { return; }
		
		extract( $args, EXTR_SKIP );
		
		/* Our variables from the widget settings. */
		$title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base );
			
		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Display the widget title if one was input (before and after defined by themes). */
		if ( $title ) {
			echo $before_title . $title . $after_title;
		}
		
		/* Widget content. */
		do_action( $this->woo_widget_cssclass . '_top' );
		
		$html = '';
		
		// Get the data to display.
		$data = $this->get_contents( $instance );
		
		if ( count( $data ) > 0 ) {
			$html .= '<div class="woo_affiliate">' . "\n";
				$html .= $this->display_contents( $data );
			$html .= '</div><!--/.woo_affiliate-->' . "\n";
		}
		
		echo $html;

		do_action( $this->woo_widget_cssclass . '_bottom' );

		/* After widget (defined by themes). */
		echo $after_widget;
	} // End widget()

	/**
	 * update function.
	 * 
	 * @access public
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array $instance
	 */
	function update ( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = strip_tags( $new_instance['title'] );
        $instance['zlink'] = strip_tags( $new_instance['zlink'] );
		$instance['display_type'] = esc_attr( $new_instance['display_type'] );
		$instance['products'] = array( esc_attr( $new_instance['products'] ) ); // Store as an array for future-proofing.
		$instance['display_image'] = (bool) esc_attr( $new_instance['display_image'] );
		$instance['display_description'] = (bool) esc_attr( $new_instance['display_description'] );
		$instance['display_category'] = (bool) esc_attr( $new_instance['display_category'] );
		$instance['display_productname'] = (bool) esc_attr( $new_instance['display_productname'] );
		$instance['display_meta'] = (bool) esc_attr( $new_instance['display_meta'] );
		$instance['custom_description'] = esc_html( $new_instance['custom_description'] );

		return $instance;
	} // End update()

   /**
    * form function.
    * 
    * @access public
    * @param array $instance
    * @return void
    */
   function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults );
		
		// Setup an array of the checkboxes, to avoid repeating code.
		$checkboxes = array(
							'display_image' => __( 'Display Image', 'woothemes' ), 
							'display_description' => __( 'Display Description', 'woothemes' ), 
							'display_category' => __( 'Display Category(s)', 'woothemes' ), 
							'display_productname' => __( 'Display Product Name', 'woothemes' ), 
							'display_meta' => __( 'Display Product Meta', 'woothemes' )
							);
							
		// Setup an array of products, to be outputted below.
		$data = $this->get_stored_data();
		$data = unserialize( $data );
		
		$products = array();
		
		if ( is_array( $data ) && ( count( $data ) > 0 ) ) {
			foreach ( $data['all'] as $k => $v ) {
				$products[$k] = $v['title'];
			}
		}
		
		ksort( $products );
?>
		<!-- Widget Title: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title (optional):', 'woothemes' ); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'title' ); ?>"  value="<?php echo $instance['title']; ?>" class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" />
		</p>
                
                <!-- Widget Zferral campaign link: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'zlink' ); ?>"><?php _e( 'Zferral campaign link (required):', 'woothemes' ); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'zlink' ); ?>"  value="<?php echo $instance['zlink']; ?>" class="widefat" id="<?php echo $this->get_field_id( 'zlink' ); ?>" />
		</p>
		<?php
			if ( $instance['zlink'] == '' ) {
		?>
			<p class="submitbox"><small class="submitdelete"><?php echo __( 'Your Zferral campaign link is required.', 'woothemes' ); ?></small></p>
			<p><small><?php printf( __( 'The WooThemes Zferral Affiliate Program is free to sign-up to for everyone. %s.', 'woothemes' ), '<a href="http://www.woothemes.com/affiliate-program/" target="_blank">' . __( 'Go to your affiliate dashboard to sign up', 'woothemes' ) . '</a>' ); ?></small></p>
		<?php
			}
		?>
                        
		<!-- Widget Display Type: Select Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'display_type' ); ?>"><?php _e( 'Display:', 'woothemes' ); ?></label>
			<select name="<?php echo $this->get_field_name( 'display_type' ); ?>" class="widefat" id="<?php echo $this->get_field_id( 'display_type' ); ?>">
				<option value="latest"<?php selected( $instance['display_type'], 'latest' ); ?>><?php _e( 'Latest (Default)', 'woothemes' ); ?></option>  
				<option value="specific"<?php selected( $instance['display_type'], 'specific' ); ?>><?php _e( 'Specific Product', 'woothemes' ); ?></option>
				<option value="random"<?php selected( $instance['display_type'], 'random' ); ?>><?php _e( 'Random', 'woothemes' ); ?></option>
				<option value="popular"<?php selected( $instance['display_type'], 'popular' ); ?>><?php _e( 'Most Popular', 'woothemes' ); ?></option>       
			</select>
		</p>
		<!-- Widget Products: Select Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'products' ); ?>"><?php _e( 'Product:', 'woothemes' ); ?></label>
			<select name="<?php echo $this->get_field_name( 'products' ); ?>" class="widefat" id="<?php echo $this->get_field_id( 'products' ); ?>">
				<option value=""><?php _e( 'Select a Product', 'woothemes' ); ?></option>
		<?php
			$html = '';
			foreach ( $products as $k => $v ) {
				$selected = '';
				if ( in_array( $k, $instance['products'] ) ) { $selected = ' selected="selected"'; }
				
				$html .= '<option value="' . $k . '"' . $selected . '>' . $v . '</option>' . "\n";
			}
			echo $html;
		?>    
			</select>
			<small><?php _e( 'Applies when "Specific Product" is selected.', 'woothemes' ); ?></small>
		</p>
		<?php
			foreach ( $checkboxes as $k => $v ) {
		?>
		<!-- Widget <?php echo $v; ?>: Checkbox Input -->
       	<p>
        	<input id="<?php echo $this->get_field_id( $k ); ?>" name="<?php echo $this->get_field_name( $k ); ?>" type="checkbox"<?php checked( $instance[$k], 1 ); ?> />
        	<label for="<?php echo $this->get_field_id( $k ); ?>"><?php echo $v; ?></label>
	   	</p>
	   	<?php
	   		}
	   	?>
		<!-- Widget Custom Description: Textarea -->
		<p>
			<label for="<?php echo $this->get_field_id( 'custom_description' ); ?>"><?php _e( 'Custom Description:', 'woothemes' ); ?></label>
			<textarea name="<?php echo $this->get_field_name( 'custom_description' ); ?>" class="widefat" rows="5" id="<?php echo $this->get_field_id( 'custom_description' ); ?>"><?php echo $instance['custom_description']; ?></textarea>
		</p>
<?php
	} // End form()
	
	/**
	 * shortcode function.
	 * 
	 * @access public
	 * @param array $atts
	 * @param string $content (default: null)
	 * @return string $output
	 */
	function shortcode ( $atts, $content = null ) {
		$output = '';
		
		$defaults = array(
			'product' => '', 
            'zlink' => '', 
			'type' => 'latest', 
			'float' => 'left', 
			'display_image' => true, 
			'display_description' => true, 
			'display_category' => true, 
			'display_productname' => true, 
			'display_meta' => true, 
			'custom_description' => ''
		);
	
		$atts = shortcode_atts( $defaults, $atts );
	
		if ( $atts['zlink'] == '' ) { return; } // We don't want to do anything further without a Zferral link.

		// If "specific" is set and no product is specified, default to "latest".
		if ( $atts['type'] == 'specific' && $atts['product'] == '' ) {
			$atts['type'] = 'latest';
		}

		extract( $atts );

		$args = array();
		
		// Product Key
		if ( isset( $atts['product'] ) && ( $atts['product'] != '' ) ) {
			$args['products'] = array( $atts['product'] );
		}
		
		// Zferral link
		if ( isset( $atts['zlink'] ) ) {
			$args['zlink'] = $atts['zlink'];
		}
		
		// Display Type
		if ( isset( $atts['type'] ) ) {
			$args['display_type'] = $atts['type'];
		} else {
			if ( isset( $atts['product'] ) && ( $atts['product'] != '' ) ) {
				$args['display_type'] = 'specific';
			} else {
				$args['display_type'] = 'latest';
			}
		}

		// Setup all standard arguments (arguments that don't have specific processing)
		foreach ( array( 'display_image', 'display_description', 'display_category', 'display_productname', 'display_meta', 'custom_description' ) as $k => $v ) {
			if ( (string)$atts[$v] == 'false' ) {
				$args[$v] = false;
			} else {
				$args[$v] = $atts[$v];
			}
		}
		
		// Get the data to display.
		$data = $this->get_contents( $args );
		
		if ( count( $data ) > 0 ) {
			$output .= '<div class="sc-woo-affiliate woo_affiliate align' . $float . '">' . "\n";
				$output .= $this->display_contents( $data );
			$output .= '</div><!--/.woo_affiliate-->' . "\n";
		}
		
		return $output;
	} // End shortcode()
	
	/**
	 * get_contents function.
	 * 
	 * @access public
	 * @param array $args (default: array())
	 * @return array $data
	 */
	function get_contents ( $args = array() ) {
		$data = $this->get_stored_data();
		$contents = array();
		$theme_key = '';
		
		$data = maybe_unserialize( $data );
		
		$args = wp_parse_args( (array) $args, $this->defaults );
		
		// Format the data, preparing it for sending to the display function.
		switch ( $args['display_type'] ) {
			case 'specific':
			
			$theme_key = $args['products'][0];
			
			if ( isset( $data['all'][$theme_key] ) ) {
				$theme_data = $data['all'][$theme_key];
				
				$contents = $theme_data;			
			}
			
			break;
			
			case 'latest':

				if ( isset( $data['latest']['name'] ) && ( count( $data['latest'] ) > 0 ) ) {
					$theme_key = $data['latest']['name'];
					
					$theme_data = $data['all'][$theme_key];
				
					$contents = $theme_data;
				}
				
			break;
			
			case 'popular':
				
				if ( isset( $data['all'] ) && ( count( $data['all'] ) > 0 ) ) {
					$theme_data = array_shift( $data['all'] );
					
					$contents = $theme_data;
				}
				
			break;
			
			case 'random':
				
				if ( isset( $data['all'] ) && ( count( $data['all'] ) > 0 ) ) {
					shuffle( $data['all'] );
					
					$theme_data = array_shift( $data['all'] );
					
					$contents = $theme_data;
				}
				
			break;
		}
		
		$contents['zlink'] = $args['zlink'];
		$contents['display_image'] = $args['display_image'];
		$contents['display_description'] = $args['display_description'];
		$contents['display_category'] = $args['display_category'];
		$contents['display_productname'] = $args['display_productname'];
		$contents['display_meta'] = $args['display_meta'];
		
		if ( $args['custom_description'] != '' ) {
			$contents['description'] = $args['custom_description'];
		}
                
                //Get Zferrall link
                $contents['link'] = $contents['zlink'] . '?d=' . $contents['permalink'];
		
		return $contents;
	} // End get_contents()
	
	/**
	 * display_contents function.
	 * 
	 * @access public
	 * @param array $data
	 * @return string $html
	 */
	function display_contents ( $data ) {
                
		$html = '';

		// Image
		if ( $data['display_image'] == true && $data['image'] != '' ) {
			$html .= '<div class="image-container">' . "\n";
			$html .= '<div class="browser-window"><img src="' . $this->plugin_url . '/assets/images/bg-screenshot.png" /></div>' . "\n";
			$html .= '<div class="image">' . "\n" . '<a href="' . $data['link'] . '"><img src="' . $data['image'] . '" /></a>' . "\n" . '</div>' . "\n";
			$html .= '</div><!--/.image-container-->' . "\n";
		}
		
		// Title
		if ( $data['display_productname'] == true ) {
			$html .= '<h4><a href="' . $data['link'] . '">' . $data['title'] . '</a></h4>' . "\n";
		}
		
		// Meta
		if ( $data['display_meta'] == true ) {
			$html .= '<p class="meta"><small>' . sprintf( __( 'By %s', 'woothemes' ), '<a href="' . $data['zlink'] . '?d=http://woothemes.com/">WooThemes</a>' ) . '</small></p>' . "\n";
		}
		
		// Description
		if ( $data['display_description'] == true && ( $data['description'] != '' ) ) {
			$html .= wpautop( $data['description'] ) . "\n";
		}
		
		// Categories
		if ( $data['display_category'] == true && ( count( $data['categories'] ) > 0 ) ) {
			$categories = array();
			
			foreach ( $data['categories'] as $k => $v ) {
				$categories[] = '<a href="'. $data['zlink'] . '?d=' . $v['url'] . '">' . $v['name'] . '</a>';
			}
			$html .= '<p class="categories"><small>' . __( 'Theme Categories: ', 'woothemes' ) . ' ' . join( ', ', $categories ) . '</small></p>' . "\n";
		}
		
		return $html;
	} // End display_contents()
	
	/**
	 * get_stored_data function.
	 *
	 * @description Check if we have data in storage. If not, query it from the API.
	 * @access public
	 * @return void
	 */
	function get_stored_data () {
		$data = get_transient( 'woo_affiliates_data' );
		
		if ( ! $data || $data == '' ) {
			$data = $this->get_api_data( array( 'action' => 'get_affiliates_products' ) );
			
			if ( is_serialized( $data ) ) {
				set_transient( 'woo_affiliates_data', $data, 60*60*24*30 ); // Cache for 30 days.
			}
		}
		
		return $data;
	} // End get_stored_data()
	
	/**
	 * get_api_data function.
	 *
	 * @description Return the contents of a URL using wp_remote_post().
	 * @access public
	 * @param array $params (default: array())
	 * @return string $data
	 */
	function get_api_data ( $params = array() ) {
		$response = wp_remote_post( $this->api_url, array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(),
			'body' => $params,
			'cookies' => array()
		    )
		);
		
		if( is_wp_error( $response ) ) {
		  $data = new StdClass();
		  $data = serialize( $data );
		} else {
			$data = $response['body'];
		}
			
		return $data;
	} // End get_api_data()
	
	function enqueue_styles () {
		wp_register_style( 'woo-affiliates', $this->plugin_url . '/assets/css/style.css', '', '1.0.0' );
		wp_enqueue_style( 'woo-affiliates' );
	} // End enqueue_styles()
	
} // End Class

 /**
  * Register the widget on `widgets_init`.
  * 
  * @access public
  */
add_action( 'widgets_init', create_function( '', 'return register_widget("Woo_Affiliates");' ), 1 ); 
?>