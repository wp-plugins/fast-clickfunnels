<?php

class fast_CF_IPN_Class {

    function __construct() {
        require_once( plugins_url('/fastmember/lib/common.php') );
        $this->FCF_process_ipn_data();
    }

    function get_CF_product_data($CF_prodid) {
        if ( !isset( $CF_prodid ) || $CF_prodid == "" ) 
            return false;
        global $wpdb;
        $proddata = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbn_products 
                WHERE fcfprodid=%s", $CF_prodid ) );
        if( count( $proddata )>0 )
            return $proddata[0];
        else return false;
    }

    function FM_existing_CFTXN_exit($txn_id) {
        global $wpdb;
        $stub = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpbn_transactions WHERE txn_id LIKE %s", '%'.$txn_id.'[%') );
        if ($stub) {
            fmLog(_fm("Transaction ID already in transaction table"));
            exit;
        }
    }

    function FCF_process_ipn_data() {

        global $wpdb;
        $txn_id = sanitize_text_field( $_POST["charge_id"] );
        $this->FM_existing_CFTXN_exit($txn_id);
        $raw_data = file_get_contents("php://input");
        fmLog("Received ClickFunnels raw data.");
        $prss_datas = explode("&", $raw_data);
        $obj_prod_datas = array();
        $oidx = 0;
        foreach ($prss_datas as $prss_data) {
            $chk_prods = strpos($prss_data, "products=");
            if ( $chk_prods !== false ) {
                $prod_data00 = str_replace("products=", "", $prss_data);
                $prod_data01 = urldecode($prod_data00);
                $prod_data02 = str_replace('\"', '', $prod_data01);
                $prod_data03 = str_replace('"', '', $prod_data02);
                $prod_data21 = str_replace('=>', ' : ', $prod_data03);
                $prod_data22 = str_replace(' : ', '" : "', $prod_data21);
                $prod_data23 = str_replace(', ', '", "', $prod_data22);
                $prod_data24 = str_replace('{}', '', $prod_data23);
                $prod_data25 = str_replace('{', '{ "', $prod_data24);
                $prod_data26 = str_replace('}', '" }', $prod_data25);
                $prod_data27 = str_replace('"{ ', '{', $prod_data26);
                $prod_data28 = str_replace('}"', '}', $prod_data27);
                $prod_data29 = str_replace('"[', '["', $prod_data28);
                $prod_data30 = str_replace(']"', '"]', $prod_data29);
                $tmp_json_data = json_decode($prod_data30);
                $chk_prod_id = sanitize_text_field( $tmp_json_data->id );
                $chk_prod = $this->get_CF_product_data( $chk_prod_id );
                if ( $chk_prod !== false ) {
                    $obj_prod_datas[$oidx] = $tmp_json_data;
                    $oidx++;
                }
            }
        }

        if ( $oidx>0 ) {
            fmLog("Received ClickFunnels data for listed FM products.");
            $sntz_cntct_info = sanitize_text_field( $_POST['contact'] );
            $data_cntct_info20 = str_replace('\"', '', $sntz_cntct_info);
            $data_cntct_info21 = str_replace('=>', ' : ', $data_cntct_info20);
            $data_cntct_info22 = str_replace(' : ', '" : "', $data_cntct_info21);
            $data_cntct_info23 = str_replace(', ', '", "', $data_cntct_info22);
            $data_cntct_info24 = str_replace('{}', '', $data_cntct_info23);
            $data_cntct_info25 = str_replace('{', '{ "', $data_cntct_info24);
            $data_cntct_info26 = str_replace('}', '" }', $data_cntct_info25);
            $data_cntct_info27 = str_replace('"{ ', '{', $data_cntct_info26);
            $data_cntct_info28 = str_replace('}"', '}', $data_cntct_info27);
            $data_cntct_info29 = str_replace('"[', '["', $data_cntct_info28);
            $data_cntct_info30 = str_replace(']"', '"]', $data_cntct_info29);
            $obj_cntct_info = json_decode($data_cntct_info30);
            $usermail = sanitize_email( $obj_cntct_info->email );
            $userid = FM_get_buyer_UID($usermail) ;
            $isnewuser = 0;
            if (!$userid) {
                $livepass = wp_generate_password();
                $userid = wp_create_user($usermail, $livepass, $usermail);
                if (is_object($userid)) {
                    $userid = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}users WHERE user_login=%s", $usermail ) );
                    fmLog("New user creating error.");
                } else {
                    $isnewuser = 1;
                    fmLog("New user: $userid");
                }
                if (isset($obj_cntct_info->first_name)) 
                    $fname = sanitize_text_field( $obj_cntct_info->first_name );
                else 
                    $fname = "";
                if (isset($obj_cntct_info->last_name)) 
                    $lname = sanitize_text_field( $obj_cntct_info->last_name );
                else 
                    $lname = "";
                update_user_meta($userid, 'first_name', $fname);
                update_user_meta($userid, 'last_name', $lname);
                update_user_meta($userid, 'nickname', _fm('New Member'));
            }
            if (!$userid) {
                $userid = 0;
                fmLog("Found no userid.");
                exit;
            }
        } else {
            fmLog("Received ClickFunnels data for nonlisted FM products.");
            exit;
        }

        $now = time();

        foreach ($obj_prod_datas as $obj_prod_data) {
            $jstr_prod_data = json_encode($obj_prod_data);
            fmLog("Received data in JSON string format.");
            $CF_prodid = sanitize_text_field( $obj_prod_data->id );
            $proddata = $this->get_CF_product_data( $CF_prodid );
            $prodid = $proddata->id;
            $pamount2 = sanitize_text_field( $obj_prod_data->amount_cents );
            $pamount = (float)$pamount2/100 ;
            $recpaidamount = $pamount;
            $expires = 0;
            $addinterval = 100*365*24*3600; // default - one time payment - 100 years (lifetime)
            if ($proddata->isrecurr) {
                $expires = $now;
                $numpayments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}wpbn_transactions 
                                        WHERE userid=%s AND prodid=%s", $userid, $prodid ) );

                if ( $pamount == $proddata->trialprice ) {
                    $trialqty = $proddata->trialquantity;
                    $fmperioddays = fastmemgetPeriodDays($proddata->trialperiod);
                    $addinterval = 24*3600*$trialqty*$fmperioddays;
                } else {
                    $paidqty = $proddata->paidquantity;
                    $addinterval = 24*3600*$paidqty*fastmemgetPeriodDays($proddata->paidperiod);
                    $exp = $wpdb->get_results( $wpdb->prepare( "SELECT expires FROM {$wpdb->prefix}wpbn_users 
                            WHERE (expires>%d) AND (userid=%s) AND (prodid=%s)", 1, $userid, $prodid ) );
                    if (count($exp)) $expires = $exp[0]->expires;
                    if ($proddata->numpayments) {
                        $needpayments = $proddata->numpayments;
                        if ($proddata->trialquantity) $needpayments++;
                        $numpayments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}wpbn_transactions 
                                            WHERE (userid=%s) AND (prodid=%s)", $userid, $prodid ) );
                        if ($numpayments >= $needpayments) $addinterval = 100*365*24*3600; //all installments paid
                    }
                }
            } 

            $newexpire = $expires + $addinterval;
            $newmembership = 0;

            $stub = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpbn_users WHERE (userid=%s) AND (prodid=%s)", $userid, $prodid ) );
            if ($stub) {
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpbn_users 
                        SET expires='$newexpire' WHERE (userid=%s) AND (prodid=%s);", $userid, $prodid ) );
                fmLog("Existing membership for userid=$userid AND prodid=$prodid");
            } else {
                $newmembership = 1;
                $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}wpbn_hits (rdparty, madesale, userid, prodid, price, postid, affid, track, htime, userip, rapisaff, otoid, coupon) 
                        VALUES (%d, %d, %s, %s, %s, %d, %d, %s, %s, %s, %d, %d, %s);",12, 1, $userid, $prodid, $recpaidamount, 0, 0, '', $now, '', 0, 0, '' ) );
                $hitid = $wpdb->insert_id;
                $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}wpbn_users 
                                        (hitid, prodid, userid, affid, tpurchased, expires, affcomm, innersubscr) 
                                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)", $hitid, $prodid, $userid, '1', $now, $newexpire, '50', $proddata->addintar ) );
                fmLog("New membership for userid=$userid AND prodid=$prodid");
                fmAddToAR($userid, 1);
            }
            $txn_rec_id = $txn_id . "[" . $prodid . "]";
            fmLog("New TXN for txn_id=$txn_rec_id");
            //record the transaction
            $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}wpbn_transactions (pp, txn_id, amount, userid, affid, affiliateemail, affcomm, israp, ttransaction, payer_email, prodid) 
                                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s);", '12', $txn_rec_id, $recpaidamount, $userid, '0', '', '0', '0', $now, $usermail, $prodid ) );


            if ($newmembership) {
                $postid = $wpdb->get_var( $wpdb->prepare( "SELECT loginpage FROM {$wpdb->prefix}wpbn_products WHERE id=%s", $prodid ) );
                if (!$postid) $postid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}posts WHERE post_content like %s", '%[fastmemloginform product="' . $prodid . '"]%' ) );
                if (!$postid) $postid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}posts WHERE post_content like %s", '%[wpbuynowloginform product="' . $prodid . '"]%' ) );
                if ($postid) $url = get_permalink($postid);
                else $url = site_url('/wp-login.php');
                $userdata = get_userdata($userid);
                if($isnewuser == 0) $livepass = FM_get_preexist_message();
                fmSendWelcomeEmail($prodid, $userid, $livepass);
                if (!fmSendConfirmARMessage($proddata, $userdata)) fmSendFirstARMessage($proddata, $userdata);
            }
        }

        fmLog("Made it to the end of the ClickFunnels IPN processing code");
        exit;
        
    }


}
