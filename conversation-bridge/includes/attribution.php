<?php
/** Shared visitor attribution contract. Never accept authentication fields here. */
if (!defined('ABSPATH')) { exit; }
final class TLIT_Attribution {
    public static function keys() {
        return explode(' ', 'fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer utm_id campaign_name ad_name adset_name campaign_id adset_id ad_id fb_campaign fb_ad fb_adset_name fb_adset_id fb_lead_id city state');
    }
    public static function normalize($input) {
        $input = is_array($input) ? $input : array();
        $raw = array_merge($input, is_array($input['tracking'] ?? null) ? $input['tracking'] : array());
        $aliases = array('gaclientid'=>'ga_client_id','sessionid'=>'session_id','landingpage'=>'landing_page','utmsource'=>'utm_source','utmmedium'=>'utm_medium','utmcampaign'=>'utm_campaign','utmadgroup'=>'utm_adgroup','utmterm'=>'utm_term','utmcontent'=>'utm_content','ad_set_name'=>'adset_name','adset'=>'adset_name');
        foreach ($aliases as $alias=>$key) { if (empty($raw[$key]) && isset($raw[$alias])) { $raw[$key]=$raw[$alias]; } }
        $out = array();
        foreach (self::keys() as $key) {
            $value = is_scalar($raw[$key] ?? null) ? sanitize_text_field((string)$raw[$key]) : '';
            $out[$key] = function_exists('mb_substr') ? mb_substr($value,0,512) : substr($value,0,512);
        }
        foreach (array('landing_page','referrer') as $key) {
            $url = parse_url($out[$key]);
            $out[$key] = is_array($url) && isset($url['scheme'],$url['host']) && in_array(strtolower($url['scheme']),array('https','http'),true)
                ? strtolower($url['scheme']).'://'.$url['host'].(isset($url['port'])?':'.$url['port']:'').($url['path']??'/') : ($key==='referrer'?'direct':'');
        }
        return $out;
    }
    public static function summary($input) {
        $t=self::normalize($input);
        return array('Campaign name'=>$t['campaign_name']?:($t['fb_campaign']?:($t['utm_campaign']?:'Not recorded')),
            'Ad name'=>$t['ad_name']?:($t['fb_ad']?:($t['utm_content']?:'Not recorded')),
            'Ad set / ad group name'=>$t['adset_name']?:($t['fb_adset_name']?:($t['utm_adgroup']?:'Not recorded')));
    }
    public static function display($input) {
        $t=self::normalize($input);$out=self::summary($t);
        $labels=array('fbp'=>'Meta browser ID (fbp)','fbc'=>'Meta click cookie (fbc)','gclid'=>'Google click ID (gclid)','gbraid'=>'Google GBRAID','wbraid'=>'Google WBRAID','fbclid'=>'Facebook click ID (fbclid)','ga_client_id'=>'Analytics client ID','session_id'=>'Analytics session ID','utm_source'=>'UTM source','utm_medium'=>'UTM medium','utm_campaign'=>'UTM campaign','utm_adgroup'=>'UTM ad group','utm_term'=>'UTM term','utm_content'=>'UTM content','landing_page'=>'Landing page','referrer'=>'Referrer');
        foreach ($t as $key=>$value) { $out[$labels[$key]??ucwords(str_replace('_',' ',$key))]=$value!==''?$value:'Not recorded'; }
        return $out;
    }
    public static function answers($details) {
        $out=array();
        foreach (is_array($details)?$details:array() as $key=>$value) {
            if (!is_string($key)||!is_scalar($value)||preg_match('/otp|challenge|token|secret|password|api_?key|session_?hash|verification_?code/i',$key)) { continue; }
            $out[ucwords(str_replace('_',' ',$key))]=sanitize_text_field((string)$value);
        }
        return $out;
    }
    public static function note($input,$note) {
        $note.="\n\nCampaign attribution";
        foreach(self::summary($input) as $key=>$value) { $note.="\n".$key.': '.$value; }
        foreach(self::normalize($input) as $key=>$value) { if($value!=='') { $note.="\n".$key.': '.$value; } }
        $answers=self::answers($input['details']??array());
        if($answers) { $note.="\n\nCaptured form answers";foreach($answers as $key=>$value) { $note.="\n".$key.': '.$value; } }
        return $note;
    }
}
