<?php
/**
 * Select approved facts for an answer, never a prewritten answer.
 * A conservative full-fact fallback preserves coverage for unclear/new-language queries.
 */

function tlit_dm_ai_knowledge_topics() {
 return array(
  'price'=>array('pattern'=>'/\b(fee\w*|feee\w*|price\w*|cost\w*|afford\w*|expensive|cheap\w*|worth|value|budget|discount\w*|scholarship\w*|offer\w*|tax\w*|gst|inclusive|exclusive|payable|total|entha|kitn[ai]|paisa|paise)\b/i','ids'=>array('price-value','session-offers','terms','tool-access','projects')),
  'payment'=>array('pattern'=>'/\b(pay\w*|paid|payment\w*|instal\w*|install\w*|emi|split|balance|remaining|second|month\w*|later|deadline\w*|interest|financ\w*|razorpay|invoice\w*|receipt\w*|family|relative|overdue|extension\w*|penalt\w*)\b/i','ids'=>array('payment-plan','payment-charges','payment-channel','terms')),
  'refund'=>array('pattern'=>'/\b(refund\w*|money\s+back|return\s+(?:my\s+)?money|withdraw\w*|cancel\w*|reschedul\w*|change\s+(?:my\s+)?mind|fourteen|14\s*days?|48\s*hours?)\b/i','ids'=>array('refund-window','batch-cancellation','payment-plan','payment-channel')),
  'career'=>array('pattern'=>'/\b(job\w*|career\w*|placement\w*|placment\w*|hire\w*|hiring|recruit\w*|employ\w*|interview\w*|resume\w*|cv|portfolio|referral\w*|refer\w*|first\s+role|work\s+experience)\b/i','ids'=>array('placement-details','projects','interview-evidence')),
  'role-direction'=>array('pattern'=>'/\b(roles?|positions?|executive|trainee|associate|specialist|career\s+(?:paths?|options?)|(?:which|what|types?\s+of)\s+(?:jobs?|careers?))\b/i','ids'=>array('role-paths','placement-details','projects','interview-evidence')),
  'career-conditions'=>array('pattern'=>'/\b(vacanc\w*|openings?|referral\w*|refer\w*|hiring\s+partners?|employer\s+(?:partners?|requirements?|criteria)|assigned\s+(?:counsellor|mentor)|dedicated\s+(?:counsellor|mentor)|interview\s+(?:commitments?|counts?|opportunities)|(?:placement|job)\s+(?:eligib\w*|criteria|conditions?|requirements?|fees?|checklist|rules?)|(?:eligib\w*|criteria|conditions?|requirements?|rules?)\s+(?:for\s+)?(?:placement|jobs?)|declin\w*\s+(?:a\s+|an\s+)?(?:job\s+)?offer|resubmi\w*|late\s+participation)\b/i','ids'=>array('placement-details','career-arrangements','placement-eligibility','career-background','role-paths')),
  'outcome-certainty'=>array('pattern'=>'/\b(guarantee\w*|assur\w*|definitely|certainly|for\s+sure|sure\s+(?:job|placement|income|revenue)|pakka|promis\w*)\b|100\s*%(?!\s+(?:(?:placement|job|career)\s+)?assistance\b)/i','ids'=>array('guarantee','placement-details','placement-eligibility','career-arrangements','career-background','role-paths','projects','business')),
  'internship'=>array('pattern'=>'/\b(intern\w*|stipend\w*|apprentice\w*|paid\s+(?:work|experience))\b/i','ids'=>array('internship','certificate','placement-eligibility','guarantee')),
  'salary'=>array('pattern'=>'/\b(salary|salaries|package\w*|pakage\w*|ctc|lpa|lakh\w*|earn\w*|income|take\s*home|take-home|compensation|probation|bond\w*|wage\w*)\b/i','ids'=>array('salary-reference','job-terms','guarantee','role-paths')),
  'location'=>array('pattern'=>'/\b(remote|hybrid|abroad|overseas|hometown|relocat\w*|shift\w*|country|countries|visa|work\s+from\s+home|wfh)\b/i','ids'=>array('work-location','career-background','format','batch-options')),
  'certificate'=>array('pattern'=>'/\b(certificat\w*|credential\w*|attendance|96\s*%|ninety.six|accredit\w*|mock\s+(?:test|exam)|passing|pass\s+mark\w*|complete\s+(?:the\s+)?course)\b/i','ids'=>array('certificate','placement-eligibility','internship','interview-evidence')),
  'admission'=>array('pattern'=>'/\b(eligib\w*|admission\w*|prerequis\w*|qualifi\w*|degree\w*|graduate\w*|college|intermediate|school\w*|drop.?out\w*|ssc|sslc|hsc|education\w*|standard|class\s*(?:10|11|12)|grade\s*(?:10|11|12)|10th|11th|12th|tenth|eleventh|twelfth|age|backlog\w*|marks|beginner\w*|fresher\w*|zero|start\s+from|no\s+experience|without\s+experience|non.?technical|non.?marketing)\b/i','ids'=>array('admission','beginner','coding','learning-fit','career-background')),
  'fit'=>array('pattern'=>'/\b(fit|suit\w*|right\s+for|switch\w*|gap|working\s+professional|already\s+(?:work|know)|experienced|advance\w*|promotion|student\w*|studying|study|confiden\w*)\b/i','ids'=>array('learning-fit','experienced-fit','career-background','admission','beginner','projects','placement-details')),
  'business'=>array('pattern'=>'/\b(business\w*|shops?|stores?|bakery|salon|boutique|retail\w*|small\s+business|freelanc\w*|agency|agencies|client\w*|entrepreneur\w*|owner\w*|startup\w*|revenue|sales|customer\w*|lead\s+generation|generate\s+leads)\b/i','ids'=>array('business','freelance-agency','research-brand','projects')),
  'schedule'=>array('pattern'=>'/\b(batch\w*|start\w*|timing\w*|timetable\w*|schedule\w*|duration|long|week\w*|hour\w*|daily|date\w*|weekend\w*|morning\w*|evening\w*|online|offline|classroom|zoom|sr\s*nagar|pause|transfer|break)\b/i','ids'=>array('duration','format','batch-options','batch-transfer','demo')),
  'learning-support'=>array('pattern'=>'/\b(record\w*|miss\w*|catch\s+up|fall\s+behind|material\w*|lms|support|help|doubt\w*|revision|one.to.one|personal\s+attention|class\s+size|group\s+size|feedback|mentor\w*)\b/i','ids'=>array('recording','support','class-experience','batch-transfer','format')),
  'language'=>array('pattern'=>'/\b(language\w*|telugu|hindi|english|tamil|kannada|fluenc\w*|communication|bol\w*|bhasha|accent|caption\w*|accessib\w*|deaf|hearing|disab\w*)\b/i','ids'=>array('language','accessibility','career-background','format')),
  'equipment'=>array('pattern'=>'/\b(laptop\w*|computer\w*|phone|mobile|ram|processor|device\w*|equipment|coding|code|technical\s+skills|design\s+skills|existing\s+(?:website|business))\b/i','ids'=>array('equipment','coding','practice-website','admission')),
  'curriculum'=>array('pattern'=>'/\b(syllabus|curricul\w*|module\w*|capstone\w*|project\w*|learn\w*|teach\w*|topic\w*|subject\w*|cover\w*|course\s+content|practic\w*|hands.on|theory|output\w*)\b/i','ids'=>array('syllabus','projects','project-scope','tools','tool-access')),
  'tools'=>array('pattern'=>'/\b(tool\w*|software|subscription\w*|licen[cs]\w*|access|semrush|elementor|wordpress|hosting|domain\w*|website\w*|no.code|n\s*8\s*n|lifetime|renew\w*)\b/i','ids'=>array('tools','tool-access','practice-website','tool-changes','coding','recording')),
  'seo'=>array('pattern'=>'/\b(seo|search\s+engine|keyword\w*|rank\w*|backlink\w*|organic|aeo|geo|google\s+business|local\s+search|technical\s+audit)\b/i','ids'=>array('seo','projects','tools','research-brand')),
  'ads'=>array('pattern'=>'/\b(advertis\w*|ads?|ppc|paid\s+media|performance\s+marketing|media\s+buying|campaign\w*|ad\s*spend|practice\s+(?:money|spend|budget)|google\s+search|meta|facebook|instagram)\b/i','ids'=>array('ads','analytics','projects','session-offers')),
  'content'=>array('pattern'=>'/\b(content|social|copywriting|creative\w*|video\w*|reels?|calendar\w*|branding|positioning|canva|capcut)\b/i','ids'=>array('content-social','research-brand','ai-practice','projects')),
  'automation'=>array('pattern'=>'/\b(automat\w*|crm|webhook\w*|workflow\w*|follow.up|whatsapp|email\s+marketing|mailchimp|funnel\w*|n\s*8\s*n|make\.com)\b/i','ids'=>array('automation','tools','projects','tool-access')),
  'analytics'=>array('pattern'=>'/\b(analytic\w*|ga\s*4|gtm|tag\s+manager|tracking|pixel\w*|conversion\w*|utm|report\w*|measure\w*|roi|roas)\b/i','ids'=>array('analytics','ads','automation','projects')),
  'ai'=>array('pattern'=>'/\b(ai|artificial\s+intelligence|chatgpt|gemini|claude|perplexity|prompt\w*|hallucinat\w*)\b/i','ids'=>array('ai-practice','tools','automation','tool-changes')),
  'demo'=>array('pattern'=>'/\b(demo|live\s+session|webinar|enrol\w*|enroll\w*|register\w*|join\w*|book\w*|reserve\w*|free\s+(?:class|session|trial|course|video))\b/i','ids'=>array('demo','enrolment-flow','session-offers','free-resources','terms','admission','learning-fit','beginner')),
  'brochure'=>array('pattern'=>'/\b(brochure\w*|download\w*|pdf|otp|verif\w*|contact\s+details|email\s+(?:address|id)|phone\s+number|mobile\s+number|thank.you)\b/i','ids'=>array('brochure-flow','enrolment-flow','contact-preferences')),
  'privacy'=>array('pattern'=>'/\b(privac\w*|data|store\w*|shar\w*|personal\s+information|retain\w*|retention|delete\w*|deletion|unsubscribe|consent|spam|contact\s+preference|api|provider)\b/i','ids'=>array('privacy','contact-preferences','brochure-flow')),
  'trust'=>array('pattern'=>'/\b(trainer\w*|veera|lakshman|mentor\w*|review\w*|trust\w*|proof|evidence|alumni|partner\w*|reputation|scam|legit\w*|best|better|competitor\w*|compare|comparison|another\s+institute|youtube|udemy|why\s+(?:you|tech))\b/i','ids'=>array('trainer','evidence','brand-value','free-resources','projects')),
 );
}

function tlit_dm_ai_knowledge_tokens($text) {
 $text=strtolower((string)$text);
 preg_match_all('/[a-z][a-z0-9-]{2,}/',$text,$matches);
 $stop=array_flip(explode(' ','the and for that this with what how why when where who which does have has are can could would should will about from into your you our their they them there these those just like know want need please tell course programme program marketing digital learn learning trained training much more also only any get give help first then after before now later some all using used use ask question answer really make take good available provide provided receive through during within current confirmed approved stated team learner learners specific requirements information include includes including included explain details support'));
 return array_values(array_diff(array_unique($matches[0]),array_keys($stop)));
}

function tlit_dm_ai_select_knowledge($question,$history,$records,$memory=array()) {
 if(!is_array($records))return array();
 $question=trim((string)$question);
 // These are data, not instructions: uncertain language/follow-ups keep every fact.
 if($question===''||preg_match('/[^\x00-\x7F]/u',$question))return $records;
 $topics=tlit_dm_ai_knowledge_topics();$active=array();
 foreach($topics as $name=>$topic)if(preg_match($topic['pattern'],$question))$active[$name]=true;
 if(!$active)return $records;
 // Broad requests such as "what topics?" do not identify a safe fact subset.
 if(count($active)===1&&isset($active['curriculum']))return $records;
 if(count($active)===1&&isset($active['learning-support'])&&!preg_match('/\b(record\w*|miss\w*|catch\s+up|fall\s+behind|material\w*|lms|doubt\w*|revision|class\s+size|group\s+size)\b/i',$question))return $records;

 // Key eligibility/payment conditions stay available even when wording is indirect.
 $wanted=array_fill_keys(array('duration','syllabus','terms','admission','refund-window','payment-plan','certificate','equipment'),true);
 foreach(array_keys($active) as $topic)foreach($topics[$topic]['ids'] as $id)$wanted[$id]=true;
 // A short follow-up can change topic: retain the last visitor's topic as context.
 $followup=count(tlit_dm_ai_knowledge_tokens($question))<=5||preg_match('/\b(it|that|those|them|also|instead|then|same|afterwards|what\s+about)\b/i',$question);
 if($followup&&is_array($history)){
  foreach(array_reverse($history) as $turn){
   if(!is_array($turn)||($turn['role']??'')!=='user')continue;
   $previous=(string)($turn['content']??'');
   if(preg_match('/[^\x00-\x7F]/u',$previous))return $records;
   foreach($topics as $name=>$topic){
    if($name==='career'&&isset($active['business'])&&!isset($active['career']))continue;
    if($name==='business'&&isset($active['career'])&&!isset($active['business']))continue;
    if(preg_match($topic['pattern'],$previous))foreach($topic['ids'] as $id)$wanted[$id]=true;
   }
   break;
  }
 }
 // Explicit visitor context enriches follow-ups; it never replaces the latest question.
 // A targeted new policy/schedule question does not need old goals or concerns.
 $contextual=array_intersect(array_keys($active),array('fit','admission','career','business','price'));
 $topicChange=array_intersect(array_keys($active),array('refund','payment','certificate','schedule','privacy','brochure','outcome-certainty','career-conditions'));
 if(is_array($memory)&&($contextual||($followup&&!$topicChange))){
  $context='';
  foreach(array('goal','background','target_role','concern') as $field)if(isset($memory[$field])&&is_string($memory[$field]))$context.=' '.$memory[$field];
  foreach(array('career','role-direction','career-conditions','business','fit','seo','ads','content','analytics','automation','tools','learning-support','price','payment') as $name){
   // Explicit career/business direction in this turn takes precedence over older context.
   if(in_array($name,array('career','role-direction','career-conditions'),true)&&isset($active['business'])&&!isset($active['career']))continue;
   if($name==='business'&&isset($active['career'])&&!isset($active['business']))continue;
   if($context!==''&&preg_match($topics[$name]['pattern'],$context))foreach($topics[$name]['ids'] as $id)$wanted[$id]=true;
  }
 }

 // Vocabulary overlap supplements topics; metadata is retrieval-only, never sent as facts.
 $queryTokens=tlit_dm_ai_knowledge_tokens($question);
 foreach($records as $record){
  if(!is_array($record)||empty($record['id']))continue;
  // Support and certainty share words such as "placement" and "hiring team";
  // vocabulary overlap alone must not turn an assistance question into a guarantee one.
  if($record['id']==='guarantee'&&!isset($wanted['guarantee']))continue;
  $search=(string)($record['text']??'');
  if(isset($record['question_examples'])&&is_array($record['question_examples']))$search.=' '.implode(' ',array_filter($record['question_examples'],'is_string'));
  if(count(array_intersect($queryTokens,tlit_dm_ai_knowledge_tokens($search)))>=3)$wanted[$record['id']]=true;
 }
 $known=array();foreach($topics as $topic)foreach($topic['ids'] as $id)$known[$id]=true;
 $selected=array();
 foreach($records as $record){
  if(!is_array($record)||empty($record['id']))continue;
  $id=(string)$record['id'];
  // Never drop live settings, owner additions, or newly introduced facts until indexed.
  if(isset($wanted[$id])||strpos($id,'current-')===0||strpos($id,'approved-')===0||!isset($known[$id]))$selected[]=$record;
 }
 return $selected?$selected:$records;
}

function tlit_dm_ai_compact_knowledge($records) {
 $facts=array();
 foreach((array)$records as $record){
  if(!is_array($record)||empty($record['id'])||!isset($record['text'])||!is_string($record['text']))continue;
  $facts[(string)$record['id']]=$record['text'];
 }
 $flags=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
 return function_exists('wp_json_encode')?wp_json_encode($facts,$flags):json_encode($facts,$flags);
}
