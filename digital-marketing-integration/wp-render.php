<?php
if (!defined('ABSPATH')) exit;
function tlit_dm_render_page($html,$asset_url,$opts) {
    // Production does not load local editing endpoints or draft comments.
    $html=preg_replace('~<script src="(?:copy-editor|feedback)\.js" defer></script>~','',$html);
    $html=preg_replace('~<link rel="stylesheet" href="copy-editor\.css">~','',$html);
    $date=esc_html($opts['batch_start_date']??'30 November 2026');$seats=esc_html($opts['countdown_seats_count']??'67');
    $html=str_replace(array('30 November 2026','30 Nov 2026','Monday–Friday, 8–9 PM IST'),array($date,$date,esc_html($opts['batch_timing']??'Monday–Friday, 8–9 PM IST')),$html);
    $html=preg_replace('~(<copy-text data-copy-id="copy-515">).*?(</copy-text>)~s','$1'.esc_html($opts['batch_title']??'Classes that fit into a real week.').'$2',$html);
    $html=preg_replace('~67 seats remaining~',$seats.' seats remaining',$html);
    // Keep zero as a real value; do not fabricate a replacement availability count.
    if(isset($opts['countdown_seats_count'])&&(string)$opts['countdown_seats_count']==='0')$html=str_replace('0 seats remaining','Current batch full',$html);
    $html=str_replace('<small>Updated 5 October 2026</small>','',$html);
    $html=preg_replace('~<small class="batch-updated">.*?</small>~','',$html);
    $html=preg_replace('~<[^>]+class="[^"]*pricing-updated[^"]*"[^>]*>.*?</[^>]+>~','',$html);
    $hide='';if(empty($opts['countdown_enabled']))$hide.='#countdownBar{display:none!important}';
    if(empty($opts['dm_seats_enabled']))$hide.='.batch-sticky-seats,.pricing-status,.cohort-availability{display:none!important}';
    if(empty($opts['batch_section_enabled']))$hide.='#cohort-schedule,#upcoming-cohort{display:none!important}';
    $config=array('mode'=>tlit_dm_ready()?'live':'preview','apiBase'=>rest_url('tlit-dm/v1'),'geoEnabled'=>!empty($opts['dm_geo_enabled']));
    if(tlit_dm_ready())$html=str_replace('This design preview only demonstrates the form and OTP screens; it sends no enquiry.','Phone verification confirms your number before your request is sent to the course team.',$html);
    $injection='<style>'.$hide.'</style><script>window.COURSE_ENQUIRY_CONFIG='.wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>';
    if(!empty($opts['dm_gtm_enabled'])){
        $gtm=get_option('gtm4wp-options',array());$id=is_array($gtm)?($gtm['gtm-code']??''):'';
        if(is_string($id)&&preg_match('/^GTM-[A-Z0-9]+$/D',$id)){
            $injection.="<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','".$id."');</script>";
        }
    }
    if(!empty($opts['dm_clarity_enabled']))$injection.='<script>(function(c,l,a,r,i){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};var t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;l.head.appendChild(t)})(window,document,"clarity","script","u5xu6vnb88");</script>';
    // Rewrite local files only; anchors and external links remain unchanged.
    $html=preg_replace_callback('~\b(src|href)="([^"#][^"]*)"~',function($m)use($asset_url){if(preg_match('~^(?:https?:|data:|mailto:|tel:|/)~',$m[2]))return $m[0];return $m[1].'="'.esc_url($asset_url.$m[2]).'"';},$html);
    return str_replace('<head>','<head>'.$injection,$html);
}
