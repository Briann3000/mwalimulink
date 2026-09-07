<?php
// views/api_cv_polish.php - Educator CV Polishing Engine
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

require_once __DIR__ . '/../config.php';
init_session();

$authUser = auth_user();
if (!$authUser || ($authUser['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Educator login required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

// Read raw JSON body or form POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = trim($input['action'] ?? '');
$rawText = trim($input['raw_text'] ?? '');
$context = $input['context'] ?? [];

$teacherName = trim($context['name'] ?? $authUser['name'] ?? 'Educator');
$subjects = trim($context['subjects'] ?? 'General Subjects');
$qualification = trim($context['qualification'] ?? 'Education Degree/Diploma');
$yearsExp = intval($context['years_of_experience'] ?? 0);
$gradeLevels = trim($context['grade_levels'] ?? 'Primary & Secondary');
$county = trim($context['county'] ?? 'Kenya');
$template = trim($context['template'] ?? 'classic_kenyan');
$schoolName = trim($context['school_name'] ?? '');
$roleTitle = trim($context['role_title'] ?? '');

$groqApiKey = env('GROQ_API_KEY', '');
$groqModel = env('GROQ_MODEL', 'llama-3.3-70b-versatile');
$geminiApiKey = env('GEMINI_API_KEY', '');

/**
 * Execute Cloud Polishing via Groq API (Blazing-fast Llama 3.3 70B)
 */
function call_groq_polisher($apiKey, $model, $action, $rawText, $meta) {
    $endpoint = "https://api.groq.com/openai/v1/chat/completions";

    $promptDirective = match ($action) {
        'polish_summary' => "Rewrite the provided rough notes into exactly 2 to 3 concise, high-impact sentences for an educator's professional summary. Sentence 1: state subject specialization, TSC alignment, and career level. Sentence 2: core instructional expertise and tangible student improvement. Sentence 3: forward-looking dedication to academic excellence. Do not use bullet points or filler phrases.",
        'generate_summary' => "Draft a 2 to 3 sentence professional teaching summary from scratch based solely on the provided credentials. Emphasize student-centered learning, curriculum delivery, and positive learning outcomes. Do not invent unprovided degrees or unverified schools.",
        'optimize_bullets' => "Convert the provided job duties into 3 to 5 strong, quantifiable achievement bullet points for a teaching CV. Each bullet must begin with a powerful past-tense action verb (e.g. Orchestrated, Accelerated, Formulated, Mentored, Instituted). Emphasize exam performance improvements, learner engagement, classroom management, and CBC alignment where relevant. Return only the bullet points, each on a new line starting with '• '.",
        'suggest_cocurricular' => "Provide 3 professional, impact-focused bullet points for co-curricular roles in Kenyan schools (such as Club Patron, Sports Coach, Drama/Music Trainer, or Scouts Leader). Return each bullet starting with '• '.",
        default => "Refine and elevate this educator CV content with professional British English, active verbs, and academic clarity."
    };

    $systemInstruction = <<<PROMPT
You are an expert executive curriculum vitae consultant specialising exclusively in Kenyan and international educator credentials.
Follow these mandatory standards:
1. Use formal British English spelling and syntax (e.g., specialised, programme, curriculum).
2. Maintain an authoritative, professional, and refined tone. Strictly avoid clichés, empty adjectives, and buzzwords.
3. Every bullet point must articulate a clear action and a meaningful educational outcome.
4. Keep the output clean, concise, and immediately ready to paste onto a CV. Do not include introductory remarks, commentary, markdown headings, or quotes.

Educator Profile Context:
- Full Name: {$meta['name']}
- Subjects: {$meta['subjects']}
- Highest Qualification: {$meta['qualification']}
- Experience: {$meta['years_of_experience']} years
- Target Levels: {$meta['grade_levels']}
- Location / County: {$meta['county']}
- Selected Style: {$meta['template']}
- Institution: {$meta['school_name']}
- Role Title: {$meta['role_title']}
PROMPT;

    $userPrompt = "Task: {$promptDirective}\n\nContent / Notes:\n\"{$rawText}\"";

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemInstruction],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 600
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        $candidateText = $result['choices'][0]['message']['content'] ?? null;
        if (!empty($candidateText)) {
            return trim($candidateText);
        }
    }

    return null;
}

/**
 * Execute Cloud Polishing via Gemini API (Fallback option)
 */
function call_gemini_polisher($apiKey, $action, $rawText, $meta) {
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

    $promptDirective = match ($action) {
        'polish_summary' => "Rewrite the provided rough notes into exactly 2 to 3 concise, high-impact sentences for an educator's professional summary. Sentence 1: state subject specialization, TSC alignment, and career level. Sentence 2: core instructional expertise and tangible student improvement. Sentence 3: forward-looking dedication to academic excellence. Do not use bullet points or filler phrases.",
        'generate_summary' => "Draft a 2 to 3 sentence professional teaching summary from scratch based solely on the provided credentials. Emphasize student-centered learning, curriculum delivery, and positive learning outcomes. Do not invent unprovided degrees or unverified schools.",
        'optimize_bullets' => "Convert the provided job duties into 3 to 5 strong, quantifiable achievement bullet points for a teaching CV. Each bullet must begin with a powerful past-tense action verb (e.g. Orchestrated, Accelerated, Formulated, Mentored, Instituted). Emphasize exam performance improvements, learner engagement, classroom management, and CBC alignment where relevant. Return only the bullet points, each on a new line starting with '• '.",
        'suggest_cocurricular' => "Provide 3 professional, impact-focused bullet points for co-curricular roles in Kenyan schools (such as Club Patron, Sports Coach, Drama/Music Trainer, or Scouts Leader). Return each bullet starting with '• '.",
        default => "Refine and elevate this educator CV content with professional British English, active verbs, and academic clarity."
    };

    $systemInstruction = <<<PROMPT
You are an expert executive curriculum vitae consultant specialising exclusively in Kenyan and international educator credentials.
Follow these mandatory standards:
1. Use formal British English spelling and syntax (e.g., specialised, programme, curriculum).
2. Maintain an authoritative, professional, and refined tone. Strictly avoid clichés, empty adjectives, and buzzwords.
3. Every bullet point must articulate a clear action and a meaningful educational outcome.
4. Keep the output clean, concise, and immediately ready to paste onto a CV. Do not include introductory remarks, commentary, markdown headings, or quotes.

Educator Profile Context:
- Full Name: {$meta['name']}
- Subjects: {$meta['subjects']}
- Highest Qualification: {$meta['qualification']}
- Experience: {$meta['years_of_experience']} years
- Target Levels: {$meta['grade_levels']}
- Location / County: {$meta['county']}
- Selected Style: {$meta['template']}
- Institution: {$meta['school_name']}
- Role Title: {$meta['role_title']}

Specific Task:
{$promptDirective}

Provided Text / Notes:
"{$rawText}"
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 600,
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        $candidateText = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!empty($candidateText)) {
            return trim($candidateText);
        }
    }

    return null;
}

/**
 * High-quality Native Heuristic Polisher (Fallback when no cloud API key is set)
 */
function call_native_polisher($action, $rawText, $meta) {
    $name = $meta['name'];
    $subjects = !empty($meta['subjects']) ? $meta['subjects'] : 'Secondary Education';
    $years = intval($meta['years_of_experience']);
    $qualification = !empty($meta['qualification']) ? $meta['qualification'] : 'Bachelor of Education';

    $expLabel = $years > 0 ? "with over {$years} years of progressive classroom experience" : "with comprehensive pedagogical training";

    if ($action === 'polish_summary' || $action === 'generate_summary') {
        if (!empty($rawText) && strlen($rawText) > 20) {
            $cleaned = rtrim(trim($rawText), '.');
            return "Dedicated and results-oriented {$subjects} educator {$expLabel}. {$cleaned}. Committed to fostering learner-centred engagement, rigorous assessment standards, and holistic student development.";
        } else {
            return "Accomplished and TSC-compliant {$subjects} educator holding a {$qualification} {$expLabel}. Proven track record in curriculum delivery, learner differentiation, and sustained improvements in academic performance. Committed to student mentorship and active participation in school co-curricular programmes.";
        }
    }

    if ($action === 'optimize_bullets') {
        if (!empty($rawText)) {
            $lines = preg_split('/[\r\n]+/', $rawText);
            $polishedLines = [];
            $actionVerbs = ['Orchestrated', 'Spearheaded', 'Implemented', 'Facilitated', 'Formulated', 'Standardised', 'Supervised'];
            $i = 0;

            foreach ($lines as $line) {
                $trimmed = trim(preg_replace('/^[-*•\d.]+\s*/', '', $line));
                if (empty($trimmed)) continue;
                $verb = $actionVerbs[$i % count($actionVerbs)];
                $i++;
                $rest = lcfirst($trimmed);
                $polishedLines[] = "• {$verb} {$rest}, ensuring strict alignment with curriculum guidelines and measurable learner progress.";
            }

            if (!empty($polishedLines)) {
                return implode("\n", $polishedLines);
            }
        }

        return "• Planned and delivered comprehensive {$subjects} lessons adhering to syllabus requirements and modern pedagogical methods.\n• Monitored and documented student progress through continuous assessment tests, raising subject mean scores consistently.\n• Maintained active classroom discipline and collaborative engagement with parents regarding academic intervention strategies.\n• Coordinated departmental curriculum activities and participated actively in school co-curricular programmes.";
    }

    if ($action === 'suggest_cocurricular') {
        return "• Served as Patron for the school science and innovation club, mentoring students for annual sub-county congress presentations.\n• Coordinated athletics and football teams, cultivating discipline, teamwork, and sportsmanship among participating learners.\n• Facilitated student leadership and debate societies, helping learners refine critical thinking and public presentation skills.";
    }

    return $rawText;
}

$polishedOutput = null;
$meta = [
    'name' => $teacherName,
    'subjects' => $subjects,
    'qualification' => $qualification,
    'years_of_experience' => $yearsExp,
    'grade_levels' => $gradeLevels,
    'county' => $county,
    'template' => $template,
    'school_name' => $schoolName,
    'role_title' => $roleTitle
];

// 1. Primary: Groq API (Fast Llama 3.3 70B)
if (!empty($groqApiKey)) {
    $polishedOutput = call_groq_polisher($groqApiKey, $groqModel, $action, $rawText, $meta);
}

// 2. Secondary: Gemini API (if Groq is not set or failed)
if (empty($polishedOutput) && !empty($geminiApiKey)) {
    $polishedOutput = call_gemini_polisher($geminiApiKey, $action, $rawText, $meta);
}

// 3. Fallback: Native Educator Heuristics
if (empty($polishedOutput)) {
    $polishedOutput = call_native_polisher($action, $rawText, $meta);
}

echo json_encode([
    'success' => true,
    'action' => $action,
    'polished_text' => $polishedOutput
]);
