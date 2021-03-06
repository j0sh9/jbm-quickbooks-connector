<?php

/**
 * Example Web Connector application
 * 
 * This is a very simple application that allows someone to enter a customer 
 * name into a web form, and then adds the customer to QuickBooks.
 * 
 * @author Keith Palmer <keith@consolibyte.com>
 * 
 * @package QuickBooks
 * @subpackage Documentation
 */


function clean($string) {
    //$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
	$string = trim($string);
	$string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
    return $string;//preg_replace('/[^A-Za-z0-9\.\, -]/', '', $string); // Removes special chars.
}


/**
* Query Inventory Item List
*
*/

function _quickbooks_query_inventory_items_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	//$products = wc_get_products(array( 'limit' => -1));
	
	$xml = 
		'<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="13.0"?>
		<QBXML>
		  <QBXMLMsgsRq onError="stopOnError">';
	//foreach ( $products as $product ) {	
		$xml .= '
			<ItemQueryRq requestID="'.$requestID.'" >
			  <FullName>'.$extra.'</FullName>
			</ItemQueryRq>';
	//}
	$xml .= '
		  </QBXMLMsgsRq>
		</QBXML>';
	
	error_log($xml);
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_query_inventory_items_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{	
	foreach( $idents as $indent_k => $indent_v ) {
		error_log($indent_k." => ".$indent_v);
	}
}



/**
 * Generate a qbXML response to add a particular customer to QuickBooks
 */
function _quickbooks_customer_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// Grab the data from our MySQL database
	//$arr = mysql_fetch_assoc(mysql_query("SELECT * FROM my_customer_table WHERE id = " . (int) $ID));
	$arr['name'] = 'sdksdkfldskd';
	$arr['fname'] = 'Josh';
	$arr['lname'] = 'Buchanan';
	
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="13.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
						<Name>' . $arr['name'] . '</Name>
						<CompanyName>' . $arr['name'] . '</CompanyName>
						<FirstName>' . $arr['fname'] . '</FirstName>
						<LastName>' . $arr['lname'] . '</LastName>
					</CustomerAdd>
				</CustomerAddRq>
			</QBXMLMsgsRq>
		</QBXML>';
	
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_customer_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{	
	foreach( $idents as $indent_k => $indent_v ) {
		error_log($indent_k." => ".$indent_v);
	}
/*	mysql_query("
		UPDATE 
			my_customer_table 
		SET 
			quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "', 
			quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
		WHERE 
			id = " . (int) $ID);*/
}

/**
 * Generate a qbXML response to add a particular invoice to QuickBooks
 */
function _quickbooks_invoice_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	$refNumber = $ID;
	//current_time('timestamp');
	
	$TxnIDcode = get_option('jbm_qb_TxnIDcode');//'CLUB';
	$ClassRef = get_option('jbm_qb_ClassRef');//'Club 8';
	$InventorySiteRef = get_option('jbm_qb_InventorySiteRef');//'Escondido';
	$GlobalCustomer = get_option('jbm_qb_GlobalCustomer');//'*Online orders club8';
	
	$order = wc_get_order($ID);
	
	if ( !$GlobalCustomer || empty($GlobalCustomer) ) {
	
		$payment_method = $order->get_payment_method();
		//authorize_net_cim_credit_card
		//icanpay
		//bacs || other || empty, literally empty,not the string
		//cheque
		//cod
		$customer_ref = '*Wire Transfer'; // Default customer because this get looked at the most by accounting
		if ( $payment_method == 'authorize_net_cim_credit_card' )
			$customer_ref = '*VP CC';
		if ( $payment_method == 'icanpay' )
			$customer_ref = '*ISO CC';
		if ( $payment_method == 'cheque' )
			$customer_ref = '*Check';
		if ( $payment_method == 'cod' )
			$customer_ref = '*Cash';
	} else {
		$customer_ref = $GlobalCustomer;//'*Online orders club8';
	}
	
	$LotNumber = '1';// Need to get this from the order at soem point
	
	$total_tax = $order->get_total_tax();
	if ( $total_tax > 0 ) {
		$CustomerSalesTaxCodeRef = 'Tax';
	} else {
		$CustomerSalesTaxCodeRef = 'Non';
	}
	
	$billing2 = ( empty($order->get_billing_address_2()) ? '' : '<Addr3><![CDATA['.substr(clean($order->get_billing_address_2()),0,40).']]></Addr3>' );
	$shipping2 = ( empty($order->get_billing_address_2()) ? '' : '<Addr3><![CDATA['.substr(clean($order->get_billing_address_2()),0,40).']]></Addr3>' );
	
	$xml = '
<?xml version="1.0" encoding="utf-8"?>
<?qbxml version="13.0"?>
<QBXML>
  <QBXMLMsgsRq onError="stopOnError">
    <InvoiceAddRq requestID="'.$requestID.'">
      <InvoiceAdd defMacro= "TxnID:'.$TxnIDcode.$ID.'">
        <CustomerRef>
          <FullName>'.$customer_ref.'</FullName>
        </CustomerRef>
	<ClassRef>
		<FullName >'.$ClassRef.'</FullName>
	</ClassRef>
	<!--<ARAccountRef></ARAccountRef>-->
        <TxnDate>'.date('Y-m-d', strtotime($order->get_date_created().' +'.get_option('gmt_offset').' hours')).'</TxnDate>
        <RefNumber>'.$ID.'</RefNumber>
        <BillAddress>
          <Addr1><![CDATA['.substr(clean($order->get_formatted_billing_full_name()),0,40).']]></Addr1>
		  <Addr2><![CDATA['.substr(clean($order->get_billing_address_1()),0,40).']]></Addr2>'.$billing2.'
          <City><![CDATA['.clean($order->get_billing_city()).']]></City>
          <State>'.$order->get_billing_state().'</State>
          <PostalCode>'.$order->get_billing_postcode().'</PostalCode>
          <Country>'.$order->get_billing_country().'</Country>
        </BillAddress>
        <ShipAddress>
          <Addr1><![CDATA['.substr(clean($order->get_formatted_shipping_full_name()),0,40).']]></Addr1>
		  <Addr2><![CDATA['.substr(clean($order->get_shipping_address_1()),0,40).']]></Addr2>'.$shipping2.'
          <City><![CDATA['.clean($order->get_shipping_city()).']]></City>
          <State>'.$order->get_shipping_state().'</State>
          <PostalCode>'.$order->get_shipping_postcode().'</PostalCode>
          <Country>'.$order->get_shipping_country().'</Country>
        </ShipAddress>
        <Memo><![CDATA[UserID:'.$order->get_customer_id().' AffiliateID:'.affwp_get_affiliate_id($order->get_customer_id()).' '.clean($order->get_customer_note()).']]></Memo>
		<CustomerSalesTaxCodeRef>
          <FullName>'.$CustomerSalesTaxCodeRef.'</FullName>
        </CustomerSalesTaxCodeRef>';
	
	//Need to see if there are coupons. Coupons affect the item total. If the item total is less than sub total, but there are no coupons use the item total because there is a backend discount.
 	$coupon_count = count($order->get_items('coupon'));
	foreach ( $order->get_items() as $item ) {
		
		$item_product_id = $item['product_id'];
		$item_variation_id = $item['variation_id'];
		if ($item_variation_id) {
			$product = wc_get_product($item['variation_id']);
		} else {
			$product = wc_get_product($item['product_id']);
		}
		$item_sku = $product->get_sku();
	//I created 2 SKUs for the same products in CBDX. I have to TRIM the SKU on the variation products to make this work correctly for CBDX.
		if ( $ClassRef == 'CBDXtreme' && substr($item_sku, -2) == '-V' )
			$item_sku = substr($item_sku, 0, -2);
		
		$item_subtotal = $item['subtotal'];
		$item_total = $item['total'];
		
		if ( $coupon_count == 0 && $item_total < $item_subtotal ) {
			$item_amount = $item_total; //We had a backend manual adjustment
		} else {
			$item_amount = $item_subtotal;
		}
		
		
		$item_total_tax = $item['total_tax'];
		if ( $item_total_tax > 0 ) {
			$SalesTaxCodeRef = 'Tax';
		} else {
			$SalesTaxCodeRef = 'Non';
		}
		$xml .= '
        <InvoiceLineAdd>
          <ItemRef>
            <FullName>'.$item_sku.'</FullName>
          </ItemRef>
          <Desc><![CDATA['.clean($item['name']).']]></Desc>
          <Quantity>'.$item['qty'].'</Quantity>
		  <ClassRef>
		  	<FullName>'.$ClassRef.'</FullName>
		  </ClassRef>
          <Amount>'.number_format($item_amount,2,'.','').'</Amount>
		  <InventorySiteRef>
		  	<FullName>'.$InventorySiteRef.'</FullName>
		  </InventorySiteRef>
		  <!--<LotNumber>'.$LotNumber.'</LotNumber>-->
		  <SalesTaxCodeRef>
		  	<FullName>'.$SalesTaxCodeRef.'</FullName>
		  </SalesTaxCodeRef>
        </InvoiceLineAdd>';
	}
	
/* QuickBooks Needs a Fee Item	 
	foreach ( $order->get_items('fee') as $fee ) {
		$FeeItemRef = 'Website Discount';
			$fee_tax = $fee['total_tax'];
			if ( $fee_tax > 0 ) {
				$SalesTaxCodeRef = 'Tax';
			} else {
				$SalesTaxCodeRef = 'Non';
			}
	$xml .= '
        <InvoiceLineAdd>
          <ItemRef>
            <FullName>'.$FeeItemRef.'</FullName>
          </ItemRef>
          <Desc><![CDATA['.clean($fee['name']).']]></Desc>
		  <ClassRef>
		  	<FullName>'.$ClassRef.'</FullName>
		  </ClassRef>
          <Amount>'.number_format($fee['total'],2,'.','').'</Amount>
		  <SalesTaxCodeRef>
		  	<FullName>'.$SalesTaxCodeRef.'</FullName>
		  </SalesTaxCodeRef>
        </InvoiceLineAdd>';
	}
*/	
	 
	foreach ( $order->get_items('coupon') as $coupon ) {
		if ( strpos($coupon['code'], 'club8-credit-', 0) === 0 || strpos($coupon['code'], 'affiliate-credit-', 0) === 0 ) {
			$CouponItemRef = 'Affiliate Commission Credit';
			$coupon['discount'] = ($coupon['discount'] * -1);
		} else {
			$CouponItemRef = 'Website Discount';
		}
		
		$discount_tax = ($coupon['discount_tax'] * -1);
		if ( $discount_tax > 0 ) {
			$SalesTaxCodeRef = 'Tax';
		} else {
			$SalesTaxCodeRef = 'Non';
		}
		$xml .= '
        <InvoiceLineAdd>
          <ItemRef>
            <FullName>'.$CouponItemRef.'</FullName>
          </ItemRef>
          <Desc><![CDATA['.clean($coupon['code']).']]></Desc>
		  <ClassRef>
		  	<FullName>'.$ClassRef.'</FullName>
		  </ClassRef>
          <Amount>'.number_format($coupon['discount'],2,'.','').'</Amount>
		  <SalesTaxCodeRef>
		  	<FullName>'.$SalesTaxCodeRef.'</FullName>
		  </SalesTaxCodeRef>
        </InvoiceLineAdd>';
	}
	
	foreach ( $order->get_items('shipping') as $shipping ) {
		$ShippingItemRef = 'Shipping';
			$shipping_tax = $shipping['total_tax'];
			if ( $shipping_tax > 0 ) {
				$SalesTaxCodeRef = 'Tax';
			} else {
				$SalesTaxCodeRef = 'Non';
			}
	$xml .= '
        <InvoiceLineAdd>
          <ItemRef>
            <FullName>'.$ShippingItemRef.'</FullName>
          </ItemRef>
          <Desc><![CDATA['.clean($shipping['method_title']).']]></Desc>
		  <ClassRef>
		  	<FullName>'.$ClassRef.'</FullName>
		  </ClassRef>
          <Amount>'.number_format($shipping['total'],2,'.','').'</Amount>
		  <SalesTaxCodeRef>
		  	<FullName>'.$SalesTaxCodeRef.'</FullName>
		  </SalesTaxCodeRef>
        </InvoiceLineAdd>';
	}
 
	$xml .= '
      </InvoiceAdd>
    </InvoiceAddRq>';
	/*
	$MC8_wallet = get_post_meta($order_id, '_mc8_funds_wallet_amount', true);
if ( $MC8_wallet > 0 ) {	
	$xml .= '
	<ReceivePaymentAddRq requestID="'.$requestID.'">
		<ReceivePaymentAdd>
			<CustomerRef>
				<FullName>'.$customer_ref.'</FullName>
			</CustomerRef>
			<!--<ARAccountRef>
				<ListID></ListID>
				<FullName></FullName>
			</ARAccountRef>-->
			<TxnDate>'.date('Y-m-d', strtotime($order->get_date_paid())).'</TxnDate>
			<RefNumber>'.$ID.'</RefNumber>
			<TotalAmount>'.number_format($MC8_wallet,2,'.','').'</TotalAmount>
			<PaymentMethodRef>
				<FullName>MC8 Wallet</FullName>
			</PaymentMethodRef>
			<Memo><![CDATA[Inv. #'.$ID.']]></Memo>
			<DepositToAccountRef>
				<FullName>MC8 Wallet</FullName>
			</DepositToAccountRef>
			<AppliedToTxnAdd>
				<TxnID useMacro="TxnID:'.$TxnIDcode.$ID.'"/>
				<PaymentAmount>'.number_format($MC8_wallet,2,'.','').'</PaymentAmount>
			</AppliedToTxnAdd>
		</ReceivePaymentAdd>
	</ReceivePaymentAddRq>';
}
	*/
	$xml .= '
  </QBXMLMsgsRq>
</QBXML>
	';
//error_log('XML: '.$xml);	
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_invoice_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{	
	if ( get_post_meta($ID, '_jbm_quickbooks_status', true) === '0' ) {
		$edit_link = 'https://'.$_SERVER['HTTP_HOST'].'/wp-admin/post.php?post='.$ID.'&action=edit';
		$dupmsg = get_bloginfo('name').' order number <a href="'.$edit_link.'">'.$ID.'</a> duplicated in QuickBooks';
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$recipients = get_option('jbm_qb_email_duplicates');
		if ( empty($recipients) ) $recipients = get_option('admin_email');
		wp_mail( $recipients, 'QB Order '.$ID.' duplicate', $dupmsg, $headers );
	}
	$update_meta = update_post_meta($ID, '_jbm_quickbooks_response', $idents);
	$update_status = update_post_meta($ID, '_jbm_quickbooks_status', $idents['statusCode']);
	
	//error_log('XML: '.$xml);
	//error_log('Quickbooks Update Meta: '.$update_meta.' - '.json_encode($idents));
/*
	foreach( $idents as $indent_k => $indent_v ) {
		error_log($indent_k." => ".$indent_v);
	}
	error_log('Request ID:  '.$requestID);
	error_log('User: '.$user);
	error_log('Action: '.$action);
	error_log('ID: '.$ID);
	error_log('Extra: '.$extra);
	error_log('Last Action Time: '.$last_action_time);
	error_log('Last Action Ident Time: '.$last_actionident_time);
*/
}


/**
 * Generate a qbXML response to add a particular payment to QuickBooks
 */
function _quickbooks_payment_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	if ( !$ID ) return false;
	
	$response = get_post_meta($ID, '_jbm_quickbooks_response', true);
	//if ( !is_array($response) ) return false;
	$TxnID = $response['TxnID'];
	$customer = $response['FullName'];
	
	
	$wallet_amt = number_format(get_post_meta($ID, '_mc8_funds_wallet_amount', true),2);
	
	$order = wc_get_order($ID);
	
	$xml = '
<?xml version="1.0" encoding="utf-8"?>
<?qbxml version="13.0"?>
<QBXML>
	<QBXMLMsgsRq onError="stopOnError">
		<ReceivePaymentAddRq>
			<ReceivePaymentAdd>
				<CustomerRef>
					<FullName>'.$customer.'</FullName>
				</CustomerRef>
				<!--<ARAccountRef>
					<ListID></ListID>
					<FullName></FullName>
				</ARAccountRef>-->
				<TxnDate>'.date('Y-m-d', strtotime($order->get_date_paid())).'</TxnDate>
				<RefNumber>'.$ID.'</RefNumber>
				<TotalAmount>'.$wallet_amt.'</TotalAmount>
				<PaymentMethodRef>
					<FullName>MC8 Wallet</FullName>
				</PaymentMethodRef>
				<Memo>Inv. #'.$ID.'</Memo>
				<DepositToAccountRef>
					<!--<ListID></ListID>-->
					<FullName>MC8 Wallet</FullName>
				</DepositToAccountRef>
				<AppliedToTxnAdd>
					<TxnID>'.$TxnID.'</TxnID>
					<PaymentAmount>'.$wallet_amt.'</PaymentAmount>
				</AppliedToTxnAdd>
			</ReceivePaymentAdd>
		</ReceivePaymentAddRq>
	</QBXMLMsgsRq>
</QBXML>
	';
	//error_log('XML: '.$xml);
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_payment_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{		
	$update_meta = update_post_meta($ID, '_jbm_quickbooks_payment_response', $idents);
	$update_status = update_post_meta($ID, '_jbm_quickbooks_payment_status', $idents['statusCode']);
}


/**
 * Generate a qbXML response to add a particular payment to QuickBooks
 */
function _quickbooks_invoice_query_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	
	$xml = '
<?xml version="1.0" encoding="utf-8"?>
<?qbxml version="13.0"?>
<QBXML>
  <QBXMLMsgsRq onError="stopOnError">
    <InvoiceQueryRq>
      <RefNumber>54321</RefNumber> <!-- put your invoice # here -->
    </InvoiceQueryRq>
  </QBXMLMsgsRq>
</QBXML>
	';
	
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_invoice_query_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{	
	foreach( $idents as $indent_k => $indent_v ) {
		error_log($indent_k." => ".$indent_v);
	}
/*	mysql_query("
		UPDATE 
			my_customer_table 
		SET 
			quickbooks_listid = '" . mysql_real_escape_string($idents['ListID']) . "', 
			quickbooks_editsequence = '" . mysql_real_escape_string($idents['EditSequence']) . "'
		WHERE 
			id = " . (int) $ID);*/
}



/**
 * Catch and handle an error from QuickBooks
 */
function _quickbooks_error_catchall($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
{
	if ( get_post_meta($ID, '_jbm_quickbooks_status', true) === '0' ) {
		$edit_link = 'https://'.$_SERVER['HTTP_HOST'].'/wp-admin/post.php?post='.$ID.'&action=edit';
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$recipients = get_option('jbm_qb_email_duplicates');
		if ( empty($recipients) ) $recipients = get_option('admin_email');
		wp_mail( $recipients, 'QB Order '.$ID.' duplicate', '<a href="'.$edit_link.'">'.$ID.'</a>', $headers );
	}
	//error_log($errnum.' - '.$errmsg);
	$idents['statusCode'] = $errnum;
	$idents['statusMessage'] = $errmsg;
	$update_meta = update_post_meta($ID, '_jbm_quickbooks_error', $idents);
	$update_status = update_post_meta($ID, '_jbm_quickbooks_status', $errnum);
	//error_log($requestID);
	//error_log($ID);
	//error_log($xml);
	//error_log($action);
	return true;
}


/**
 * Catch and handle an error from QuickBooks
 */
function _quickbooks_error_catch500($requestID, $user, $action, $ID, $extra, &$err, $xml, $errnum, $errmsg)
{
	if ( get_post_meta($ID, '_jbm_quickbooks_status', true) === '0' ) {
		$edit_link = 'https://'.$_SERVER['HTTP_HOST'].'/wp-admin/post.php?post='.$ID.'&action=edit';
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$recipients = get_option('jbm_qb_email_duplicates');
		if ( empty($recipients) ) $recipients = get_option('admin_email');
		wp_mail( $recipients, 'QB Order '.$ID.' duplicate', '<a href="'.$edit_link.'">'.$ID.'</a>', $headers );
	}
	//error_log($errnum.' - '.$errmsg);
	$idents['statusCode'] = $errnum;
	$idents['statusMessage'] = $errmsg;
	$update_meta = update_post_meta($ID, '_jbm_quickbooks_error', $idents);
	$update_status = update_post_meta($ID, '_jbm_quickbooks_status', $errnum);
	//error_log($requestID);
	//error_log($ID);
	//error_log($action);
	return true;
}
