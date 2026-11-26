<?php
if (!defined('ABSPATH')) exit;
require_once dirname(__DIR__).'/conversation-bridge/includes/attribution.php';
function tlit_dm_answer($key,$value){
    $maps=array('student_status'=>array('studying'=>'Student / Currently studying','graduated'=>'Recent Graduate','first_job'=>'Looking for first job','other'=>'Other background'),'preferred_call_time'=>array('morning'=>'Morning (10:00 AM – 1:00 PM IST)','afternoon'=>'Afternoon (1:00 PM – 5:00 PM IST)','evening'=>'Evening (5:00 PM – 8:00 PM IST)','flexible'=>'Flexible — confirm with learner'),'goal'=>array('first_marketing_job'=>'First digital marketing job','internship'=>'Internship while studying','freelance'=>'Freelance projects','exploring'=>'Exploring digital marketing'),'start_timeline'=>array('soon'=>'As soon as possible','one_month'=>'Within a month','later'=>'Later','exploring'=>'Still exploring'),'main_question'=>array('career'=>'Career opportunities','beginner'=>'Starting as a beginner','fees'=>'Fees and payment','schedule'=>'Class schedule','projects'=>'Projects and portfolio','unsure'=>'Not sure yet'));
    return $maps[$key][$value]??$value;
}
function tlit_dm_tracking_fields($p) {
    return TLIT_Attribution::normalize($p);
}
function tlit_dm_lead_fields($p,$name,$phone,$email,$goal,$note) {
    // Chat numbers arrive in E.164; legacy verified forms still supply 10 Indian digits.
    $international_phone=preg_match('/^\+[1-9][0-9]{6,14}$/D',$phone)?$phone:'+91'.$phone;
    $t=tlit_dm_tracking_fields($p);$d=isset($p['details'])&&is_array($p['details'])?$p['details']:array();
    $slot=isset($d['preferred_call_time'])&&is_string($d['preferred_call_time'])?substr(sanitize_text_field($d['preferred_call_time']),0,200):'';
    $profile=isset($d['student_status'])&&is_string($d['student_status'])?substr(sanitize_text_field($d['student_status']),0,200):'';
    $slot=tlit_dm_answer('preferred_call_time',$slot);$profile=tlit_dm_answer('student_status',$profile);
    $source='Website - Direct';$u=strtolower($t['utm_source']);
    if($t['gclid']||$t['gbraid']||$t['wbraid']||strpos($u,'google')!==false)$source='Google Ads';
    elseif($t['fbclid']||preg_match('/facebook|instagram|^fb$|^ig$|^meta$/',$u))$source='Facebook Ads';elseif($u&&$u!=='direct')$source=$t['utm_source'];
    $date=(new DateTime('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $f=array('name'=>$name,'phone'=>$international_phone,'email'=>$email,'Course Name'=>'AI Powered+ Digital Marketing','Lead date'=>$date,'lead_date'=>$date,'leaddate'=>$date,'date'=>$date,'Your preferred time to call'=>$slot?:'Not specified — confirm with learner','City Name'=>$t['city']?:'Not detected','State Name'=>$t['state']?:'Not detected','Lead Source'=>$source,'Mode of Training'=>'Online Live Interactive Batch','Exp Level'=>$profile?:'Not specified','Remarks'=>$note,'Facebook Campaign'=>$t['fb_campaign']?:($source==='Facebook Ads'?($t['campaign_name']?:$t['utm_campaign']):''),'Facebook Ad'=>$t['fb_ad']?:($source==='Facebook Ads'?($t['ad_name']?:$t['utm_content']):''),'Facebook Ad set Name'=>$t['fb_adset_name']?:($source==='Facebook Ads'?($t['adset_name']?:$t['utm_adgroup']):''),'Facebook Ad set ID'=>$t['fb_adset_id']?:$t['adset_id'],'Facebook Lead ID'=>$t['fb_lead_id']);
    $aliases=array('ga_client_id'=>'gaclientid','session_id'=>'sessionid','landing_page'=>'landingpage','utm_source'=>'utmsource','utm_medium'=>'utmmedium','utm_campaign'=>'utmcampaign','utm_adgroup'=>'utmadgroup','utm_term'=>'utmterm','utm_content'=>'utmcontent');
    foreach($t as $k=>$v){if(!in_array($k,array('city','state'),true))$f[$k]=$v;}
    foreach($aliases as $k=>$alias)$f[$alias]=$t[$k];
    $names=TLIT_Attribution::summary($t);
    if($source==='Facebook Ads') foreach(array('Facebook Campaign'=>'Campaign name','Facebook Ad'=>'Ad name','Facebook Ad set Name'=>'Ad set / ad group name') as $field=>$label) $f[$field]=$names[$label]==='Not recorded'?'':$names[$label];
    foreach(array('utmcampaign'=>'Campaign name','utmcontent'=>'Ad name','utmadgroup'=>'Ad set / ad group name') as $field=>$label) { if($f[$field]===''&&$names[$label]!=='Not recorded') $f[$field]=$names[$label]; }
    return $f;
}
