<?php 
add_action( 'init', 'register_cpt_wp_automatic' );

function register_cpt_wp_automatic() {


	
	$labels = array(
			'name' => 'Campaigns',
			'all_items' => 'All Campaigns',
			'singular_name' => 'wp_automatic',
			'add_new' => 'New campaign' ,
			'add_new_item' => 'Add New Campaign',
			'edit_item' => 'Edit Campaign',
			'new_item' => 'New Campaign',
			'view_item' => 'View Campaign',
			'search_items' => 'Search Campaigns',
			'not_found' => 'No Campaigns found',
			'not_found_in_trash' => 'No Campaigns found in Trash',
			'parent_item_colon' => 'Parent Campaign:',
			'menu_name' => 'Automatic',
	);

	$icon  = plugins_url('/wp-automatic/images/ta.png');
 	$icon = "dashicons-admin-settings";
 	$icon = 'data:image/svg+xml;base64,' . base64_encode('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024"><path fill="#a7aaad" d="M413.802 191.458c-111.928 20.093-222.021 75.366-298.293 149.826-45.215 43.403-62.59 66.237-84.971 110.999-23.763 47.050-32.892 94.1-26.028 137.503 18.281 120.151 133.856 201.452 338.502 239.373 44.309 8.223 70.36 10.058 166.748 10.058 99.582 0.453 121.51-0.906 172.23-10.058 165.819-29.698 279.582-94.1 320.674-181.359 83.589-175.877-118.316-408.386-396.516-456.818-49.791-8.676-142.985-8.223-192.323 0.453zM342.083 495.709c45.215 16.899 80.395 51.173 80.395 79.036 0 30.604-29.698 46.597-71.719 38.374-68.525-12.799-126.992-64.878-113.287-100.511 11.87-31.51 48.885-36.992 104.611-16.899zM771.491 493.421c39.28 24.669 7.77 80.848-62.137 108.711-52.532 21.022-95.029 15.54-106.446-14.611-10.058-26.957 18.734-64.402 67.143-86.806 43.403-19.64 78.107-22.381 101.417-7.317z"></path>
</svg>');

 	
	$args = array(
			'labels' => $labels,
			'hierarchical' => false,
			'description' => 'Campains of Wordpress Automatic',
			'supports' => array( 'title' ),
			'taxonomies' => array( 'options' ),
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_nav_menus' => true,
		
			'menu_position' => 66666665666666666  ,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'has_archive' => false,
			'query_var' => true,
			'can_export' => true,
			'rewrite' => true,
			'capability_type' => 'post',
			'menu_icon'=>$icon
			
	);
	
	//admin only
	$admin_caps = array('capabilities' => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'delete_posts'       => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options'
	  ));
	
	
	$opt = get_option ( 'wp_automatic_options', array ('OPT_ADMIN_ONLY') );
	if (in_array ( 'OPT_ADMIN_ONLY', $opt )) {
		
		$args = array_merge($args,$admin_caps);
		
	} else {
	
	}
	
	
	
	register_post_type( 'wp_automatic', $args );
	
	
}

/* ------------------------------------------------------------------------*
 * CHANGING THE WAY DISPLAYED of the campaigns
* ------------------------------------------------------------------------*/

add_filter("manage_edit-wp_automatic_columns", "wp_automatic_edit_columns");
add_action("manage_posts_custom_column",  "wp_automatic_columns_display");

function wp_automatic_edit_columns($portfolio_columns){
	$portfolio_columns = array(
			"cb" => "<input type=\"checkbox\" />",
			"title" => "Campaign Title",
			"wp_automatic_type"=> "Type",
			"wp_automatic_keywords" => "Keywords/Source",
			"wp_automatic_Category" => "Category",
			"wp_automatic_status"=>"New Post Status",
			"wp_automatic_posted"=> "Posts / max",
			"wp_automatic_last_run" => "Last<br>run"


	);
	return $portfolio_columns;
}

function wp_automatic_columns_display($wp_automatic_columns){
	
 	
	global $wpdb;
	global $post;
	global $wpAutomaticTemp;
	
	$prefix=$wpdb->prefix;
	$id=$post_id=$post->ID;
	
	//check if already exists
	if(isset($wpAutomaticTemp->camp_id) && $wpAutomaticTemp->camp_id == $post_id){
		
		$ret=$wpAutomaticTemp;
		
	}else{
		//getting the record of the database
		$query="select * from {$prefix}automatic_camps where camp_id ='$id'";
		$res=$wpdb->get_results($query);
		if(! isset($res[0])) return;
		$ret=$res[0];
		$wpAutomaticTemp = $ret;
	}
	
	
	
	switch ($wp_automatic_columns)
	{

		case "wp_automatic_keywords":
			//getting the keyword
			 
			//Feeds or GoogleNews
			if(wp_automatic_trim($ret->camp_type) == 'Feeds' || wp_automatic_trim($ret->camp_type) == 'GoogleNews' ){
				
				if(strlen($ret->feeds) > 100){
					  echo substr($ret->feeds, 0,100).'...';
				}else{
					  echo $ret->feeds;
				}
				
				
				
			}elseif( wp_automatic_trim($ret->camp_type) == 'Facebook' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				  echo $camp_general['cg_fb_page'] ;
			}elseif( wp_automatic_trim($ret->camp_type) == 'Craigslist' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				  echo $camp_general['cg_cl_page'] ;
			
			}elseif( wp_automatic_trim($ret->camp_type) == 'Reddit' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				  echo $camp_general['cg_rd_page'] ;
			
			}elseif( $ret->camp_type == 'Single'){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_sn_source'] ;
			
			}elseif( $ret->camp_type == 'Multi' || $ret->camp_type == 'Multi-page scraper'){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				
				if(wp_automatic_trim( $camp_general['cg_ml_source'] ) == '' ){
					
					//cg_multi_posts_list
					$cg_multi_posts_list = $camp_general['cg_multi_posts_list'];
					
					//limit to 200 chars
					if(strlen($cg_multi_posts_list) > 200){
						echo substr($cg_multi_posts_list, 0,200).'...';

					}else{

						echo $camp_general['cg_multi_posts_list'];
					}
				}else{
				
					if(strlen($camp_general['cg_ml_source'] ) > 100){
						echo substr($camp_general['cg_ml_source'] , 0 , 100) . '...' ;
					}else{
						echo  ($camp_general['cg_ml_source']  )   ;
					}
				}
				
			}elseif( $ret->camp_type == 'Instagram' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_it_user'] ;
			
			}elseif( $ret->camp_type == 'Vimeo' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_vm_user'] ;
				
			}elseif( $ret->camp_type == 'Flicker' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_fl_user'] ;
			
			}elseif( $ret->camp_type == 'eBay' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_eb_user'] ;
			
			}elseif( $ret->camp_type == 'Pinterest' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_pt_user'] ;
				
			}elseif( $ret->camp_type == 'TikTok' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_tt_user'] ;
				
			}elseif( $ret->camp_type == 'SoundCloud' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_sc_user'] ;
				
			}elseif( $ret->camp_type == 'Envato' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo  $camp_general['cg_ev_author'] . $camp_general['cg_ev_tags'] . $camp_general['cg_ev_cat']    ;
			
			}elseif( $ret->camp_type == 'DailyMotion' && $ret->camp_keywords == '*' ){
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				echo $camp_general['cg_dm_user'] ;
				
			}elseif( $ret->camp_type == 'Youtube' && $ret->camp_keywords == '*' ){
			 
				echo $ret->camp_yt_user;

				//if camp_yt_user  is not set, echo cg_yt_playlist
				if(wp_automatic_trim($ret->camp_yt_user) == ''){
					$camp_general = unserialize (base64_decode( $ret->camp_general) );
					echo $camp_general['cg_yt_playlist'] ;
				}

			
			}elseif( $ret->camp_type == 'Amazon' && $ret->camp_keywords == '*' ){
				
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
			
				if(wp_automatic_trim($camp_general['cg_am_custom_urls']) != ''){
					echo $camp_general['cg_am_custom_urls'] ;
				}else{
					echo 'Custom HTML';
				}

			}elseif( $ret->camp_type == 'telegram'  ){
				
				$camp_general = unserialize (base64_decode( $ret->camp_general) );
				
				 
					echo $camp_general['cg_te_page'] ;
				 
				
			}else{
				
				if(strlen($ret->camp_keywords) > 100){
					echo 	substr($ret->camp_keywords, 0,100).'...';
				}else{
					echo $ret->camp_keywords;
				}
			}
			  
			break;

			case "wp_automatic_type":
			
				//if campaign type is gpt3, modify to ChatGPT Articles
				if($ret->camp_type == 'gpt3'){
					 $ret->camp_type = 'AI Articles';
				}

				//if Multi, modify it to multi-page scraper
				if($ret->camp_type == 'Multi'){
					$ret->camp_type = 'Multi-page scraper';
				}
				
				//echo uppsercase first letter of $ret->camp_type;				 
				echo ucfirst($ret->camp_type);

				//echo nonce for duplicate button hidden field 
				echo '<input type="hidden" name="wp_automatic_nonce_'. $post->ID .'" value="'.wp_create_nonce('wp_automatic_nonce').'" data-nonce-camp="'.$post->ID.'">';

				break;
					
		
		case "wp_automatic_Category":

			@$camp_post_category = $ret->camp_post_category ;
			if(isset($ret->camp_post_category)){
				$catname = get_cat_name ($camp_post_category);
				
				if(wp_automatic_trim($catname) != ''){
					echo $catname;
				}else{
					echo 'Default';
				}
				
			}else{
				echo 'Default';
			}
			break;

		case "wp_automatic_status":
			//getting posted count
			if(isset($ret->camp_keywords))   echo $ret->camp_post_status ;
			break;

		case "wp_automatic_posted":
			//getting posted count

			//getting posted count
			//  echo $ret->posted;
			@$key='Posted:'.$ret->camp_id;
			//getting count from wplb_log
			$query="select count(id) as count from {$prefix}automatic_log where action='$key'";
			$res= $wpdb->get_results($query);
			@$res=$res[0];
			if(isset($res->count))   echo $res->count;
			  echo ' / ';
			if(isset($ret->camp_post_every))   echo $ret->camp_post_every;
			break;
			
		case "wp_automatic_last_run":
			
			 
			//get the value of the record from postmeta table when the key is last_update and the post id is $id
			$query = "SELECT meta_value FROM {$prefix}postmeta WHERE meta_key = 'last_update' AND post_id = $id";

			//get the result
			$result = $wpdb->get_results($query);

			//get the value of the first row
			$last_run_timestamp = isset($result[0]->meta_value)? $result[0]->meta_value : '';
			
			 
			if(wp_automatic_trim($last_run_timestamp) != ''){
				echo human_time_diff($last_run_timestamp);
			}else{
				echo 'N/A';
			}
			
		
			break;


	}



}

/* ------------------------------------------------------------------------*
 * Custom Post Updated Message
* ------------------------------------------------------------------------*/
//add filter to ensure the text Book, or book, is displayed when user updates a book
add_filter('post_updated_messages', 'codex_book_updated_messages');
function show_links($id){
	$count=get_post_meta($id, 'links_added',1);
	if( $count != ''){
		return ' Additional Info:'.$count.' links added to be posted on the last time a file was uploaded.';
	}else{
		return '';
	}
}

function codex_book_updated_messages( $messages ) {
	global $post, $post_ID;

	$messages['wp_automatic'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( 'Campaign <b>updated</b> successfully.'  ),
			2 => 'Custom field updated.',
			3 => 'Custom field deleted.',
			4 => 'Campaign updated.',

			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( 'Campaign restored to revision from %s', wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf('Campaign  published %s ', show_links($post_ID) ),
			7 => 'Campaign saved.',
			8 => sprintf( 'Campaign submitted. %s',  show_links($post_ID)),
			9 => sprintf( 'Camapaign scheduled for: <strong>%1$s</strong>. %2$s',
					// translators: Publish box date format, see http://php.net/date
					date_i18n( 'M j, Y @ G:i' , strtotime( $post->post_date ) ),  show_links($post_ID) ),
			10 => sprintf( 'Campaign draft updated. %s',  show_links($post_ID)),

	);

	return $messages;
}


/*=============================================================
 *  Deactivate campaign button which set the campaign as draft
 * ============================================================*/
function register_custom_bulk_actions($actions) {
    global $post_type;
    
    if ($post_type === 'wp_automatic') {
        $actions['deactivate'] = 'Deactivate';
        $actions['activate'] = 'Activate';
    }
    
    return $actions;
}
add_filter('bulk_actions-edit-wp_automatic', 'register_custom_bulk_actions');

function handle_custom_bulk_actions($redirect_to, $doaction, $post_ids) {
    if (in_array($doaction, ['deactivate', 'activate'])) {
        $new_status = ($doaction === 'deactivate') ? 'draft' : 'publish';
        
        foreach ($post_ids as $post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => $new_status,
            ));
        }
        
        $redirect_to = add_query_arg($doaction . '_success', count($post_ids), $redirect_to);
    }
    
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-wp_automatic', 'handle_custom_bulk_actions', 10, 3);

function display_custom_success_notice() {
    if (isset($_REQUEST['deactivate_success'])) {
        $count = intval($_REQUEST['deactivate_success']);
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(_n('%s post has been deactivated.', '%s posts have been deactivated.', $count), $count) . '</p></div>';
    }
    
    if (isset($_REQUEST['activate_success'])) {
        $count = intval($_REQUEST['activate_success']);
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(_n('%s post has been activated.', '%s posts have been activated.', $count), $count) . '</p></div>';
    }
}
add_action('admin_notices', 'display_custom_success_notice');


?>