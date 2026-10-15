<?php
/** Conservative, dependency-free fallback for sessions without an AI learning-goal summary. */
final class TLIT_CB_Goals {
    const VERSION = 1;
    const EMPTY_SUMMARY = 'Not enough conversation yet to identify a learning goal.';
    const REVISED_SUMMARY = 'Learning goal not yet clear — the visitor revised their direction.';

    public static function is_fallback($goal) {
        $goal = self::clean($goal);
        return $goal === '' || preg_match('/^(?:Not provided|Not enough conversation|Learning goal not yet clear|Exploring course details|Exploring digital marketing topics|General course enquiry|Undecided|Unknown|Not yet (?:known|clear|identified))\b/iu', $goal) === 1;
    }

    /** Messages are ordered oldest first. Only visitor-authored question content is evidence. */
    public static function analyze($messages, $model_goal = '', $cleared = false) {
        if ($cleared) {
            return self::result(self::REVISED_SUMMARY, 'insufficient', 'fallback');
        }
        $model_goal = self::clean($model_goal);
        if (!self::is_fallback($model_goal)) {
            return self::result($model_goal, preg_match('/^Likely\b/iu', $model_goal) ? 'inferred' : 'stated', 'model');
        }
        $goal = null;
        $topics = array();
        $last_evidence = '';
        $revision_evidence = '';
        $revised = false;
        $external_context = false;
        foreach (is_array($messages) ? $messages : array() as $message) {
            $message = is_object($message) ? get_object_vars($message) : $message;
            if (!is_array($message) || !in_array(isset($message['role']) ? $message['role'] : '', array('user', 'visitor'), true)) {
                continue;
            }
            $type = isset($message['type']) ? $message['type'] : 'message';
            if (!in_array($type, array('question', 'learning_goal', 'message', 'text'), true)) {
                continue;
            }
            $text = self::clean(isset($message['content']) ? $message['content'] : '');
            if ($text === '') { continue; }
            // Do not detach a first-person quote/question from its reported or hypothetical subject.
            $external_message = self::external_context($text);
            $own_context = preg_match('/\b(?:for myself|personally|my own|as for me|in my case|about me)\b/iu', $text) === 1;
            if ($own_context && !$external_message) { $external_context = false; }
            if ($external_message) { $external_context = true; }
            // Clauses allow a later correction in the same message to replace an earlier goal.
            $clauses = preg_split('/[.!?\n]+|[,;]\s*(?=(?:i|my|not|nenu|naaku|mujhe|main)\b)|\b(?:but|instead|actually|rather)\b/iu', $text, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($clauses as $clause) {
                $clause = trim($clause);
                if ($clause === '') { continue; }
                $clause_topics = self::topics($clause);
                if ($clause_topics) { $last_evidence = $clause; }
                foreach ($clause_topics as $topic) { $topics[$topic] = $topic; }
                if ($own_context && preg_match('/\b(?:for myself|personally|my own|as for me|in my case|about me)\b/iu', $clause)) { $external_context = false; }
                if ($external_context) { continue; }
                $kind = self::intent($clause, $type);
                if ($kind !== null) {
                    $question = preg_match('/\b(?:can|could|will|would|how|does|do|is)\s+(?:i|this|the\s+course)\b|\b(?:help|teach|prepare)\s+me\b|\b(?:sakta|sakti|sakte|vastunda|vastundha|dorukutunda)\b|వస్తుందా|వస్తుంద|సాధ్యమా|सकता|सकती/iu', $clause);
                    if ($kind === 'business' && !preg_match('/\b(?:want|need|aim|plan|grow|increase|improve|promote|generate)\w*\b|పెంచ|बढ़ा/iu', $clause)) { $question = true; }
                    $goal = self::result(($question ? 'Likely interested in ' : '') . self::label($kind, (bool) $question), $question ? 'inferred' : 'stated', 'transcript', $clause);
                    $goal['_kind'] = $kind;
                    $revised = false;
                } elseif (self::rejects($clause, $goal !== null ? $goal['_kind'] : '')) {
                    $goal = null;
                    $revised = true;
                    $revision_evidence = $clause;
                }
            }
        }
        if ($goal !== null) {
            unset($goal['_kind']);
            return $goal;
        }
        if ($revised) { return self::result(self::REVISED_SUMMARY, 'insufficient', 'transcript', $revision_evidence); }
        if ($topics) {
            $topics = array_slice(array_values($topics), -3);
            return self::result('Exploring course details: ' . implode('; ', $topics) . '.', 'interest', 'transcript', $last_evidence);
        }
        return self::result(self::EMPTY_SUMMARY, 'insufficient', 'fallback');
    }

    private static function result($summary, $status, $source, $evidence = '') {
        return array('summary' => self::cut(self::clean($summary), 600), 'status' => $status, 'source' => $source, 'evidence' => self::cut(self::clean($evidence), 240), 'version' => self::VERSION);
    }

    private static function cut($text, $length) {
        if (function_exists('mb_substr')) { return mb_substr($text, 0, $length, 'UTF-8'); }
        return preg_match('/^.{0,' . (int) $length . '}/us', $text, $match) ? $match[0] : '';
    }

    private static function clean($value) {
        if (!is_string($value) && !is_numeric($value)) { return ''; }
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');
        if (!preg_match('//u', $value)) { return ''; }
        $value = preg_replace(array(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            '/(?<!\w)\+?\d[\d\s().\-]{7,}\d(?!\w)/u',
            '/\b(?:my name is|i am called|i\x{2019}m called|i\x27m called)\s+[^,.;!?]+/iu',
            '/\b(?:api[ _-]?key|password|secret|access[ _-]?token)\s*(?:is|:|=)\s*\S+/iu',
            '/\b(?:sk-(?:or-v1-|proj-)?|ghp_)[A-Za-z0-9_-]{12,}\b/u',
            '/\b(?:otp|verification code)\s*(?:is|:|=)?\s*\d{4,8}\b/iu',
        ), '[redacted]', $value);
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    private static function label($kind, $inferred) {
        $labels = array(
            'job' => array('Find a digital marketing job.', 'finding a digital marketing job.'),
            'career' => array('Advance or change their marketing career.', 'career growth or a move into digital marketing.'),
            'freelance' => array('Start or grow freelance marketing work.', 'starting or growing freelance marketing work.'),
            'business' => array('Use digital marketing to grow their business.', 'using digital marketing to grow their business.'),
            'skills' => array('Build practical digital marketing skills.', 'building practical digital marketing skills.'),
        );
        return $labels[$kind][$inferred ? 1 : 0];
    }

    private static function blocked($text) {
        // A quoted scenario, another person's ambition or a denied goal is not this visitor's goal.
        return preg_match('/\b(?:hypothetically|hypothetical|suppose|imagine|pretend|translate|for example|asking for|my\s+(?:friend|brother|sister|son|daughter|wife|husband|cousin)|someone|a person|students|people)\b|\bwhat\s+does\b.+\bmean\b|["“][^"”]*\b(?:i|my|me)\b[^"”]*["”]|\bif\s+(?:i|someone|a person)\b|\b(?:not|never|no longer|don\x27t|dont|do not|don\x{2019}t|isn\x27t|aren\x27t|cannot)\b|\b(?:nahi|nahin|vaddu|ledu|ledhu)\b|వద్దు|లేదు|नहीं|नही/iu', $text) === 1;
    }

    private static function external_context($text) {
        return preg_match('/\b(?:hypothetically|hypothetical|suppose|imagine|pretend|translate|for example|asking for|on behalf of)\b|\bif\s+(?:i|someone|a person)\b|\bmy\s+(?:friend|brother|sister|son|daughter|wife|husband|cousin)\b.*\b(?:asked|asks|said|says|wants|needs|want|need|looking|planning)\b|\b(?:he|she)\s+(?:asked|asks|said|says)\b|["“][^"”]*\b(?:i|my|me)\b[^"”]*["”]/iu', $text) === 1;
    }

    private static function intent($text, $type) {
        if (self::blocked($text)) { return null; }
        $self = preg_match('/\b(?:i|my|me|myself|nenu|naaku|naku|naa|mera|meri|mujhe|main|mai)\b|నేను|నాకు|నా |मुझे|मैं|मेरा|मेरी/iu', $text);
        $purpose = preg_match('/\b(?:want|need|hope|aim|plan|looking|seek|start|become|grow|improve|promote|build|switch|change|advance|learn|upskill|increase|help|get|find|land|earn|generate|run|own|have|chahiye|karna|chahta|chahti|kavali|kaavali|pench|nerchuk)\w*\b|కావాలి|నేర్చు|పెంచ|పొంద|చేయా|వస్తుందా|चाहिए|सीख|बढ़ा|करना|चाहता|चाहती/iu', $text);
        if ($type === 'learning_goal') { $self = true; $purpose = true; }
        if (!$self || !$purpose) { return null; }
        if (preg_match('/\b(?:want|need|like)\s+to\s+(?:know|understand|ask|check)\s+(?:about|what|whether|if)\b/iu', $text)) { return null; }
        if (preg_match('/\b(?:freelanc\w*|self[ -]employ\w*|independent\s+client\w*)\b|ఫ్రీలాన్|फ्रीलान|फ्रीलैंस/iu', $text)) { return 'freelance'; }
        if (preg_match('/\b(?:switch|change|advanc\w*|promot\w*|upskill\w*|career\s+growth)\b.*\b(?:career|role|job|marketing|skills)\b|\b(?:career|job)\b.*\b(?:switch|change|growth|advanc\w*)\b/iu', $text)) { return 'career'; }
        $job = preg_match('/\b(?:job|jobs|placement|hired|employ\w*|work\s+in\s+marketing)\b|ఉద్యోగ|జాబ్|నౌకరీ|नौकरी|जॉब/iu', $text);
        $job_purpose = preg_match('/\b(?:want|need|hope|aim|plan|looking|seek|become|get|find|land|chahiye|chahta|chahti|kavali|kaavali|vastunda|vastundha|dorukutunda)\w*\b|కావాలి|పొంద|వస్తుందా|चाहिए|चाहता|चाहती/iu', $text);
        if ($job && $job_purpose && !preg_match('/\b(?:want|need|like)\s+to\s+(?:know|understand|ask|check)\b|\b(?:already|currently)\s+(?:have|in)\b/iu', $text)) { return 'job'; }
        if (preg_match('/\b(?:my|our)\s+(?:(?:own|small|local|online|family)\s+)?(?:business|shop|store|bakery|salon|restaurant|brand|startup|company|agency)\b|\b(?:i|we)\s+(?:run|own|have)\s+(?:an?\s+)?(?:business|shop|store|bakery|salon|restaurant|brand|startup|company|agency)\b|\b(?:my|our)\s+(?:sales|leads|customers|enquiries|inquiries)\b|\b(?:naa|na|mera|meri)\s+(?:business|dukaan|shop|vyaparam)\b|నా వ్యాపార|నా షాప్|మా వ్యాపార|मेरा व्यवसाय|मेरी दुकान|मेरे बिजनेस/iu', $text)) { return 'business'; }
        if (preg_match('/\b(?:learn|build|improve|develop)\b.*\b(?:marketing|skills|seo|ads|analytics)\b|మార్కెటింగ్ నేర్చు|मार्केटिंग सीख/iu', $text)) { return 'skills'; }
        return null;
    }

    private static function rejects($text, $kind) {
        $explicit_drop = preg_match('/^(?:please\s+)?(?:forget|drop|cancel)\b.*\b(?:goal|plan|job|career|freelanc\w*|business)\b/iu', $text);
        if (!$explicit_drop && !preg_match('/\b(?:i|my|me)\b|నాకు|నేను|मुझे|मैं/iu', $text)) { return false; }
        if (preg_match('/\b(?:changed my mind|not sure what|no longer sure|still deciding|do not know what|don\x27t know what)\b/iu', $text)) { return true; }
        if (!$explicit_drop && !preg_match('/\b(?:not|never|no longer|don\x27t|dont|do not|don\x{2019}t)\s+(?:(?:really|actually|any\s+longer)\s+)?(?:want|need|seek|plan|looking|interested|pursu\w*|my\s+goal|for\s+me)|వద్దు|नहीं चाहिए|नही चाहिए/iu', $text)) { return false; }
        $kinds = array('job' => 'job|jobs|employment|placement|work', 'career' => 'career|switch|job', 'freelance' => 'freelanc\w*', 'business' => 'business|shop|store|sales', 'skills' => 'learn|skills');
        return $kind !== '' && isset($kinds[$kind]) && preg_match('/\b(?:' . $kinds[$kind] . ')\b/iu', $text);
    }

    private static function topics($text) {
        $patterns = array(
            'fees and payment options' => '/\b(?:fee|fees|price|pricing|cost|payment|instalments?|installments?|emi|discount|refund|gst)\b|ఫీజు|ఫీజు|ధర|फीस|कीमत|फी /iu',
            'jobs and placement support' => '/\b(?:jobs?|salary|placement|hiring|resume|interview|career)\b|ఉద్యోగ|జాబ్|नौकरी|जॉब/iu',
            'curriculum and marketing skills' => '/\b(?:curriculum|syllabus|modules?|topics?|tools?|seo|ads|advertising|social media|instagram|facebook|analytics|automation|marketing)\b|మార్కెటింగ్|సిలబస్|मार्केटिंग|सिलेबस/iu',
            'class schedule and duration' => '/\b(?:duration|months?|weeks?|timings?|schedule|weekend|batch|classes|recordings?|language)\b|సమయం|తరగత|समय|क्लास/iu',
            'certification' => '/\b(?:certificate|certification|certified)\b|సర్టిఫికెట్|प्रमाणपत्र/iu',
            'practical projects' => '/\b(?:projects?|portfolio|capstone|assignments?)\b|ప్రాజెక్ట్|प्रोजेक्ट/iu',
            'course eligibility' => '/\b(?:eligible|eligibility|beginner|fresher|qualification|experience|required|coding)\b/iu',
            'brochure and enrolment' => '/\b(?:brochure|enrol|enroll|enrolment|enrollment|register|registration|admission|demo|live session)\b/iu',
        );
        $topics = array();
        foreach ($patterns as $label => $pattern) { if (preg_match($pattern, $text)) { $topics[] = $label; } }
        return $topics;
    }
}
