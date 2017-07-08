<?php

// We need to make sure the correct timezone is set, or some PHP installations will complain
if (function_exists('date_default_timezone_set'))
{
	// * MAKE SURE YOU SET THIS TO THE CORRECT TIMEZONE! *
	// List of valid timezones is here: http://us3.php.net/manual/en/timezones.php
	date_default_timezone_set(get_option('timezone_string'));
}

$plugin_url = plugin_dir_path( __FILE__ );

// Require the framework
//require_once dirname(__FILE__) . '../../QuickBooks.php';
require_once $plugin_url.'../QuickBooks.php';

// Your .QWC file username/password
$qbwc_user = 'jbm_woocommerce';
$qbwc_pass = '!sodiol2215';

$dsn = 'mysqli://'.DB_USER.':'.DB_PASSWORD.'@'.DB_HOST.'/'.DB_NAME;

if (!QuickBooks_Utilities::initialized($dsn))
{
	// Initialize creates the neccessary database schema for queueing up requests and logging
	QuickBooks_Utilities::initialize($dsn);
	
	// This creates a username and password which is used by the Web Connector to authenticate
	QuickBooks_Utilities::createUser($dsn, $qbwc_user, $qbwc_pass);
}
