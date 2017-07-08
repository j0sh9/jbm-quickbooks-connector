<?php
/*
Plugin Name: Quickbooks Connector
Description: Sync orders to quickbooks
Version: 1.1
*/

/***
*
* Create path for Quickbook Web Connector App
*/
function jbm_quickbooks_connector() {
  if ( !isset($_GET['jbm-quickbooks-connector']) ) return;

  include plugin_dir_path( __FILE__ )."includes/qbwc.php";
  die();
}
add_action( 'template_redirect', 'jbm_quickbooks_connector' );

function jbm_quickbooks_enqueue_inventory_list_query(  ) {
	if ( isset($_GET['inventoryList']) ) {
		require_once plugin_dir_path( __FILE__ )."includes/config.php";
		$Queue = new QuickBooks_WebConnector_Queue($dsn);
		$products = wc_get_products(array( 'status' => array( 'private', 'publish' ), 'limit' => -1));
		foreach ( $products as $product ) {	
			if ( ! empty( $product->get_sku() ) ) {
				$Queue->enqueue(QUICKBOOKS_QUERY_INVENTORYITEM, $product->get_id(), 0, $product->get_sku(), null, null, true);
			}
		}
	}
}
//add_action( 'init', 'jbm_quickbooks_enqueue_inventory_list_query' );

function jbm_quickbooks_enqueue_new_order( $order_id ) {
	if ( ! get_option('jbm_qb_enabled') ) return;
	require_once plugin_dir_path( __FILE__ )."includes/config.php";
	$Queue = new QuickBooks_WebConnector_Queue($dsn);
	$Queue->enqueue(QUICKBOOKS_ADD_INVOICE, $order_id);
	update_post_meta($order_id, '_jbm_quickbooks_response', 'Processing');
	
}
add_action( 'woocommerce_order_status_processing', 'jbm_quickbooks_enqueue_new_order', 10, 1);



function jbm_quickbooks_enqueue_current_order_meta_box() {
	if ( ! get_option('jbm_qb_enabled') ) return;
	$example_var = 'example';
	add_meta_box(
		'jbm_quickbooks_enqueue_current_order',
		'Send To QuickBooks',
		'jbm_quickbooks_enqueue_current_order_html',
		'shop_order',
		'side',
		'high',
		array('example_var' => $example_var)
	);
}
add_action( 'add_meta_boxes', 'jbm_quickbooks_enqueue_current_order_meta_box');

function jbm_quickbooks_enqueue_current_order_html() {
	global $post;
	$order_id = $post->ID;
	$quickbooks_status = get_post_meta($order_id, '_jbm_quickbooks_response', true);
	if ( $quickbooks_status != false ) {
		if ( $quickbooks_status == 'Processing' ) {
			$status_msg = '<strong>Order Sent to Queue</strong>';
			$send_text = "Processing";
			$send_disabled = 'disabled';
		} else {
			
			
			$unserial_quickbooks_status = unserialize(str_replace('\\', '', $quickbooks_status));
			if ( $unserial_quickbooks_status != false ) {
				$quickbooks_status = json_encode($unserial_quickbooks_status);
				$quickbooks_status = json_decode($quickbooks_status);
			} else {
				$quickbooks_status = json_decode($quickbooks_status);
			}
			
			if ( $quickbooks_status->statusCode !== '0' ) {
				$status_msg = '<strong style="color:red;">'.$quickbooks_status->statusMessage.'</strong><br>';
				$status_msg .= '<span>Status Code: '.$quickbooks_status->statusCode.'</span>';
				$send_text = "Resend To Quickbooks";
				$send_disabled = '';
			} else {
				$status_msg = '<strong>'.$quickbooks_status->statusMessage.'</strong><br>';
				$status_msg .= '<strong>Transaction ID:</strong> '.$quickbooks_status->TxnID;
				$send_disabled = "disabled";
				$send_text = "Sent";
			}
		}
		
	} else {
		$status_msg = '<strong>Order not sent to QuickBooks';
		$send_text = "Send To QuickBooks";
		$send_disabled = '';
	}
		
	?>
	<div>
		<p><?=$status_msg;?></p>
		<p><button id="quickbooks_enqueue_current_order" name="quickbooks_enqueue_current_order" type="submit" value="1" class="button" <?=$send_disabled?>><?=$send_text?></button></p>
	</div>
	<?php
}

function jbm_quickbooks_enqueue_current_order_save( $post_ID, $post, $update ) {
	if ( ! get_option('jbm_qb_enabled') ) return;
	if ( isset( $_POST['quickbooks_enqueue_current_order'] ) && $_POST['quickbooks_enqueue_current_order'] == 1 ) {
		jbm_quickbooks_enqueue_new_order( $post_ID );
	}
}
add_action ( 'save_post_shop_order', 'jbm_quickbooks_enqueue_current_order_save', 10, 3);


function jbm_qb_admin_settings() {
	$jb_page_title = 'QuickBooks Settings';
	$jb_menu_title = 'QuickBooks Settings';
	$jb_capability = 'manage_options';
	$jb_menu_slug = 'jbm-qb-admin-settings';
	$jb_callback = 'jbm_qb_admin_settings_html';
	$jb_icon_url = 'dashicons-image-flip-vertical';
	$jb_menu_position = 60;
	add_menu_page(  $jb_page_title,  $jb_menu_title,  $jb_capability,  $jb_menu_slug,  $jb_callback,  $jb_icon_url,  $jb_menu_position );
}

function jbm_qb_admin_settings_html() {
	if ( isset( $_POST['jbm_qb_submit'] ) && $_POST['jbm_qb_submit'] == 1 ) {
		if ( isset($_POST['jbm_qb_enabled']) )
			update_option( 'jbm_qb_enabled', $_POST['jbm_qb_enabled'] );
		update_option( 'jbm_qb_TxnIDcode', $_POST['jbm_qb_TxnIDcode'] );
		update_option( 'jbm_qb_ClassRef', $_POST['jbm_qb_ClassRef'] );
		update_option( 'jbm_qb_InventorySiteRef', $_POST['jbm_qb_InventorySiteRef'] );
		
		//update_option( 'jbm_qb_user', $_POST['jbm_qb_user'] );
		//update_option( 'jbm_qb_pass', $_POST['jbm_qb_user'] );
	}
	$enabled = get_option('jbm_qb_enabled');
	$enable_checked = '';
	if ( $enabled === '1' )
		$enable_checked = 'checked';
	$TxnIDcode = get_option('jbm_qb_TxnIDcode');//'CLUB';
	$ClassRef = get_option('jbm_qb_ClassRef');//'Club 8';
	$InventorySiteRef = get_option('jbm_qb_InventorySiteRef');//'Escondido';
	
	global $wpdb;
	$errors_query = "
		SELECT $wpdb->postmeta.*
		FROM $wpdb->postmeta
		WHERE $wpdb->postmeta.meta_key = '_jbm_quickbooks_status'
		AND $wpdb->postmeta.meta_value > '0'
		";
	//$args = array( 'posts_per_page' => 5, 'offset'=> 1, 'category' => 1 );
	$order_errors = $wpdb->get_results($errors_query, OBJECT);
	?>
	<h1>QuickBooks Sync Settings</h1>
	<form action="" method="post" id="jbm-qb-form">
		<div>
			<p><label><input type="checkbox" name="jbm_qb_enabled" id="jbm_qb_enabled" value="1" <?=$enable_checked?> /> &nbsp; <strong>QuickBooks Sync Enabled</strong></label></p>
		</div>
		<p><label><strong>Transaction Prefix</strong> <input name="jbm_qb_TxnIDcode" id="jbm_qb_TxnIDcode" type="text" placeholder="i.e. ISO" value="<?=$TxnIDcode?>" ></label></p>
		<p><label><strong>Invoice Class</strong> <input name="jbm_qb_ClassRef" id="jbm_qb_ClassRef" type="text" placeholder="ISO International" value="<?=$ClassRef?>" ></label></p>
		<p><label><strong>Inventory Site</strong> <input name="jbm_qb_InventorySiteRef" id="jbm_qb_InventorySiteRef" type="text" placeholder="Escondido" value="<?=$InventorySiteRef?>" ></label></p>
		</div>
		<p><button type="submit" name="jbm_qb_submit" id="jbm_qb_submit" value="1" class="button">Save Settings</button></p>
	</form>
	<style>
		#jbm-qb-form {
			max-width: 600px;
			min-width: 300px;
			width: 100%;
		}
		#jbm-qb-form input[type=text] {
			width: 100%;
		}
		#jbm-qb-error-table {
		}
		
		#jbm-qb-error-table, #jbm-qb-error-table th, #jbm-qb-error-table td {
			border: 1px solid #cdcdcd;
			border-collapse: collapse;
		}
		#jbm-qb-error-table {
			width: 97%;
			margin: 3vw 1vw;
		}
		#jbm-qb-error-table th, #jbm-qb-error-table td {
			padding: 4px 8px;
		}
	</style>
	<div>
		<table id="jbm-qb-error-table">
			<tr><th>Order #</th><th>Error Code</th><th>Error Message</th></tr>
			
	<?php foreach($order_errors as $error) {
		$errors = unserialize(get_post_meta($error->post_id, '_jbm_quickbooks_response', true));
	?>
			<tr>
				<td><a href="/wp-admin/post.php?post=<?=$error->post_id;?>&action=edit" target="_blank"><?=$error->post_id;?></a></td>
				<td><?= $errors['statusCode'];?></td>
				<td><?= $errors['statusMessage'];?></td>
			</tr>
	<?php } ?>
		</table>
	</div>
	<?php
}
add_action( 'admin_menu', 'jbm_qb_admin_settings' );