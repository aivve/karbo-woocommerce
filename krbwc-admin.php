<?php
/*
Karbo for WooCommerce
https://github.com/aivve/karbo.club-woocommerce
*/

// Include everything
include (dirname(__FILE__) . '/krbwc-include-all.php');

//===========================================================================
// Global vars.

global $g_KRBWC__plugin_directory_url;
$g_KRBWC__plugin_directory_url = plugins_url ('', __FILE__);

global $g_KRBWC__cron_script_url;
$g_KRBWC__cron_script_url = $g_KRBWC__plugin_directory_url . '/krbwc-cron.php';

//===========================================================================

//===========================================================================
// Global default settings
global $g_KRBWC__config_defaults;
$g_KRBWC__config_defaults = array (

   // ------- Hidden constants
// 'supported_currencies_arr'             =>  array ('USD', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'HKD', 'JPY', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB'), // Not used right now.
// 'database_schema_version'              =>  1.4,
   'assigned_address_expires_in_mins'     =>  12*60,   // 12 hours to pay for order and receive necessary number of confirmations.
   'funds_received_value_expires_in_mins' =>  '5',		// 'received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.
// 'starting_index_for_new_krb_payments' =>  '2',    // Generate new addresses for the wallet starting from this index.
// 'max_blockchains_api_failures'         =>  '3',    // Return error after this number of sequential failed attempts to retrieve blockchain data.
// 'max_unusable_generated_addresses'     =>  '20',   // Return error after this number of unusable (non-empty) Karbo addresses were sequentially generated
   'blockchain_api_timeout_secs'          =>  '20',   // Connection and request timeouts for curl operations dealing with blockchain requests.
   'exchange_rate_api_timeout_secs'       =>  '10',   // Connection and request timeouts for curl operations dealing with exchange rate API requests.
   'soft_cron_job_schedule_name'          =>  'minutes_1',   // WP cron job frequency
// 'delete_expired_unpaid_orders'         =>  '1',   // Automatically delete expired, unpaid orders from WooCommerce->Orders database
// 'reuse_expired_addresses'              =>  '1',   // True - may reduce anonymouty of store customers (someone may click/generate bunch of fake orders to list many addresses that in a future will be used by real customers).
                                                      // False - better anonymouty but may leave many addresses in wallet unused (and hence will require very high 'gap limit') due to many unpaid order clicks.
                                                      //        In this case it is recommended to regenerate new wallet after 'gap limit' reaches 1000.
// 'max_unused_addresses_buffer'          =>  10,     // Do not pre-generate more than these number of unused addresses. Pregeneration is done only by hard cron job or manually at plugin settings.
   'cache_exchange_rates_for_minutes'			=>	10,			// Cache exchange rate for that number of minutes without re-calling exchange rate API's.
// 'soft_cron_max_loops_per_run'					=>	2,			// NOT USED. Check up to this number of assigned Karbo addresses per soft cron run. Each loop involves number of DB queries as well as API query to blockchain - and this may slow down the site.
// 'elists'																=>	array(),
// 'use_aggregated_api'										=>  '1',		// Use aggregated API to efficiently retrieve Karbo address balance

   // ------- General Settings
// 'license_key'                          =>  'UNLICENSED',
// 'api_key'                              =>  substr(md5(microtime()), -16),
   // New, ported from WooCommerce settings pages.
   'service_provider'				 	  =>  'karbo_club',		// 'blockchain_info'
   'address'                              =>  '', 
   // 'electrum_mpk_saved'                   =>  '', // Saved, non-normalized value - MPK's separated by space / \n / ,
   // 'electrum_mpks'                        =>  array(), // Normalized array of MPK's - derived from saved.
   'confs_num'                            =>  '4', // number of confirmations required before accepting payment.
   //'exchange_rate_type'                   =>  'vwap', // 'realtime', 'bestrate'.
   'exchange_multiplier'                  =>  '1.00',

   'delete_db_tables_on_uninstall'        =>  '0',
   'autocomplete_paid_orders'			  =>  '1',
   'enable_soft_cron_job'                 =>  '1',    // Enable "soft" Wordpress-driven cron jobs.

   // ------- Copy of $this->settings of 'KRBWC_Karbo' class.
   // DEPRECATED (only blockchain.info related settings still remain there.)
// 'gateway_settings'                     =>  array('confirmations' => 6),

   // ------- Special settings
   'exchange_rates'                       =>  array('EUR' => array('method|type' => array('time-last-checked' => 0, 'exchange_rate' => 1), 'GBP' => array())),
   );
//===========================================================================

//===========================================================================
function KRBWC__GetPluginNameVersionEdition($please_donate = true) // false to turn off
{
  $return_data = '<h2 style="border-bottom:1px solid #DDD;padding-bottom:10px;margin-bottom:20px;">' .
            KRBWC_PLUGIN_NAME . ', v.: <span style="color:#EE0000;">' .
            KRBWC_VERSION. '</span>' .
          '</h2>';


  if ($please_donate)
  {
    $return_data .= "<p style='border:1px solid #890e4e;padding:5px 10px;color:#004400;background-color:#FFF;'>" . __('Please donate KRB to:','wookarboclub') . "&nbsp;&nbsp;<span style='font-size:110%;font-weight:bold;'><a href='karbowanec:KdueH7qJgwWGwzUCNxiUnkG4ddayULf9PMAnGgEHyVeMbAfzYP4BPSJj455jtAiweTGW5U81HhJbuY34gXBCR2sB9YcE3h9'>KdueH7qJgwWGwzUCNxiUnkG4ddayULf9PMAnGgEHyVeMbAfzYP4BPSJj455jtAiweTGW5U81HhJbuY34gXBCR2sB9YcE3h9</a></span>" . __(' to support maintaining of the free Payment Gateway.','wookarboclub') . "</p>";
  }

  return $return_data;
}
//===========================================================================

//===========================================================================
function KRBWC__withdraw ()
{
    $krbwc_settings = KRBWC__get_settings();
    $address = $krbwc_settings['address'];

    try{
      $wallet_api = New ForkNoteWalletd("http://karbo.club:8888");
      $address_balance = $wallet_api->getBalance($address);
    }
    catch(Exception $e) {
    }          

    if ($address_balance === false)
    {
      return __('Karbo address is not found in wallet.','wookarboclub');
    } else {
      $address_balance = $address_balance['availableBalance'];
      //round ( float $val [, int $precision = 0 [, int $mode = PHP_ROUND_HALF_UP ]] )
      $display_address_balance  = sprintf("%.4f", $address_balance  / 1000000000000.0); 
      $withdraw_fee = 100000000; 
      $display_fee  = sprintf("%.4f", $withdraw_fee  / 1000000000000.0);
      $send_amount = (floor( $address_balance / 100000000 ) * 100000000 ) - 200000000; // Only allows sending 4 decimal places
      $display_send_amount = sprintf("%.4f", $send_amount  / 1000000000000.0);
      $send_address = $_POST["withdraw_address"];
      
      try{
        $sent = $wallet_api->sendTransaction( array( $address ), array(array( "amount" => $send_amount, "address" => $send_address)), false, 6, $withdraw_fee, $address );
        return "Withdraw Sent in Transaction: " . $sent["transactionHash"];
        //@TODO Log
      }
      catch(Exception $e) {
        return $e->GetMessage();
      }  
    }
}
//===========================================================================

//===========================================================================
function KRBWC__get_settings ($key=false)
{
  global   $g_KRBWC__plugin_directory_url;
  global   $g_KRBWC__config_defaults;

  $krbwc_settings = get_option (KRBWC_SETTINGS_NAME);
  if (!is_array($krbwc_settings))
    $krbwc_settings = array();

  if ($key)
    return (@$krbwc_settings[$key]);
  else
    return ($krbwc_settings);
}
//===========================================================================

//===========================================================================
function KRBWC__update_settings ($krbwc_use_these_settings=false, $also_update_persistent_settings=false)
{
   if ($krbwc_use_these_settings)
      {
      // if ($also_update_persistent_settings)
      //   KRBWC__update_persistent_settings ($krbwc_use_these_settings);

      update_option (KRBWC_SETTINGS_NAME, $krbwc_use_these_settings);
      return;
      }

   global   $g_KRBWC__config_defaults;

   // Load current settings and overwrite them with whatever values are present on submitted form
   $krbwc_settings = KRBWC__get_settings();

   foreach ($g_KRBWC__config_defaults as $k=>$v)
      {
      if (isset($_POST[$k]))
         {
         if (!isset($krbwc_settings[$k]))
            $krbwc_settings[$k] = ""; // Force set to something.
         KRBWC__update_individual_krbwc_setting ($krbwc_settings[$k], $_POST[$k]);
         }
      // If not in POST - existing will be used.
      }

   //---------------------------------------
   // Validation
   //if ($krbwc_settings['aff_payout_percents3'] > 90)
   //   $krbwc_settings['aff_payout_percents3'] = "90";
   //---------------------------------------

  // ---------------------------------------
  // Post-process variables.

  // Array of MPK's. Single MPK = element with idx=0
  //$krbwc_settings['electrum_mpks'] = preg_split("/[\s,]+/", $krbwc_settings['electrum_mpk_saved']);
  // ---------------------------------------


  // if ($also_update_persistent_settings)
  //   KRBWC__update_persistent_settings ($krbwc_settings);

  update_option (KRBWC_SETTINGS_NAME, $krbwc_settings);
}
//===========================================================================

//===========================================================================
// Takes care of recursive updating
function KRBWC__update_individual_krbwc_setting (&$krbwc_current_setting, $krbwc_new_setting)
{
   if (is_string($krbwc_new_setting))
      $krbwc_current_setting = KRBWC__stripslashes ($krbwc_new_setting);
   else if (is_array($krbwc_new_setting))  // Note: new setting may not exist yet in current setting: curr[t5] - not set yet, while new[t5] set.
      {
      // Need to do recursive
      foreach ($krbwc_new_setting as $k=>$v)
         {
         if (!isset($krbwc_current_setting[$k]))
            $krbwc_current_setting[$k] = "";   // If not set yet - force set it to something.
         KRBWC__update_individual_krbwc_setting ($krbwc_current_setting[$k], $v);
         }
      }
   else
      $krbwc_current_setting = $krbwc_new_setting;
}
//===========================================================================

//===========================================================================
//
// Reset settings only for one screen
function KRBWC__reset_partial_settings ($also_reset_persistent_settings=false)
{
   global   $g_KRBWC__config_defaults;

   // Load current settings and overwrite ones that are present on submitted form with defaults
   $krbwc_settings = KRBWC__get_settings();

   foreach ($_POST as $k=>$v)
      {
      if (isset($g_KRBWC__config_defaults[$k]))
         {
         if (!isset($krbwc_settings[$k]))
            $krbwc_settings[$k] = ""; // Force set to something.
         KRBWC__update_individual_krbwc_setting ($krbwc_settings[$k], $g_KRBWC__config_defaults[$k]);
         }
      }

  update_option (KRBWC_SETTINGS_NAME, $krbwc_settings);

  // if ($also_reset_persistent_settings)
  //   KRBWC__update_persistent_settings ($krbwc_settings);
}
//===========================================================================

//===========================================================================
function KRBWC__reset_all_settings ($also_reset_persistent_settings=false)
{
  global   $g_KRBWC__config_defaults;

  update_option (KRBWC_SETTINGS_NAME, $g_KRBWC__config_defaults);

  // if ($also_reset_persistent_settings)
  //   KRBWC__reset_all_persistent_settings ();
}
//===========================================================================

//===========================================================================
// Recursively strip slashes from all elements of multi-nested array
function KRBWC__stripslashes (&$val)
{
   if (is_string($val))
      return (stripslashes($val));
   if (!is_array($val))
      return $val;

   foreach ($val as $k=>$v)
      {
      $val[$k] = KRBWC__stripslashes ($v);
      }

   return $val;
}
//===========================================================================

//===========================================================================
/*
    ----------------------------------
    : Table 'krb_payments' :
    ----------------------------------
      status                "unused"      - never been used address with last known zero balance
                            "assigned"    - order was placed and this address was assigned for payment
                            "revalidate"  - assigned/expired, unused or unknown address suddenly got non-zero balance in it. Revalidate it for possible late order payment against meta_data.
                            "used"        - order was placed and this address and payment in full was received. Address will not be used again.
                            "xused"       - address was used (touched with funds) by unknown entity outside of this application. No metadata is present for this address, will not be able to correlated it with any order.
                            "unknown"     - new address was generated but cannot retrieve balance due to blockchain API failure.
*/
function KRBWC__create_database_tables ($krbwc_settings)
{
  global $wpdb;

  $krbwc_settings = KRBWC__get_settings();
  $must_update_settings = false;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'krbwc_persistent_settings';
  ///$karbo_clubs_table_name          = $wpdb->prefix . 'krbwc_karbo_clubs';
  $krb_payments_table_name             = $wpdb->prefix . 'krbwc_krb_payments';

  if($wpdb->get_var("SHOW TABLES LIKE '$krb_payments_table_name'") != $krb_payments_table_name)
      $b_first_time = true;
  else
      $b_first_time = false;

 //----------------------------------------------------------
 // Create tables
  /// NOT NEEDED YET
  /// $query = "CREATE TABLE IF NOT EXISTS `$persistent_settings_table_name` (
  ///   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ///   `settings` text,
  ///   PRIMARY KEY  (`id`)
  ///   );";
  /// $wpdb->query ($query);

  /// $query = "CREATE TABLE IF NOT EXISTS `$karbo_clubs_table_name` (
  ///   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ///   `master_public_key` varchar(255) NOT NULL,
  ///   PRIMARY KEY  (`id`),
  ///   UNIQUE KEY  `master_public_key` (`master_public_key`)
  ///   );";
  /// $wpdb->query ($query);

  $query = "CREATE TABLE IF NOT EXISTS `$krb_payments_table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `krb_address` char(98) NOT NULL,
    `krb_payment_id` char(64) NOT NULL,
    `origin_id` char(128) NOT NULL DEFAULT '',
    `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
    `status` char(16)  NOT NULL DEFAULT 'unknown',
    `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
    `assigned_at` bigint(20) NOT NULL DEFAULT '0',
    `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
    `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
    `address_meta` MEDIUMBLOB NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `krb_payment_id` (`krb_payment_id`),
    KEY `index_in_wallet` (`index_in_wallet`),
    KEY `origin_id` (`origin_id`),
    KEY `status` (`status`)
    );";
  $wpdb->query ($query);
  //----------------------------------------------------------

	// upgrade krbwc_krb_payments table, add additional indexes
  // if (!$b_first_time)
  // {
  //   $version = floatval($krbwc_settings['database_schema_version']);

  //   if ($version < 1.1)
  //   {

  //     $query = "ALTER TABLE `$krb_payments_table_name` ADD INDEX `origin_id` (`origin_id` ASC) , ADD INDEX `status` (`status` ASC)";
  //     $wpdb->query ($query);
  //     $krbwc_settings['database_schema_version'] = 1.1;
  //     $must_update_settings = true;
  //   }

  //   if ($version < 1.2)
  //   {

  //     $query = "ALTER TABLE `$krb_payments_table_name` DROP INDEX `index_in_wallet`, ADD INDEX `index_in_wallet` (`index_in_wallet` ASC)";
  //     $wpdb->query ($query);
  //     $krbwc_settings['database_schema_version'] = 1.2;
  //     $must_update_settings = true;
  //   }

  //   if ($version < 1.3)
  //   {

  //     $query = "ALTER TABLE `$krb_payments_table_name` CHANGE COLUMN `origin_id` `origin_id` char(128)";
  //     $wpdb->query ($query);
  //     $krbwc_settings['database_schema_version'] = 1.3;
  //     $must_update_settings = true;

  //     $address = @$krbwc_settings['gateway_settings']['electrum_master_public_key'];
  //     if ($address)
  //     {
  //       // Replace hashed values of MPK in DB with real MPK values.
  //       $address_old_value = 'electrum.mpk.' . md5($address);
  //       // UPDATE table_name SET field = REPLACE(field, 'foo', 'bar') WHERE INSTR(field, 'foo') > 0;
  //       // UPDATE [table_name] SET [field_name] = REPLACE([field_name], "foo", "bar");
  //       $query = "UPDATE `$krb_payments_table_name` SET `origin_id` = '$address' WHERE `origin_id` = '$address_old_value'";
  //       $wpdb->query ($query);

  //       // Copy settings from old location to new, if new is empty.
  //       if (!@$krbwc_settings['electrum_mpk_saved'])
  //       {
  //         $krbwc_settings['electrum_mpk_saved'] = $address;
  //         // 'KRBWC__update_settings()' will populate $krbwc_settings['electrum_mpks'].
  //       }
  //     }
  //   }

  //   if ($version < 1.4)
  //   {

  //     $query = "ALTER TABLE `$krb_payments_table_name` MODIFY `address_meta` MEDIUMBLOB";
  //     $wpdb->query ($query);
  //     $krbwc_settings['database_schema_version'] = 1.4;
  //     $must_update_settings = true;
  //   }

  // }

 //  if ($must_update_settings)
 //  {
	//   KRBWC__update_settings ($krbwc_settings);
	// }

  //----------------------------------------------------------
  // Seed DB tables with initial set of data
  /* PERSISTENT SETTINGS CURRENTLY UNUNSED
  if ($b_first_time || !is_array(KRBWC__get_persistent_settings()))
  {
    // Wipes table and then creates first record and populate it with defaults
    KRBWC__reset_all_persistent_settings();
  }
  */
   //----------------------------------------------------------
}
//===========================================================================

//===========================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function KRBWC__delete_database_tables ()
{
  global $wpdb;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'krbwc_persistent_settings';
  ///$karbo_clubs_table_name          = $wpdb->prefix . 'krbwc_karbo_clubs';
  $krb_payments_table_name    = $wpdb->prefix . 'krbwc_krb_payments';

  ///$wpdb->query("DROP TABLE IF EXISTS `$persistent_settings_table_name`");
  ///$wpdb->query("DROP TABLE IF EXISTS `$karbo_clubs_table_name`");
  $wpdb->query("DROP TABLE IF EXISTS `$krb_payments_table_name`");
}
//===========================================================================

