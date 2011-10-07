<?php
session_start(); 

/**
 * JW Post Types
 * @author Jeffrey Way
 * @link http://jeffrey-way.com
*/
class JW_Post_Type {

	/**
	* The name of the post type.
	* @var string
	*/
	public $post_type_name;

	/**
	 * A list of user-specifie options for the post type.
	 * @var array
	*/
	public $post_type_args;


	/**
	 * Sets default values, registers the passed post type, and
	 * listens for when the post is saved.
	 * 
	 * @param string $name The name of the desired post type.
	 * @param array @post_type_args Override the options.
	*/
	function __construct($name, $post_type_args = array() )
	{
		$this->post_type_name = strtolower($name);
		$this->post_type_args = (array) $post_type_args;

		// First step, register that new post type
		$this->init(array(&$this, "register_post_type"));
		$this->save_post();
	}

	/**
	* Helper method, that attaches a passed function to the 'init' WP action
	* @param function $cb Passed callback function.
	*/
	function init($cb)
	{
		add_action("init", $cb);
	}

	/**
	* Helper method, that attaches a passed function to the 'admin_init' WP action
	* @param function $cb Passed callback function.
	*/
	function admin_init($cb)
	{
		add_action("admin_init", $cb);
	}


	/**
	* Registers a new post type in the WP db.
	*/	
	function register_post_type()
	{
		$n = ucwords($this->post_type_name);
		
		$labels = array(
			"name" => _x(ucwords($n) . "s", "post type general name"),
			"singular_name" => _x("$n Item", "post type singular name"),
			"add_new" => _x("Add New", "$n item"),
			"add_new_item" => __("Add New $n"),
			"edit_item" => __("Edit $n Item"),
			"new_item" => __("New $n Item"),
			"view_item" => __("View $n Item"),
			"search_items" => __("Search $n"),
			"not_found" =>  __("Nothing found"),
			"not_found_in_trash" => __("Nothing found in Trash"),
			"parent_item_colon" => ""
		);

		$args = array(
			"labels" => $labels,
			"public" => true,
			"publicly_queryable" => true,
			"show_ui" => true,
			"query_var" => true,
			#"menu_icon" => get_stylesheet_directory_uri() . "/article16.png",
			"rewrite" => true,
			"capability_type" => "post",
			"hierarchical" => false,
			"menu_position" => null,
			"supports" => array("title","editor","thumbnail"),
			'has_archive' => true
		); 
		
		// Take user provided options, and override the defaults.
		$args = array_merge($args, $this->post_type_args);

		register_post_type( $this->post_type_name, $args );		
	}


	/**
	 * Registers a new taxonomy, associated with the instantiated post type.
	 * 
	 * @param string $taxonomy_name The name of the desired taxonomy
	 * @param string $plural The plural form of the taxonomy name. (Optional)
	 * @param array $options A list of overrides
	*/
	function add_taxonomy($taxonomy_name, $plural = '', $options = array() )
	{
		// Create local reference so we can pass it to the init cb.
		$post_type_name = $this->post_type_name;

		// If no plural form of the taxonomy was provided, do a crappy fix. :)
		if ( empty($plural) ) {
			$plural = $taxonomy_name . 's';
		}

		// Taxonomies need to be lowercase, but displaying them will look better this way...
		$taxonomy_name = ucwords($taxonomy_name);

		// At WordPress' init, register the taxonomy
		$this->init(
				function() use($taxonomy_name, $plural, $post_type_name, $options) {
					$labels = array(
				    "name" => _x( ucwords($taxonomy_name), "taxonomy general name" ),
				    "singular_name" => _x( $taxonomy_name, "taxonomy singular name" ),
				    "search_items" =>  __( "Search $taxonomy_name" ),
				    "popular_items" => __( "Popular $taxonomy_name" ),
				    "all_items" => __( "All $taxonomy_name" ),
				    "parent_item" => null,
				    "parent_item_colon" => null,
				    "edit_item" => __( "Edit $plural" ), 
				    "update_item" => __( "Update $plural" ),
				    "add_new_item" => __( "Add New $taxonomy_name" ),
				    "new_item_name" => __( "New $taxonomy_name" ),
				    "separate_items_with_commas" => __( "Separate $taxonomy_name with commas" ),
				    "add_or_remove_items" => __( "Add o`r remove $taxonomy_name" ),
				    "choose_from_most_used" => __( "Choose from the most used $taxonomy_name" ),
				    "menu_name" => __( $taxonomy_name ),
				  ); 

				  // Override defaults with user provided options
				  $options = array_merge(
				  	array(
					    "hierarchical" => false,
					    "labels" => $labels,
					    "show_ui" => true,
					    "query_var" => true,
					    "rewrite" => array( "slug" => strtolower($taxonomy_name) )
						),
						$options		  
					);

					// name of taxonomy, associated post type, options
				  register_taxonomy(strtolower($taxonomy_name), $post_type_name, $options);
		});
	}


	/**
	* Creates a new custom meta box in the New 'post_type' page.
	* 
	* @param string $title
	* @param array $form_fields Associated array that contains the label of the input, and the desired input type. 'Title' => 'text'
	*/
	function add_meta_box($title, $form_fields = array() )
	{
		$post_type_name = $this->post_type_name;

		// At WordPress' admin_init action, add any applicable metaboxes.
		$this->admin_init(function() use($title, $form_fields, $post_type_name) {
			add_meta_box(
				strtolower(str_replace(' ', '_', $title)), // id
				$title, // title
				function($post, $data) { // function that displays the form fields
					global $post;

					// List of all the specified form fields
					$inputs = $data['args'][0];

					// Get the saved field values
					$meta = get_post_custom($post->ID);

					// For each form field specified, we need to create the necessary markup
					// $name = Label, $type = the type of input to create
					foreach ($inputs as $name => $type) {
						#'Happiness Info' in 'Snippet Info' box becomes
						# snippet_info_happiness_level
						$id_name = $data['id'] . '_' . strtolower(str_replace(' ', '_', $name));

						if( is_array($inputs[$name]) ) {
							// then it must be a select
							// filter through them, and create options
							$select = "<select name='$id_name' class='widefat'>";
							foreach ($inputs[$name][1] as $option) {
								// if what's stored in the db is equal to the
								// current value in the foreach, that should
								// be the selected one

								if ( isset($meta[$id_name]) && $meta[$id_name][0] == $option) {
									$set_selected = "selected='selected'";
								} else $set_selected = '';
						
								$select .= "<option value='$option' $set_selected> $option </option>";
							}
							$select .= "</select>";
							array_push($_SESSION['taxonomy_data'], 'select_difficulty');
						} else {
							$select = '<select><option>--</option></select>';
						}
						
						// Attempt to set the value of the input, based on what's saved in the db.
						$value = isset($meta[$id_name][0]) ? $meta[$id_name][0] : '';

						// Sorta sloppy. I need a way to access all these form fields later on.
						// I had trouble finding an easy way to pass these values around, so I'm
						// storing it in a session. Fix eventually.
						array_push($_SESSION['taxonomy_data'], $id_name);

						// TODO - Add the other input types.
						$lookup = array(
							"text" => "<input type='text' name='$id_name' value='$value' class='widefat' />",
							"textarea" => "<textarea name='$id_name' class='widefat' rows='10'>$value</textarea>",
							"select" => $select
						);
						?>

						<p>
							<label><?php echo ucwords($name) . ':'; ?></label><br />
							<?php echo $lookup[is_array($type) ? $type[0] : $type]; ?>
							
						</p>
						<?php
					}
				},
				$post_type_name, // associated post type
				'normal', // location/context. normal, side, etc.
				'default', // priority level
				array($form_fields) // optional passed arguments. 
			); // end add_meta_box
		});
	}


	/**
	* When a post saved/updated in the database, this methods updates the meta box params in the db as well.
	*/
	function save_post()
	{
		add_action('save_post', function()  {
			global $post;		

			// Get all the form fields that were saved in the session,
			// and update their values in the db.
			foreach ($_SESSION['taxonomy_data'] as $form_name) {
				if ( isset($_POST[$form_name]) ) {
					update_post_meta($post->ID, $form_name, $_POST[$form_name]);
				}
			}			

		});
	}
}

/*********/
/* USAGE */
/*********/

// $product = new PostType("movie");
// $product->add_taxonomy('Actor');
// $product->add_taxonomy('Director');
// $product->add_meta_box('Movie Info', array(
// 	'name' => 'text',
// 	'rating' => 'text',
// 	'review' => 'textarea'

// ));
