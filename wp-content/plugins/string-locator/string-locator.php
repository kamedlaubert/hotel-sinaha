<?php
/**
 * Plugin Name: String Locator
 * Plugin URI: http://www.clorith.net/wordpress-string-locator/
 * Description: Scan through theme and plugin files looking for text strings
 * Version: 1.8.2
 * Author: Clorith
 * Author URI: http://www.clorith.net
 * Text Domain: string-locator
 * License: GPL2
 *
 * Copyright 2013 Marius Jensen (email : marius@jits.no)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class String_Locator
{
	/**
	 * @var string $string_locator_language The code language used for the editing page
	 * @var string $version String Locator version number
	 * @var array  $notice Array containing all notices to display
	 * @var bool   $failed_edit Has there been a failed edit
	 * @var string $plugin_url The URL to the plugins directory
	 */
	public  $string_locator_language = '';
	public  $version                 = '1.8.2';
	public  $notice                  = array();
	public  $failed_edit             = false;
	private $plugin_url              = '';
	private $path_to_use             = '';
	private $bad_http_codes          = array( '500' );
	private $excerpt_length          = 25;

    /**
     * Construct the plugin
     */
    function __construct()
    {
		/**
		 * Define class variables requiring expressions
		 */
		$this->plugin_url     = plugin_dir_url( __FILE__ );
	    $this->path_to_use    = ( is_multisite() ? 'network/admin.php' : 'tools.php' );
	    $this->excerpt_length = apply_filters( 'string_locator_excerpt_length', 25 );

		add_action( 'admin_menu', array( $this, 'populate_menu' ) );
	    add_action( 'network_admin_menu', array( $this, 'populate_network_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 11 );

		add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );

		add_action( 'admin_init', array( $this, 'editor_save' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );

	    add_action( 'wp_ajax_string-locator-get-directory-structure', array( $this, 'ajax_get_directory_structure' ) );
	    add_action( 'wp_ajax_string-locator-search', array( $this, 'ajax_file_search' ) );
	    add_action( 'wp_ajax_string-locator-clean', array( $this, 'ajax_clean_search' ) );
    }

	/**
	 * Create a set of drop-down options for picking one of the available themes
	 *
	 * @param string $current The current selection option to match against
	 *
	 * @return string
	 */
	function get_themes_options( $current = null ) {
		$options = sprintf(
			'<option value="%s" %s>&mdash; %s &mdash;</option>',
			't--',
			( $current == 't--' ? 'selected="selected"' : '' ),
			esc_html( __( 'All themes', 'string-locator' ) )
		);

		$string_locate_themes = wp_get_themes();

		foreach( $string_locate_themes AS $string_locate_theme_slug => $string_locate_theme ) {
			$string_locate_theme_data = wp_get_theme( $string_locate_theme_slug );
			$string_locate_value = 't-' . $string_locate_theme_slug;

			$options .= sprintf(
				'<option value="%s" %s>%s</option>',
				$string_locate_value,
				( $current == $string_locate_value ? 'selected="selected"' : '' ),
				$string_locate_theme_data->Name
			);
		}

		return $options;
	}

	/**
	 * Create a set of drop-down options for picking one of the available plugins
	 *
	 * @param string $current The current selection option to match against
	 *
	 * @return string
	 */
	function get_plugins_options( $current = null ) {
		$options = sprintf(
				'<option value="%s" %s>&mdash; %s &mdash;</option>',
				'p--',
				( $current == 'p--' ? 'selected="selected"' : '' ),
				esc_html( __( 'All plugins', 'string-locator' ) )
		);

		$string_locate_plugins = get_plugins();

		foreach( $string_locate_plugins AS $string_locate_plugin_path => $string_locate_plugin )
		{
			$string_locate_value = 'p-' . $string_locate_plugin_path;

			$options .= sprintf(
				'<option value="%s" %s>%s</option>',
				$string_locate_value,
				( $current == $string_locate_value ? 'selected="selected"' : '' ),
				$string_locate_plugin['Name']
			);
		}

		return $options;
	}

	/**
	 * Handles the AJAX request to prepare the search hierarchy
	 *
	 * @return object
	 */
	function ajax_get_directory_structure() {
		if ( ! check_ajax_referer( 'string-locator-search', 'nonce', false ) ) { wp_send_json_error( __( 'Authentication failed', 'string-locator' ) ); }

		$scan_path = $this->prepare_scan_path( $_POST['directory'] );
		$files = $this->ajax_scan_path( $scan_path->path );

		/*
		 * Make sure each chunk of file arrays never exceeds 500 files
		 * This is to prevent the SQL string from being too large and crashing everything
		 */
		$file_chunks = array_chunk( $files, apply_filters( 'string-locator-files-per-array', 500 ), true );

		$store = (object) array(
			'scan_path' => $scan_path,
			'search'    => wp_unslash( $_POST['search'] ),
			'directory' => $_POST['directory'],
			'chunks'    => count( $file_chunks )
		);

		$response = array(
			'total'     => count( $files ),
			'current'   => 0,
			'directory' => $scan_path,
			'chunks'    => count( $file_chunks )
		);

		update_option( 'string-locator-search-overview', serialize( $store ), true );
		update_option( 'string-locator-search-history', serialize( array() ) );

		foreach( $file_chunks AS $count => $file_chunk ) {
			update_option( 'string-locator-search-files-' . $count, serialize( $file_chunk ) );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Search an individual file supplied va AJAX
	 *
	 * @return object
	 */
	function ajax_file_search() {
		if ( ! check_ajax_referer( 'string-locator-search', 'nonce', false ) ) { wp_send_json_error( __( 'Authentication failed', 'string-locator' ) ); }

		$files_per_chunk = apply_filters( 'string-locator-files-per-array', 500 );
		$filenum         = absint( $_POST['filenum'] );
		$next_file       = $filenum + 1;

		$next_chunk = ( ceil( ( $next_file ) / $files_per_chunk ) - 1 );
		$chunk      = ( ceil( $filenum / $files_per_chunk ) - 1 );
		if ( $chunk < 0 ) {
			$chunk = 0;
		}
		if ( $next_chunk < 0 ) {
			$next_chunk = 0;
		}

		$scan_data = unserialize( get_option( 'string-locator-search-overview' ) );
		$file_data = unserialize( get_option( 'string-locator-search-files-' . $chunk ) );

		if ( ! isset( $file_data[ $filenum ] ) ) {
			$chunk++;
			$file_data = unserialize( get_option( 'string-locator-search-files-' . $chunk ) );
		}
		if ( ! isset( $file_data[ $filenum ] ) ) {
			wp_send_json_error( sprintf( __( 'The file-number, %d, that was sent could not be found.', 'string-locator' ), $filenum ) );
		}

		$file_name = explode( "/", $file_data[ $filenum ] );
		$file_name = end( $file_name );

		$search_results = $this->scan_file( $file_data[ $filenum ], $scan_data->search, $file_data[ $filenum ], $scan_data->scan_path->type, '' );

		$response = array(
			'last_file' => $file_data[ $filenum ],
			'filenum'   => $filenum,
			'search'    => $search_results,
			'filename'  => $file_name
		);

		if ( $next_chunk != $chunk ) {
			$file_data = unserialize( get_option( 'string-locator-search-files-' . $next_chunk ) );
		}

		$response['next_file'] = ( isset( $file_data[ $next_file ] ) ? $file_data[ $next_file ] : '' );

		if ( ! empty( $search_results ) ) {
			$history = maybe_unserialize( get_option( 'string-locator-search-history', array() ) );
			$history = array_merge( $history, $search_results );
			update_option( 'string-locator-search-history', serialize( $history ), false );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Clean up our options used to help during the search
	 *
	 * @return object
	 */
	function ajax_clean_search() {
		if ( ! check_ajax_referer( 'string-locator-search', 'nonce', false ) ) { wp_send_json_error( __( 'Authentication failed', 'string-locator' ) ); }

		$scan_data = unserialize( get_option( 'string-locator-search-overview' ) );
		for( $i = 0; $i < $scan_data->chunks; $i++ ) {
			delete_option( 'string-locator-search-files-' . $i );
		}

		wp_send_json_success( true );
	}

	/**
	 * Create a table row for insertion into the search results list
	 *
	 * @param array|object $item The table row item
	 *
	 * @return string
	 */
	function prepare_table_row( $item ) {
		if ( ! is_object( $item ) ) {
			$item = (object) $item;
		}

		return sprintf(
			'<tr>
                <td>%1$s<div class="row-actions"><span class="edit"><a href="%2$s" aria-label="Edit">Edit</a></span></div></td>
                <td><a href="%2$s">%3$s</a></td>
                <td>%4$d</td>
            </tr>',
			$item->stringresult,
			esc_url( $item->editurl ),
			esc_html( $item->filename_raw ),
			esc_html( $item->linenum )
		);
	}

	/**
	 * Create a full table populated with the supplied items
	 *
	 * @param array $items An array of table rows
	 * @param array $table_class An array of items to append to the table class along with the defaults
	 *
	 * @return string
	 */
	function prepare_full_table( $items, $table_class = array() ) {
		$table_class = array_merge( $table_class, array(
			'wp-list-table',
			'widefat',
			'fixed',
			'striped',
			'tools_page_string-locator'
		) );

		$table_columns = sprintf(
			'<tr>
				<th scope="col" class="manage-column column-stringresult column-primary">%s</th>
				<th scope="col" class="manage-column column-filename">%s</th>
				<th scope="col" class="manage-column column-linenum">%s</th>
			</tr>',
			esc_html( __( 'String', 'string-locator' ) ),
			esc_html( __( 'File', 'string-locator' ) ),
			esc_html( __( 'Line number', 'string-locator' ) )
		);

		$table_rows = array();
		foreach( $items AS $item ) {
			$table_rows[] = $this->prepare_table_row( $item );
		}

		$table = sprintf(
			'<div class="tablenav top"><br class="clear"></div><table class="%s"><thead>%s</thead><tbody>%s</tbody><tfoot>%s</tfoot></table>',
			implode( ' ', $table_class ),
			$table_columns,
			implode( "\n", $table_rows ),
			$table_columns
		);

		return $table;
	}

	/**
	 * Create an admin edit link for the supplied path
	 *
	 * @param string $path
	 * @param int $line
	 *
	 * @return string
	 */
	function create_edit_link( $path, $line = 0 ) {
		$file_type    = 'core';
		$file_slug    = '';
		$content_path = str_replace( '\\', '/', WP_CONTENT_DIR );

		$path      = str_replace( '\\', '/', $path );
		$paths     = explode( '/', $path );

		$url_args = array(
			'page=string-locator',
			'edit-file=' . end( $paths )
		);

		switch ( true ) {
			case ( in_array( 'wp-content', $paths ) && in_array( 'plugins', $paths ) ) :
				$file_type = 'plugin';
				$content_path .= '/plugins/';
				break;
			case ( in_array( 'wp-content', $paths ) && in_array( 'themes', $paths ) ) :
				$file_type = 'theme';
				$content_path .= '/themes/';
				break;
		}

		$rel_path  = str_replace( $content_path, '', $path );
		$rel_paths = explode( '/', $rel_path );

		if ( 'core' != $file_type ) {
			$file_slug = $rel_paths[0];
		}

		$url_args[] = 'file-reference=' . $file_slug;
		$url_args[] = 'file-type=' . $file_type;
		$url_args[] = 'string-locator-line=' . absint( $line );
		$url_args[] = 'string-locator-path=' . urlencode( str_replace( '/', DIRECTORY_SEPARATOR, $path ) );

		$url = admin_url( $this->path_to_use . '?' . implode( '&', $url_args ) );

		return $url;
	}

	/**
	 * Parse the search option to determine what kind of search we are performing and what directory to start in
	 *
	 * @param $option
	 *
	 * @return bool|object
	 */
	function prepare_scan_path( $option ) {
		$data = array(
			'path' => '',
			'type' => '',
			'slug' => ''
		);

		switch ( true ) {
			case ( 't--' == $option ):
				$data['path'] = WP_CONTENT_DIR . '/themes/';
				$data['type'] = 'theme';
				break;
			case ( strlen( $option ) > 3 && 't-' == substr( $option, 0, 2 ) ):
				$data['path'] = WP_CONTENT_DIR . '/themes/' . substr( $option, 2 );
				$data['type'] = 'theme';
				$data['slug'] = substr( $option, 2 );
				break;
			case ( 'p--' == $option ):
				$data['path'] = WP_CONTENT_DIR . '/plugins/';
				$data['type'] = 'plugin';
				break;
			case ( strlen( $option ) > 3 && 'p-' == substr( $option, 0, 2 ) ):
				$slug = explode( '/', substr( $option, 2 ) );

				$data['path'] = WP_CONTENT_DIR . '/plugins/' . $slug[0];
				$data['type'] = 'plugin';
				$data['slug'] = $slug[0];
				break;
			case ( 'core' == $option ):
				$data['path'] = ABSPATH;
				$data['type'] = 'core';
				break;
			case ( 'wp-content' == $option ):
				$data['path'] = WP_CONTENT_DIR;
				$data['type'] = 'core';
				break;
		}

		if ( empty( $data['path'] ) ) {
			return false;
		}

		return (object) $data;
	}

	/**
	 * Check if a file path is valid for editing
	 *
	 * @param string $path Path to file
	 * @return bool
	 */
	function is_valid_location( $path ) {
		$valid   = true;
		$path    = str_replace( array( '/' ), array( DIRECTORY_SEPARATOR ), stripslashes( $path ) );
		$abspath = str_replace( array( '/' ), array( DIRECTORY_SEPARATOR ), ABSPATH );

		if ( empty( $path ) ) {
			$valid = false;
		}
		if ( stristr( $path, '..' ) ) {
			$valid = false;
		}
		if ( ! stristr( $path, $abspath ) ) {
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Set the text domain for translated plugin content
	 */
	function load_i18n() {
		$i18n_dir = 'string-locator/languages/';
		load_plugin_textdomain( 'string-locator', false, $i18n_dir );
	}

	/**
	 * Load up JavaScript and CSS for our plugin on the appropriate admin pages
	 */
	function admin_enqueue_scripts( $hook ) {
		// Break out early if we are not on a String Locator page
		if ( 'tools_page_string-locator' != $hook && 'toplevel_page_string-locator' != $hook ) {
			return;
		}

		/**
		 * String Locator Styles
		 */
		wp_register_style( 'string-locator', plugin_dir_url( __FILE__ ) . '/resources/css/string-locator.css', array(), $this->version );

		/**
		 * String Locator Scripts
		 */
		wp_register_script( 'string-locator-search', plugin_dir_url( __FILE__ ) . '/resources/js/string-locator-search.js', array( 'jquery' ), $this->version );

		if ( isset( $_GET['edit-file'] )) {
			$filename = explode( '.', $_GET['edit-file'] );
			$filext = end( $filename );
			switch( $filext ) {
				case 'js':
					$this->string_locator_language = 'javascript';
					break;
				case 'php':
					$this->string_locator_language = 'application/x-httpd-php';
					break;
				case 'css':
					$this->string_locator_language = 'css';
					break;
				default:
					$this->string_locator_language = 'htmlmixed';
			}

			/**
			 * CodeMirror Styles
			 */
			wp_register_style( 'codemirror', plugin_dir_url( __FILE__ ) . '/resources/css/codemirror.css', array( 'codemirror-lint' ), $this->version );
			wp_register_style( 'codemirror-twilight', plugin_dir_url( __FILE__ ) . '/resources/css/codemirror/twilight.css', array( 'codemirror' ), $this->version );
			wp_register_style( 'codemirror-lint', plugin_dir_url( __FILE__ ) . '/resources/js/codemirror/addon/lint/lint.css', array(), $this->version );

			/**
			 * CodeMirror Scripts
			 */
			wp_register_script( 'codemirror-addon-edit-closebrackets', $this->plugin_url . '/resources/js/codemirror/addon/edit/closebrackets.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-addon-edit-matchbrackets', $this->plugin_url . '/resources/js/codemirror/addon/edit/matchbrackets.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-addon-selection-active-line', $this->plugin_url . '/resources/js/codemirror/addon/selection/active-line.js', array( 'codemirror' ), $this->version, true );

			wp_register_script( 'codemirror-addon-lint-css', $this->plugin_url . '/resources/js/codemirror/addon/lint/lint.js', array( 'codemirror' ), $this->version, true );

			wp_register_script( 'codemirror-mode-javascript', $this->plugin_url . '/resources/js/codemirror/mode/javascript/javascript.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-mode-htmlmixed', $this->plugin_url . '/resources/js/codemirror/mode/htmlmixed/htmlmixed.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-mode-clike', $this->plugin_url . '/resources/js/codemirror/mode/clike/clike.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-mode-xml', $this->plugin_url . '/resources/js/codemirror/mode/xml/xml.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-mode-css', $this->plugin_url . '/resources/js/codemirror/mode/css/css.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror-mode-php', $this->plugin_url . '/resources/js/codemirror/mode/php/php.js', array( 'codemirror' ), $this->version, true );
			wp_register_script( 'codemirror', $this->plugin_url . '/resources/js/codemirror/lib/codemirror.js', array(), $this->version, true );

			/**
			 * String Locator Scripts
			 */
			wp_register_script( 'string-locator-editor', $this->plugin_url . '/resources/js/string-locator.js', array( 'codemirror' ), $this->version, true );

			/**
			 * CodeMirror Enqueue
			 */
			wp_enqueue_style( 'codemirror-twilight' );

			wp_enqueue_script( 'codemirror-addon-edit-closebrackets' );
			wp_enqueue_script( 'codemirror-addon-edit-matchbrackets' );
			wp_enqueue_script( 'codemirror-addon-selection-active-line' );
			wp_enqueue_script( 'codemirror-addon-lint' );

			wp_enqueue_script( 'codemirror-mode-javascript' );
			wp_enqueue_script( 'codemirror-mode-htmlmixed' );
			wp_enqueue_script( 'codemirror-mode-clike' );
			wp_enqueue_script( 'codemirror-mode-xml' );
			wp_enqueue_script( 'codemirror-mode-css' );
			wp_enqueue_script( 'codemirror-mode-php' );

			/**
			 * String Locator Enqueue
			 */
			wp_enqueue_script( 'string-locator-editor' );
		}

		/**
		 * String Locator Enqueue
		 */
		wp_enqueue_style( 'string-locator' );

		wp_enqueue_script( 'string-locator-search' );
		wp_localize_script( 'string-locator-search', 'string_locator', array(
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'search_nonce'          => wp_create_nonce( 'string-locator-search' ),
			'saving_results_string' => __( 'Saving search results&hellip;', 'string-locator' )
		) );
	}

    /**
     * Add our plugin to the 'Tools' menu
     */
    function populate_menu()
    {
	    if ( is_multisite() ) {
		    return;
	    }
        $page_title  = __( 'String Locator', 'string-locator' );
        $menu_title  = __( 'String Locator', 'string-locator' );
        $capability  = 'edit_themes';
        $parent_slug = 'tools.php';
        $menu_slug   = 'string-locator';
        $function    = array( $this, 'options_page' );

	    add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    }

	function populate_network_menu()
	{
		$page_title  = __( 'String Locator', 'string-locator' );
		$menu_title  = __( 'String Locator', 'string-locator' );
		$capability  = 'edit_themes';
		$menu_slug   = 'string-locator';
		$function    = array( $this, 'options_page' );

		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, 'dashicons-edit' );
	}

    /**
     * Function for including the actual plugin Admin UI page
     */
    function options_page()
    {
		/**
		 * Don't load anything if the user can't edit themes any way
		 */
		if ( ! current_user_can( 'edit_themes' ) ) {
			return false;
		}

		/**
		 * Show the edit page if;
		 * - The edit file path query var is set
		 * - The edit file path query var isn't empty
		 * - The edit file path query var does not contains double dots (used to traverse directories)
		 */
		if ( isset( $_GET['string-locator-path'] ) && $this->is_valid_location( $_GET['string-locator-path'] ) ) {
			include_once( dirname( __FILE__ ) . '/editor.php' );
		}
		else {
			include_once( dirname( __FILE__ ) . '/options.php' );
		}
    }

	/**
	 * @param string $start Start delimited
	 * @param string $end End delimiter
	 * @param string $string The string to scan
	 * @return array
	 */
	function SmartScan( $start, $end, $string ) {
		$opened = array();

		$lines = explode( "\n", $string );
		for ( $i = 0; $i < count( $lines ); $i++ ) {
			if ( stristr( $lines[$i], $start ) ) {
				$opened[] = $i;
			}
			if ( stristr( $lines[$i], $end ) ) {
				array_pop( $opened );
			}
		}

		return $opened;
	}

	/**
	 * Handler for storing the content of the code editor
	 * Also runs over the Smart-Scan if enabled
	 */
	function editor_save() {
		if ( isset( $_POST['string-locator-editor-content'] ) && check_admin_referer( 'string-locator-edit_' . $_GET['edit-file'] ) && current_user_can( 'edit_themes' ) ) {

			if ( $this->is_valid_location( $_GET['string-locator-path'] ) ) {
				$path = urldecode( $_GET['string-locator-path'] );
				$content = stripslashes( $_POST['string-locator-editor-content'] );

				/**
				 * Send an error notice if the file isn't writable
				 */
				if ( ! is_writeable( $path ) ) {
					$this->notice[] = array(
						'type'    => 'error',
						'message' => __( 'The file could not be written to, please check file permissions or edit it manually.', 'string-locator' )
					);
					$this->failed_edit = true;
					return;
				}

				/**
				 * If enabled, run the Smart-Scan on the content before saving it
				 */
				if ( isset( $_POST['string-locator-smart-edit'] ) ) {
					$open_brace = substr_count( $content, '{' );
					$close_brace = substr_count( $content, '}' );
					if ( $open_brace != $close_brace ) {
						$this->failed_edit = true;

						$opened = $this->SmartScan( '{', '}', $content );

						foreach( $opened AS $line ) {
							$this->notice[] = array(
								'type'    => 'error',
								'message' => sprintf(
									__( 'There is an inconsistency in the opening and closing braces, { and }, of your file on line %s', 'string-locator' ),
									'<a href="#" class="string-locator-edit-goto" data-goto-line="' . ( $line + 1 ). '">' . ( $line + 1 ) . '</a>'
								)
							);
						}
					}

					$open_bracket = substr_count( $content, '[' );
					$close_bracket = substr_count( $content, ']' );
					if ( $open_bracket != $close_bracket ) {
						$this->failed_edit = true;

						$opened = $this->SmartScan( '[', ']', $content );

						foreach( $opened AS $line ) {
							$this->notice[] = array(
								'type'    => 'error',
								'message' => sprintf(
									__( 'There is an inconsistency in the opening and closing braces, [ and ], of your file on line %s', 'string-locator' ),
									'<a href="#" class="string-locator-edit-goto" data-goto-line="' . ( $line + 1 ). '">' . ( $line + 1 ) . '</a>'
								)
							);
						}
					}

					$open_parenthesis  = substr_count( $content, '(' );
					$close_parenthesis = substr_count( $content, ')' );
					if ( $open_parenthesis != $close_parenthesis ) {
						$this->failed_edit = true;

						$opened = $this->SmartScan( '(', ')', $content );

						foreach( $opened AS $line ) {
							$this->notice[] = array(
								'type'    => 'error',
								'message' => sprintf(
									__( 'There is an inconsistency in the opening and closing braces, ( and ), of your file on line %s', 'string-locator' ),
									'<a href="#" class="string-locator-edit-goto" data-goto-line="' . ( $line + 1 ). '">' . ( $line + 1 ) . '</a>'
								)
							);
						}
					}

					if ( $this->failed_edit ) {
						return;
					}
				}

				$original = file_get_contents( $path );

				if ( isset( $_POST['string-locator-make-child-theme'] ) ) {
					$child_theme = $this->create_child_theme( $_GET['file-reference'] );
				}

				$this->write_file( $path, $content );

				/**
				 * Check the status of the site after making our edits.
				 * If the site fails, revert the changes to return the sites to its original state
				 */
				$header = wp_remote_head( site_url() );
				if ( 301 == $header['response']['code'] ) {
					$header = wp_remote_head( $header['headers']['location'] );
				}

				if ( in_array( $header['response']['code'], $this->bad_http_codes ) ) {
					$this->failed_edit = true;
					$this->write_file( $path, $original );

					$this->notice[] = array(
						'type'    => 'error',
						'message' => __( 'A 500 server error was detected on your site after updating your file. We have restored the previous version of the file for you.', 'string-locator' )
					);
				}
				else {
					$this->notice[] = array(
						'type'    => 'updated',
						'message' => __( 'The file has been saved', 'string-locator' )
					);
				}
			}
		}
	}

	private function create_child_theme( $theme ) {
		$child_theme = sprintf( '%s/%s-child', get_theme_root(), $theme );
		mkdir( $child_theme );

		touch( $child_theme . '/functions.php' );
		touch( $child_theme . '/style.css' );

		return $child_theme;
	}

	/**
	 * When editing a file, this is where we write all the new content
	 * We will break early if the user isn't allowed to edit files
	 *
	 * @param string $path - The path to the file
	 * @param string $content - The content to write to the file
	 */
	private function write_file( $path, $content ) {
		if ( ! current_user_can( 'edit_themes' ) ) {
			return;
		}

		if ( apply_filters( 'string-locator-filter-closing-php-tags', true ) ) {
			$content = preg_replace( "/\?>$/si", '', trim( $content ), -1, $replaced_strings );

			if ( $replaced_strings >= 1 ) {
				$this->notice[] = array(
					'type'    => 'error',
					'message' => __( 'We detected a PHP code tag ending, this has been automatically stripped out to help prevent errors in your code.', 'string-locator' )
				);
			}
		}

		$file        = fopen( $path, "w" );
		$lines       = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $content ) );
		$total_lines = count( $lines );

		for( $i = 0; $i < $total_lines; $i++ ) {
			$write_line = $lines[ $i ];

			if ( ( $i + 1 ) < $total_lines ) {
				$write_line .= PHP_EOL;
			}

			fwrite( $file, $write_line );
		}

		fclose( $file );
	}

	/**
	 * Hook the admin notices and loop over any notices we've registered in the plugin
	 */
	function admin_notice() {
		if ( ! empty( $this->notice ) ) {
			foreach( $this->notice AS $note ) {
				printf(
					'<div class="%s"><p>%s</p></div>',
					esc_attr( $note['type'] ),
					esc_html( $note['message'] )
				);
			}
		}
	}

	/**
	 * Scan through an individual file to look for occurrences of £string
	 *
	 * @param string $filename - The path to the file
	 * @param string $string - The search string
	 * @param mixed $location - The file location object/string
	 * @param string $type - File type
	 * @param string $slug - The plugin/theme slug of the file
	 *
	 * @return string
	 */
	function scan_file( $filename, $string, $location, $type, $slug ) {
		if ( empty( $string ) ) {
			return false;
		}
		$output = array();
		$linenum = 0;
		$match_count = 0;

		if ( ! is_object( $location ) ) {
			$path = $location;
			$location = explode( DIRECTORY_SEPARATOR, $location );
			$file = end( $location );
		}
		else {
			$path = $location->getPathname();
			$file = $location->getFilename();
		}

		/*
		 * Check if the filename matches our search pattern
		 */
		if ( stristr( $file, $string ) ) {
			$relativepath = str_replace( array( ABSPATH, '\\', '/' ), array( '', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR ), $path );
			$match_count++;

			$editurl = $this->create_edit_link( $path, $linenum );

			$path_string = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $editurl ),
					esc_html( $relativepath )
			);

			$output[] = array(
				'ID'           => $match_count,
				'linenum'      => 0,
				'path'         => $path,
				'filename'     => $path_string,
				'filename_raw' => $relativepath,
				'editurl'      => $editurl,
				'stringresult' => $file
			);
		}

		$readfile = fopen( $filename, "r" );
		if ( $readfile )
		{
			while ( ( $readline = fgets( $readfile ) ) !== false )
			{
				$string_preview_is_cut = false;
				$linenum++;
				/**
				 * If our string is found in this line, output the line number and other data
				 */
				if ( stristr( $readline, $string ) )
				{
					/**
					 * Prepare the visual path for the end user
					 * Removes path leading up to WordPress root and ensures consistent directory separators
					 */
					$relativepath = str_replace( array( ABSPATH, '\\', '/' ), array( '', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR ), $path );
					$match_count++;

					/**
					 * Create the URL to take the user to the editor
					 */
					$editurl = $this->create_edit_link( $path, $linenum );

					$string_preview  = $readline;
					if ( strlen( $string_preview ) > 100 ) {
						$string_location = strpos( $string_preview, $string );

						$string_location_start = $string_location - $this->excerpt_length;
						if ( $string_location_start < 0 ) {
							$string_location_start = 0;
						}

						$string_location_end = $string_location + strlen( $string ) + $this->excerpt_length;
						if ( $string_location_end > strlen( $string_preview ) ) {
							$string_location_end = strlen( $string_preview );
						}

						$string_preview = substr( $string_preview, $string_location_start, $string_location_end );
						$string_preview_is_cut = true;
					}

					$string_preview = str_ireplace( $string, '<strong>' . $string . '</strong>', esc_html( $string_preview ) );
					if ( $string_preview_is_cut ) {
						$string_preview = sprintf(
							'&hellip;%s&hellip;',
							$string_preview
						);
					}

					$path_string = sprintf(
						'<a href="%s">%s</a>',
						esc_url( $editurl ),
						esc_html( $relativepath )
					);

					$output[] = array(
						'ID'           => $match_count,
						'linenum'      => $linenum,
						'path'         => $path,
						'filename'     => $path_string,
						'filename_raw' => $relativepath,
						'editurl'      => $editurl,
						'stringresult' => $string_preview
					);
				}
			}
		}
		else {
			/**
			 * The file was unreadable, give the user a friendly notification
			 */
			$output[] = array(
				'linenum'      => '#',
				'filename'     => esc_html( sprintf( __( 'Could not read file: %s', 'string-locator' ), $file ) ),
				'stringresult' => ''
			);
		}

		fclose( $readfile );

		return $output;
	}

	function ajax_scan_path( $path ) {
		$files = array();

		$paths = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path ),
				RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $paths AS $name => $location ) {
			if ( is_dir( $location->getPathname() ) ) {
				continue;
			}

			$files[] = $location->getPathname();
		}

		return $files;
	}
}

/**
 * Instantiate the plugin
 */
$string_locator = new string_locator();