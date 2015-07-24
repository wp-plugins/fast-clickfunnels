<?php

class fast_CF_Main_Class {
    function __construct() {
        if ( is_admin() ) {
            add_filter('fm_prod_third_party_int', array($this, 'fcf_third_party_int_html' ), 10, 1);
        }
    }
    
    function fcf_third_party_int_html($content) {

        $fcfprodid = sanitize_text_field( $_POST["fcfprodid"] );

        $content .= "<br/><br/><h2>"._fm('ClickFunnels Integration')."</h2>
                    "._fm('You are ready to integrate with ClickFunnels')." (<a href='https://www.clickfunnels.net/' target='_blank'>www.clickfunnels.net</a>).<br />
                    "._fm('In your ClickFunnels account please choose Access Integrations from the Edit Funnel options and add the following URL into the Webhook URL field:'). "<br/>
                    <input type='text' value='". site_url("/") ."?fcfipn_api=fast_CF_IPN_handle' readonly style='width: 100%;' /><br /><br />
                    "._fm('ClickFunnels Product  ID #')."&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;<input type='text' id='fcfprodid' style='width: 30%;' maxlength='20' name='fcfprodid' value='$fcfprodid' /><br/><br/>";
        return $content;
    }
}