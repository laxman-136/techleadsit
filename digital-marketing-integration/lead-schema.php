<?php
if (!defined('ABSPATH')) exit;
function tlit_dm_answer($key,$value){
    $maps=array('student_status'=>array('studying'=>'Student / Currently studying','graduated'=>'Recent Graduate','first_job'=>'Looking for first job','other'=>'Other background'),'preferred_call_time'=>array('morning'=>'Morning (10:00 AM – 1:00 PM IST)','afternoon'=>'Afternoon (1:00 PM – 5:00 PM IST)','evening'=>'Evening (5:00 PM – 8:00 PM IST)','flexible'=>'Flexible — confirm with learner'),'goal'=>array('first_marketing_job'=>'First digital marketing job','internship'=>'Internship while studying','freelance'=>'Freelance projects','exploring'=>'Exploring digital marketing'),'start_timeline'=>array('soon'=>'As soon as possible','one_month'=>'Within a month','later'=>'Later','exploring'=>'Still exploring'),'main_question'=>array('career'=>'Career opportunities','beginner'=>'Starting as a beginner','fees'=>'Fees and payment','schedule'=>'Class schedule','projects'=>'Projects and portfolio','unsure'=>'Not sure yet'));
    return $maps[$key][$value]??$value;
}
function tlit_dm_tracking_fields($p) {
    $t=isset($p['tracking'])&&is_array($p['tracking'])?$p['tracking']:array();$out=array();
    foreach(explode(' ','utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content gclid gbraid wbraid fbclid fbp fbc ga_client_id session_id landing_page referrer fb_ad fb_campaign fb_adset_name fb_adset_id fb_lead_id adset_id ad_id city state') as $k){$out[$k]=isset($t[$k])&&is_string($t[$k])?substr(sanitize_text_field($t[$k]),0,250):'';}
    foreach(array('landing_page','referrer') as $k){$u=parse_url($out[$k]);$out[$k]=is_array($u)&&isset($u['scheme'],$u['host'])&&in_array($u['scheme'],array('http','https'),true)?$u['scheme'].'://'.$u['host'].(isset($u['port'])?':'.$u['port']:'').($u['path']??'/'):($k==='referrer'?'direct':'');}
    return $out;
}
function tlit_dm_lead_fields($p,$name,$phone,$email,$goal,$note) {
    $t=tlit_dm_tracking_fields($p);$d=isset($p['details'])&&is_array($p['details'])?$p['details']:array();
    $slot=isset($d['preferred_call_time'])&&is_string($d['preferred_call_time'])?substr(sanitize_text_field($d['preferred_call_time']),0,200):'';
    $profile=isset($d['student_status'])&&is_string($d['student_status'])?substr(sanitize_text_field($d['student_status']),0,200):'';
    $slot=tlit_dm_answer('preferred_call_time',$slot);$profile=tlit_dm_answer('student_status',$profile);
    $source='Website - Direct';$u=strtolower($t['utm_source']);
    if($t['gclid']||$t['gbraid']||$t['wbraid']||strpos($u,'google')!==false)$source='Google Ads';
    elseif($t['fbclid']||preg_match('/facebook|instagram|^fb$|^ig$|^meta$/',$u))$source='Facebook Ads';elseif($u&&$u!=='direct')$source=$t['utm_source'];
    $date=(new DateTime('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $f=array('name'=>$name,'phone'=>'+91'.$phone,'email'=>$email,'Course Name'=>'AI Powered+ Digital Marketing','Lead date'=>$date,'lead_date'=>$date,'leaddate'=>$date,'date'=>$date,'Your preferred time to call'=>$slot?:'Not specified — confirm with learner','City Name'=>$t['city']?:'Not detected','State Name'=>$t['state']?:'Not detected','Lead Source'=>$source,'Mode of Training'=>'Online Live Interactive Batch','Exp Level'=>$profile?:'Not specified','Remarks'=>$note,'Facebook Campaign'=>$t['fb_campaign']?:($source==='Facebook Ads'?$t['utm_campaign']:''),'Facebook Ad'=>$t['fb_ad']?:($source==='Facebook Ads'?$t['utm_content']:''),'Facebook Ad set Name'=>$t['fb_adset_name']?:($source==='Facebook Ads'?$t['utm_adgroup']:''),'Facebook Ad set ID'=>$t['fb_adset_id']?:$t['adset_id'],'Facebook Lead ID'=>$t['fb_lead_id']);
    $aliases=array('ga_client_id'=>'gaclientid','session_id'=>'sessionid','landing_page'=>'landingpage','utm_source'=>'utmsource','utm_medium'=>'utmmedium','utm_campaign'=>'utmcampaign','utm_adgroup'=>'utmadgroup','utm_term'=>'utmterm','utm_content'=>'utmcontent');
    foreach($t as $k=>$v){if(!in_array($k,array('city','state'),true))$f[$k]=$v;}
    foreach($aliases as $k=>$alias)$f[$alias]=$t[$k];
    return $f;
}
