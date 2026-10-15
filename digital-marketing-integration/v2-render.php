<?php
if (!defined('ABSPATH')) exit;
function tlit_dm_v2_render($html,$assets,$options){
    $html=tlit_dm_render_page($html,$assets,$options);
    $config=['apiBase'=>rest_url('tlit-leads/v1'),'thankYouUrl'=>home_url('/ai-powered-digital-marketing-course/thank-you/')];
    if(function_exists('tlit_dm_ai_options')){$ai=tlit_dm_ai_options();$config['feeLabel']='₹'.number_format((float)$ai['fee'],0);}
    $chat=['mode'=>'live','apiBase'=>rest_url('tlit-dm/v1'),'geoEnabled'=>!empty($options['dm_geo_enabled'])];
    $injection='<script>window.TLITLeadConfig='.wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';window.COURSE_ENQUIRY_CONFIG='.wp_json_encode($chat,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>';
    return str_replace('</head>',$injection.'</head>',$html);
}
// Add an ordinary WordPress Pages entry on the next administrator visit after update.
// Routing serves the custom template; the original landing page is preserved.
function tlit_dm_v2_register_page(){
    if(!current_user_can('manage_options')||get_option('tlit_dm_v2_page_registered'))return;
    $slug='ai-powered-digital-marketing-course';$page=get_page_by_path($slug,OBJECT,'page');
    if(!$page){$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'AI Powered Digital Marketing Course','post_name'=>$slug,'post_content'=>''],true);if(is_wp_error($id))return;}else{$id=$page->ID;}
    update_option('tlit_dm_v2_page_registered',(int)$id,false);
}
add_action('admin_init','tlit_dm_v2_register_page');
