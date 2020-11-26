<?php
/**
 * Display list of emails, which are in denylist
 * @package Limit Attempts
 * @since 1.2.6
 */
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if ( ! class_exists( 'Lmtttmpts_Denylist_Email' ) ) {
	class Lmtttmpts_Denylist_Email extends WP_List_Table {
		public $is_geoip_exists;

		function __construct() {
			global $wpdb;
			parent::__construct();
			$this->is_geoip_exists = $wpdb->query( "SHOW TABLES LIKE '{$wpdb->base_prefix}bws_list_countries'" );
		}

		function get_columns() {
			/* adding collumns to table and their view */
			$columns = array(
				'cb'           => '<input type="checkbox" />',
				'email'        => __( 'Email', 'limit-attempts' ),
				'add_time'     => __( 'Date Added', 'limit-attempts' )
			);
			return $columns;
		}

		function get_sortable_columns() {
			/* seting sortable collumns */
			$sortable_columns = array(
				'email'        => array( 'email', false ),
				'add_time'     => array( 'add_time', false )
			);
			return $sortable_columns;
		}

		function column_email( $item ) {
			/* adding action to 'email' collumn */
			$actions = array(
				'delete'	=> '<a href="' . wp_nonce_url( sprintf( '?page=%s&list=denylist&tab-action=denylist_email&lmtttmpts_remove_from_denylist_email=%s', $_REQUEST['page'], $item['email'] ), 'lmtttmpts_remove_from_denylist_email_' . $item['email'], 'lmtttmpts_nonce_name' ) . '">' . __( 'Delete', 'limit-attempts' ) . '</a>',
			);
			return sprintf( '%1$s %2$s', $item['email'], $this->row_actions( $actions ) );
		}

		function get_bulk_actions() {
			/* adding bulk action */
			$actions = array(
				'remove_from_denylist_email_ips'	=> __( 'Delete', 'limit-attempts' )
			);
			return $actions;
		}

		function column_cb( $item ) {
			/* customize displaying cb collumn */
			return sprintf(
				'<input type="checkbox" name="email[]" value="%s" />', $item['email']
			);
		}

		function prepare_items() {
			/* preparing table items */
			global $wpdb;
			$prefix = $wpdb->prefix . 'lmtttmpts_';
			/* query for total number of denylisted Emails */
			$query = "SELECT COUNT(*) FROM `" . $prefix . "denylist_email`";
			/* if search */
			if ( isset( $_REQUEST['s'] ) ) {
				$part_email = isset( $_REQUEST['s'] ) ? trim( htmlspecialchars( $_REQUEST['s'] ) ) : '';
				$query .= " WHERE `email` LIKE '%" . $part_email . "%'";
			}
			/* get the total number of Emails */
			$totalitems = $wpdb->get_var( $query );
			/* get the value of number of Emails on one page */
			$perpage = $this->get_items_per_page( 'addresses_per_page', 20 );
			/* the total number of pages */
			$totalpages = ceil( $totalitems / $perpage );
			/* set pagination arguments */
			$this->set_pagination_args( array(
				"total_items" 	=> $totalitems,
				"total_pages" 	=> $totalpages,
				"per_page" 		=> $perpage
			) );
			/* general query */

			$query =
				"SELECT
					`{$prefix}denylist_email`.`add_time`,
					`{$prefix}denylist_email`.`email`
				FROM `{$prefix}denylist_email`";
			if ( isset( $_REQUEST['s'] ) ) {
				$part_email = isset( $_REQUEST['s'] ) ? trim( htmlspecialchars( $_REQUEST['s'] ) ) : '';
				$query .= " WHERE `email` LIKE '%" . $part_email . "%'";
			}

			/* the 'orderby' and 'order' values */
			$orderby = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ? $_REQUEST['orderby']  : 'add_time';
			$order   = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array('asc', 'desc') ) ) ? $_REQUEST['order'] : 'desc';
			/* calculate offset for pagination */
			$paged   = ( isset( $_REQUEST['paged'] ) && is_numeric( $_REQUEST['paged'] ) && 0 < $_REQUEST['paged'] ) ? $_REQUEST['paged'] : 1;
			if ( 0 > $totalpages && $paged > $totalpages )
				$paged = $totalpages;
			$offset  = ( $paged - 1 ) * $perpage;
			/* add calculated values (order and pagination) to our query */
			$query .= " ORDER BY `" . $orderby . "` " . $order . " LIMIT " . $offset . "," . $perpage;
			/* get data from our denylist table - list of denylisted IPs */
			$blacklisted_items = $wpdb->get_results( $query, ARRAY_A );
			/* get site date and time format from DB option */
			$columns 				= $this->get_columns();
			$hidden 				= array();
			$sortable 				= $this->get_sortable_columns();
			$this->_column_headers	= array( $columns, $hidden, $sortable );
			$this->items 			= $blacklisted_items;
		}

		function column_default( $item, $column_name ) {
			/* setting default view for collumn items */
			switch ( $column_name ) {
				case 'add_time':
				case 'email':
					return $item[ $column_name ];
				case 'ip_range':
					return $item['ip_from'] . ' - ' . $item['ip_to'];
				default:
					/* Show whole array for bugfix */
					return print_r( $item, true );
			}
		}

		function pagination( $which ) {
			if ( empty( $this->_pagination_args ) ) {
				return;
			}

			$total_items = $this->_pagination_args['total_items'];
			$total_pages = $this->_pagination_args['total_pages'];
			$infinite_scroll = false;
			if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
				$infinite_scroll = $this->_pagination_args['infinite_scroll'];
			}

			if ( 'top' === $which && $total_pages > 1 && method_exists( $this->screen, 'render_screen_reader_content' ) ) {
				$this->screen->render_screen_reader_content( 'heading_pagination' );
			}

			$output = '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

			$current = $this->get_pagenum();

			$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

			$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

			$additional_inputs = '';
			$requests_indexes  = array( 'orderby', 'order', 's' );
			foreach ( $requests_indexes as $index ) {
				if ( isset( $_REQUEST[ $index ] ) ) {
					$request = esc_html( trim( $_REQUEST[ $index ] ) );
					if ( ! empty( $request ) ) {
						$current_url = add_query_arg( $index, $request, $current_url );
						$additional_inputs .= '<input type="hidden" name="' . $index . '" value="' . $request . '" />';
					}
				}
			}

			$page_links = array();

			$total_pages_before = '<span class="paging-input">';
			$total_pages_after  = '</span>';

			$disable_first = $disable_last = $disable_prev = $disable_next = false;

			if ( $current == 1 ) {
				$disable_first = true;
				$disable_prev = true;
			}
			if ( $current == 2 ) {
				$disable_first = true;
			}
			if ( $current == $total_pages ) {
				$disable_last = true;
				$disable_next = true;
			}
			if ( $current == $total_pages - 1 ) {
				$disable_last = true;
			}

			if ( $disable_first ) {
				$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
			} else {
				$page_links[] = sprintf( "<a class='first-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( remove_query_arg( 'paged', $current_url ) ),
					__( 'First page' ),
					'&laquo;'
				);
			}

			if ( $disable_prev ) {
				$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
			} else {
				$page_links[] = sprintf( "<a class='prev-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
					__( 'Previous page' ),
					'&lsaquo;'
				);
			}

			if ( 'bottom' === $which ) {
				$html_current_page  = $current;
				$total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page' ) . '</span><span id="table-paging" class="paging-input">';
			} else {
				$html_current_page = sprintf( "%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' />",
					'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page' ) . '</label>',
					$current,
					strlen( $total_pages )
				);
			}
			$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
			$page_links[] = $total_pages_before . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . $total_pages_after;

			if ( $disable_next ) {
				$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
			} else {
				$page_links[] = sprintf( "<a class='next-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
					__( 'Next page' ),
					'&rsaquo;'
				);
			}

			if ( $disable_last ) {
				$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
			} else {
				$page_links[] = sprintf( "<a class='last-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
					__( 'Last page' ),
					'&raquo;'
				);
			}

			$pagination_links_class = 'pagination-links';
			if ( ! empty( $infinite_scroll ) ) {
				$pagination_links_class = ' hide-if-js';
			}
			$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

			if ( $total_pages ) {
				$page_class = $total_pages < 2 ? ' one-page' : '';
			} else {
				$page_class = ' no-pages';
			}
			$this->_pagination = "<div class='tablenav-pages{$page_class}'>{$output}{$additional_inputs}</div>";

			echo $this->_pagination;
		}

		function action_message() {
			global $wpdb;
			$action_message = array(
				'error' 			=> false,
				'done'  			=> false,
				'error_country'		=> false,
				'wrong_ip_format'	=> ''
			);
			$error = $done = '';
			$prefix = "{$wpdb->prefix}lmtttmpts_";
			$message_list = array(
				'notice'						=> __( 'Notice:', 'limit-attempts' ),
				'empty_ip_list'					=> __( 'No address has been selected', 'limit-attempts' ),
				'denylisted_delete_done'		=> __( 'has been deleted from deny list', 'limit-attempts' ),
				'denylisted_delete_done_many'	=> __( 'have been deleted from deny list', 'limit-attempts' ),
				'denylisted_delete_error'		=> __( 'Error while deleting from deny list', 'limit-attempts' )
			);
			if ( isset( $_REQUEST['lmtttmpts_remove_from_denylist_email'] ) ) {
				check_admin_referer( 'lmtttmpts_remove_from_denylist_email_' . $_REQUEST['lmtttmpts_remove_from_denylist_email'], 'lmtttmpts_nonce_name' );
				$email = $_REQUEST['lmtttmpts_remove_from_denylist_email'];
			} else {
				if (
					( isset( $_POST['action'] ) && $_POST['action']  == 'remove_from_denylist_email_ips' ) ||
					( isset( $_POST['action2'] ) && $_POST['action2'] == 'remove_from_denylist_email_ips' )
				) {
					check_admin_referer( 'bulk-' . $this->_args['plural'] );
					$email = isset( $_POST['email'] ) ? $_POST['email'] : '';
				}
			}
			if ( isset( $email ) ) {
				if ( empty( $email ) ) {
					$action_message['done'] = $message_list['notice'] . '&nbsp;' . $message_list['empty_ip_list'];
				} else {
					$eml = is_array( $email ) ? implode( "','", $email ) : $email;
					$wpdb->query( "DELETE FROM `{$prefix}denylist_email` WHERE `email` IN ('{$eml}');" );
					if ( $wpdb->last_error ) {
						$action_message['error'] = $eml . '&nbsp;-&nbsp;' . $message_list['denylisted_delete_error'];
					} else {
						$done_ips = (array)$email;
						$action_message['done'] = implode( ', ', $done_ips ) . '&nbsp;' . ( 1 == count( $done_ips ) ? $message_list['denylisted_delete_done'] : $message_list['denylisted_delete_done_many'] );
					}
				}
			}

			if ( isset( $_REQUEST['s'] ) ) {
				$search_request = esc_html( trim( $_REQUEST['s'] ) );
				if ( ! empty( $search_request ) ) {
					$action_message['done'] .= ( empty( $action_message['done'] ) ? '' : '<br/>' ) . __( 'Search results for', 'limit-attempts' ) . '&nbsp;' . $search_request;
				}
			}

			if ( ! empty( $action_message['error'] ) ) { ?>
				<div class="error inline lmtttmpts_message"><p><strong><?php echo $action_message['error']; ?></strong></div>
			<?php }
			if ( ! empty( $action_message['done'] ) ) { ?>
				<div class="updated inline lmtttmpts_message"><p><?php echo $action_message['done'] ?></p></div>
			<?php }
		}
	}
}