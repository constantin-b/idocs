<?php
/**
 * 
 * Register post type and taxonomy and provide functionality for them
 * @author Constantin
 *
 */
class iDocument{
	
	private $post_type = 'documentation';
	private $taxonomy  = 'documents';
	
	
	public function __construct(){
		
		// init action, register post type
		add_action('init', array($this, 'register_post'), 100);
		
		// modify post permalink to include taxonomy
		add_filter('post_type_link', array($this, 'modify_permalink'), 10, 2);
		
		// on archive pages order docs by menu order
		add_filter( 'pre_get_posts', array($this, 'reorder_tax_docs' ), 999 );
		
		// add columns to posts table
		add_filter('manage_edit-'.$this->post_type.'_columns', array( $this, 'extra_columns' ));
		add_action('manage_'.$this->post_type.'_posts_custom_column', array($this, 'output_extra_columns'), 10, 2);		
		
		// add extra menu pages
		add_action('admin_menu', array($this, 'menu_pages'), 1);
		
		// modify adjacent post query for docs post type to stay within the same category
		add_filter( 'get_previous_post_join', array($this, 'adjacent_post_join'), 10, 3);
		add_filter( 'get_previous_post_where', array($this, 'adjacent_post_where'), 10, 3);
		add_filter( 'get_next_post_join', array($this, 'adjacent_post_join'), 10, 3);
		add_filter( 'get_next_post_where', array($this, 'adjacent_post_where'), 10, 3);
		
		// modify post title to include the taxonomy name
		add_filter( 'single_post_title', array($this, 'prepend_taxonomy'), 10, 2 );
		
		// document save action
		add_action('save_post_' . $this->post_type, array( $this, 'save_document' ), 10, 3);
	}
	
	/**
	 * Register post type and taxonomy
	 */
	public function register_post(){
		
		$labels = array(
			'name' 					=> _x('Documents', 'Documents', 'idocs'),
	    	'singular_name' 		=> _x('Document', 'Document', 'idocs'),
	    	'add_new' 				=> _x('Add new', 'Add new document', 'idocs'),
	    	'add_new_item' 			=> __('Add new document', 'idocs'),
	    	'edit_item' 			=> __('Edit document', 'idocs'),
	    	'new_item'				=> __('New document', 'idocs'),
	    	'all_items' 			=> __('All documents', 'idocs'),
	    	'view_item' 			=> __('View', 'idocs'),
	    	'search_items' 			=> __('Search', 'idocs'),
	    	'not_found' 			=> __('No documents found', 'idocs'),
	    	'not_found_in_trash' 	=> __('No documents in trash', 'idocs'), 
	    	'parent_item_colon' 	=> '',
	    	'menu_name' 			=> __('Documents', 'idocs')
		);
		
		$args = array(
    		'labels' 				=> $labels,
    		'public' 				=> true,
			'exclude_from_search'	=> false,
    		'publicly_queryable' 	=> true,
			'show_in_nav_menus'		=> true,
		
    		'show_ui' 				=> true,
			'show_in_menu' 			=> true,
			'menu_position' 		=> 20,
			'menu_icon'				=> IDOCS_URL.'assets/back-end/images/menu-icon.png',	
		
    		'query_var' 			=> true,
    		'capability_type' 		=> 'post',
    		'has_archive' 			=> false, 
    		'hierarchical' 			=> true,
    		'rewrite'				=> array(
				'slug' 			=> $this->post_type.'/%'.$this->taxonomy.'%',
				'with_front' 	=> false
			),
			'register_meta_box_cb' => array( $this, 'meta_boxes' ),		
    		'supports' 			=> array( 
    			'title', 
    			'editor', 
    			'author', 
    			'thumbnail', 
    			'excerpt', 
    			/*'trackbacks',*/
				/*'custom-fields',*/
				'page-attributes', // for hierarchy
    			/*'comments',*/  
    			'revisions',
    			'post-formats' 
			),			
 		); 
 		
 		register_post_type($this->post_type, $args);
  
  		// Add new taxonomy, make it hierarchical (like categories)
  		$cat_labels = array(
	    	'name' 					=> _x( 'Document categories', 'document', 'idocs' ),
	    	'singular_name' 		=> _x( 'Document category', 'document', 'idocs' ),
	    	'search_items' 			=> __( 'Search document category', 'idocs' ),
	    	'all_items' 			=> __( 'All document categories', 'idocs' ),
	    	'parent_item' 			=> __( 'Document category parent', 'idocs' ),
	    	'parent_item_colon'		=> __( 'Document category parent:', 'idocs' ),
	    	'edit_item' 			=> __( 'Edit document category', 'idocs' ), 
	    	'update_item' 			=> __( 'Update document category', 'idocs' ),
	    	'add_new_item' 			=> __( 'Add new document category', 'idocs' ),
	    	'new_item_name' 		=> __( 'Document category name', 'idocs' ),
	    	'menu_name' 			=> __( 'Document categories', 'idocs' ),
		); 	

		register_taxonomy($this->taxonomy, array($this->post_type), array(
			'public'			=> true,
    		'show_ui' 			=> true,
			'show_in_nav_menus' => true,
			'show_admin_column' => true,		
			'hierarchical' 		=> true,
			'rewrite' 			=> array( 
				'slug' 			=> $this->taxonomy,
				'with_front'	=> false
			),
			'capabilities'		=> array('edit_posts'),		
    		'labels' 			=> $cat_labels,    		
    		'query_var' 		=> $this->taxonomy    		
  		));  		
	}
	
	/**
	 * Register meta boxes for document post type
	 * @param object $post - current post
	 */
	public function meta_boxes( $post ){
		// table of contents meta box options
		add_meta_box(
			'idocs-table-of-contents',
			__('Table of contents', 'idocs'),
			array( $this, 'meta_box_table_of_contents' ),
			$this->post_type,
			'side',
			'core'
		);		
	}
	
	/**
	 * Table of contents meta box output
	 * @param object $post - current post object
	 */
	public function meta_box_table_of_contents( $post ){
		$options = idocs_get_post_options( $post->ID );
		wp_nonce_field( 'idocs-save-toc-options', 'idocs_toc_nonce' );
?>		
<p>
<input type="checkbox" name="toc_show" value="1" <?php if( $options['toc_show'] ):?> checked="checked"<?php endif;?> />
<label for=""><?php _e('Show table of contents', 'idocs');?></label>
</p>

<p><label for=""><?php _e('Create table of contents from heading', 'idocs')?>:</label></p>
<input type="text" name="toc_heading" value="<?php echo $options['toc_heading'];?>" size="2" />

<p><label for=""><?php _e('Show table if h tags found exceeds', 'idocs');?>:</label></p>
<input type="text" name="toc_min_headings" value="<?php echo $options['toc_min_headings'];?>" size="2" />
<?php		
	}
	
	/**
	 * Store custom post options
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update
	 */
	public function save_document( $post_id, $post, $update ){
		if( !current_user_can('edit_post', $post_id) ){
			wp_die( __('You are not allowed to do this.', 'idocs' ) );
		}
		
		if( isset( $_POST['idocs_toc_nonce'] ) ){		
			check_admin_referer( 'idocs-save-toc-options', 'idocs_toc_nonce' );			
			idocs_update_post_options( $post_id );
		}	
	}
	
	/**
	 * Modify permalink to include taxonomy
	 * 
	 * @param string $url
	 * @param object $post
	 */
	public function modify_permalink($url, $post) {
	
		// limit to certain post type. remove if not needed
	    if ( $post->post_type != $this->post_type ) {
	        return $url;
	    }
	    
	    // fetches term
	    $term = get_the_terms( $post->ID, $this->taxonomy ); 
	    if ($term && count($term)) {
	    	$term = array_pop($term);
	        // takes only 1st one
	        $term = $term->slug;	      	
	    }else{
	    	$term = 'uncategorized';	
	    }
	        
	    return str_replace('%'.$this->taxonomy.'%', $term, $url);
	}
	
	/**
	 * On single doc display, prepend taxonomy name to title
	 * 
	 * Enter description here ...
	 * @param unknown_type $title
	 * @param unknown_type $sep
	 * @param unknown_type $seplocation
	 */
	public function prepend_taxonomy( $post_title, $post ){
		if( $this->post_type != $post->post_type ){
			return $post_title;
		}
		
		$terms = wp_get_post_terms( $post->ID, idocs_taxonomy(), array('fields'=>'names') );
		if($terms){
			return $terms[0].' - '.$post_title;
		}		
		return $post_title;
	}
	
	/**
	 * Add extra menu pages
	 */
	public function menu_pages(){
		
		$settings = add_submenu_page(
			'edit.php?post_type='.$this->post_type, 
			__('Settings', 'idocs'), 
			__('Settings', 'idocs'), 
			'manage_options', 
			'idocs_settings',
			array($this, 'plugin_settings'));

		$shortcodes = add_submenu_page(
			'edit.php?post_type='.$this->post_type, 
			__('Shortcodes', 'idocs'), 
			__('Shortcodes', 'idocs'), 
			'manage_options', 
			'idocs_shortcodes',
			array($this, 'plugin_shortcodes'));	
			
		add_action( 'load-'.$settings, array($this, 'plugin_settings_onload') );	
	}
	
	/**
	 * Output plugin settings page
	 */
	public function plugin_settings(){
		$options = idocs_settings();
		include IDOCS_PATH.'views/plugin_settings.php';
	}
	
	/**
	 * Process plugin settings
	 */
	public function plugin_settings_onload(){
		if( isset( $_POST['idocs_wp_nonce'] ) ){
			if( check_admin_referer('idocs-save-plugin-settings', 'idocs_wp_nonce') ){
				idocs_update_settings();								
			}
		}				
	}
	
	/**
	 * Output shortcodes page
	 */
	public function plugin_shortcodes(){
		global $IDOCS_SHORCODES;
		$shortcodes = $IDOCS_SHORCODES->get_shortcodes();
		
		include IDOCS_PATH.'views/plugin_shortcodes.php';
	}
	
	/**
	 * On taxonomy archive page, order docs by menu_order instead of publish date
	 */
	public function reorder_tax_docs($query){
		if ( is_tax( $this->taxonomy ) && !is_admin() && $query->is_main_query() ){
			// get plugin settings
			$query->set( 'order', 'ASC' );
			$query->set( 'orderby', 'menu_order title' );						
		}	
		
		if( is_admin() ){
			global $pagenow;			
			
			if( 'edit.php' == $pagenow && ( isset( $_GET['post_type'] ) && $this->post_type == $_GET['post_type'] ) &&  $query->is_main_query() ){
				$query->set( 'orderby', '' );
			}			
		}
		
		return $query;	
	}
	
	/**
	 * Modify join on adjacent post function to keep next/prev post navigation into the same category
	 * @param string $join
	 * @param bool $in_same_term
	 * @param array $excluded_terms
	 */
	public function adjacent_post_join($join, $in_same_term, $excluded_terms){
		global $post;
		if( $this->post_type != $post->post_type ){
			return $join;
		}
		
		$term_array = wp_get_object_terms( $post->ID, $this->taxonomy, array( 'fields' => 'ids' ) );
		if ( ! $term_array || is_wp_error( $term_array ) ){
			return $join;
		}	
		
		$join = "INNER JOIN wp_term_relationships AS tr 
				 ON p.ID = tr.object_id 				
				 INNER JOIN wp_term_taxonomy tt 
				 ON tr.term_taxonomy_id = tt.term_taxonomy_id 
				 AND tt.taxonomy = '{$this->taxonomy}' 
				 AND tt.term_id IN (". implode( ',', array_map( 'intval', $term_array ) ) .") ";
		
		return $join;
	}
	
	/**
	 * Modify where on adjacent post function to keep next/prev post navigation into the same category
	 * @param string $where
	 * @param bool $in_same_term
	 * @param array $excluded_terms
	 */
	public function adjacent_post_where($where, $in_same_term, $excluded_terms){
		global $post;
		if( $this->post_type != $post->post_type ){
			return $where;
		}
		
		$where.= " AND tt.taxonomy = '{$this->taxonomy}'";		
		return $where;
	}
	
	/**
	 * Extra columns in list table
	 * @param array $columns
	 */
	public function extra_columns( $columns ){		
		
		$cols = array();
		foreach( $columns as $c => $t ){
			$cols[$c] = $t;
			if( 'title' == $c ){
				$cols['menu_order'] = __('Order', 'idocs');	
			}	
		}		
		return $cols;
	}
	
	/**
	 * Extra columns in list table output
	 * @param string $column_name
	 * @param int $post_id
	 */
	public function output_extra_columns($column_name, $post_id){
		
		switch( $column_name ){
			case 'menu_order':
				$post = get_post( $post_id );
				echo $post->menu_order;
			break;			
		}
			
	}
	
	// Helpers
	
	/**
	 * Return post type name - use function idocs_post_type() instead
	 */
	public function get_post_type(){
		return $this->post_type;
	}
	
	/**
	 * Return taxonomy - use function idocs_taxonomy() instead
	 */
	public function get_taxonomy(){
		return $this->taxonomy;
	}
	
}
global $IDOCS;
$IDOCS = new iDocument();