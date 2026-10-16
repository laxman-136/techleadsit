<?php
if (!defined('ABSPATH')) exit;
function tlit_dm_v2_render($html,$assets,$options,$variant='v2',$slug='ai-powered-digital-marketing-course'){
    // Thank-you pages return to the routed landing page, not the plugin asset folder.
    $html=str_replace('href="./"','href="'.esc_url(home_url('/'.$slug.'/')).'"',$html);
    $html=tlit_dm_render_page($html,$assets,$options);
    $html=preg_replace('~<script[^>]+src="[^"]*preview-config\.js(?:\?[^"]*)?"[^>]*></script>~i','',$html);
    $config=['apiBase'=>rest_url('tlit-leads/v1'),'thankYouUrl'=>home_url('/'.$slug.'/thank-you/'),'pageVariant'=>$variant];
    if(function_exists('tlit_dm_ai_options')){$ai=tlit_dm_ai_options();$config['feeLabel']='₹'.number_format((float)$ai['fee'],0);}
    $chat=['mode'=>'live','apiBase'=>rest_url('tlit-dm/v1'),'geoEnabled'=>!empty($options['dm_geo_enabled']),'pageVariant'=>$variant];
    $injection='<script>window.TLITLeadConfig='.wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';window.COURSE_ENQUIRY_CONFIG='.wp_json_encode($chat,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>';
    return str_replace('</head>',$injection.'</head>',$html);
}
// Show all three variants under WordPress Pages on the next administrator visit.
// Their separate custom templates continue to be served by the plugin routes.
function tlit_dm_v2_register_page(){
    if(!current_user_can('manage_options'))return;
    foreach(array('v1'=>array('ai-powered-digital-marketing','AI Powered Digital Marketing (Original)'),'v2'=>array('ai-powered-digital-marketing-course','AI Powered Digital Marketing Course'),'v3'=>array('ai-powered-digital-marketing-training','AI Powered Digital Marketing Training')) as $variant=>$details){
        $option='tlit_dm_'.$variant.'_page_registered';if(get_option($option))continue;
        [$slug,$title]=$details;$page=get_page_by_path($slug,OBJECT,'page');
        if(!$page){$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>''],true);if(is_wp_error($id))continue;}else{$id=$page->ID;}
        update_option($option,(int)$id,false);
    }
}
add_action('admin_init','tlit_dm_v2_register_page');
