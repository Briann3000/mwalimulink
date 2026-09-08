<?php
// services/TscVerificationService.php - Automated TSC & Credential Verification Engine
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

class TscVerificationService
{
    /**
     * Official TSC Portal verification endpoint URL
     */
    private static $tscLookupUrl = 'https://tsconline.tsc.go.ke/register/registration-status';

    /**
     * Verify a teacher's TSC registration details.
     *
     * @param int $teacherId
     * @param string $tscNumber
     * @param string $idNumber
     * @param string $candidateName
     * @return array [
     *   'success' => bool,
     *   'status' => 'verified'|'pending_manual'|'failed',
     *   'match_score' => int (0-100),
     *   'portal_name' => string|null,
     *   'message' => string
     * ]
     */
    public static function verifyTeacher($teacherId, $tscNumber, $idNumber, $candidateName)
    {
        $tscNumber = trim((string)$tscNumber);
        $idNumber = trim((string)$idNumber);
        $candidateName = trim((string)$candidateName);

        if (empty($tscNumber) || strlen($tscNumber) < 4) {
            return [
                'success' => false,
                'status' => 'failed',
                'match_score' => 0,
                'portal_name' => null,
                'message' => 'A valid TSC registration number is required for verification.'
            ];
        }

        $teacher = R::load('teacher', $teacherId);
        if (!$teacher || !$teacher->id) {
            return [
                'success' => false,
                'status' => 'failed',
                'match_score' => 0,
                'portal_name' => null,
                'message' => 'Teacher record not found.'
            ];
        }

        // 1. Query the TSC online portal (or reliable fallback engine)
        $portalResult = self::queryTscPortal($tscNumber, $idNumber, $candidateName);

        $portalName = $portalResult['registered_name'] ?? null;
        $isRegistered = !empty($portalResult['is_active']);
        $matchScore = 0;

        if (!empty($portalName)) {
            $matchScore = self::calculateNameSimilarity($candidateName, $portalName);
        }

        $status = 'failed';
        $message = '';

        if ($portalResult['unreachable'] ?? false) {
            $status = 'pending_manual';
            $message = 'Government portal temporarily unresponsive. Queued for background validation or document audit.';
            if (function_exists('send_admin_verification_alert')) {
                send_admin_verification_alert($teacher, "Government portal query timed out for TSC No: {$tscNumber}. Queued for administrative review.");
            }
        } elseif ($isRegistered && $matchScore >= 80) {
            $status = 'verified';
            $message = "Verified successfully. Registered official name matched with {$matchScore}% confidence.";
        } elseif ($isRegistered && $matchScore >= 55) {
            $status = 'pending_manual';
            $message = "Registration record found ({$portalName}), but name difference detected ({$matchScore}% match). Queued for rapid confirmation.";
            if (function_exists('send_admin_verification_alert')) {
                send_admin_verification_alert($teacher, "Name discrepancy detected ({$matchScore}% match) for TSC: {$tscNumber}. Portal: '{$portalName}' vs Candidate: '{$candidateName}'.");
            }
        } else {
            $status = 'failed';
            $message = 'No active TSC registration matching the provided credentials was identified.';
        }

        // 2. Persist audit log in RedBean
        $log = R::dispense('tscverificationlog');
        $log->teacher_id = $teacher->id;
        $log->tsc_number = $tscNumber;
        $log->id_number = $idNumber;
        $log->candidate_name = $candidateName;
        $log->portal_name = $portalName;
        $log->status = $status;
        $log->match_score = $matchScore;
        $log->details = $message;
        $log->created_at = date('Y-m-d H:i:s');
        R::store($log);

        // 3. Update Teacher Entity
        $prevStatus = $teacher->verification_status;
        if ($status === 'verified') {
            $teacher->verification_status = 'verified';
            $teacher->verified_at = date('Y-m-d H:i:s');
            $teacher->verified_source = 'automated_tsc_portal';
            $teacher->tsc_verified_name = $portalName;
            R::store($teacher);

            if ($prevStatus !== 'verified' && function_exists('send_verification_status_email')) {
                send_verification_status_email($teacher, 'verified');
            }
        } elseif ($status === 'pending_manual') {
            if ($teacher->verification_status !== 'verified') {
                $teacher->verification_status = 'pending';
                R::store($teacher);
            }
        } elseif ($status === 'failed') {
            if ($teacher->verification_status !== 'verified') {
                $teacher->verification_status = 'none';
                $teacher->tsc_verified_name = null;
                R::store($teacher);
            }
        }

        return [
            'success' => ($status === 'verified'),
            'status' => $status,
            'match_score' => $matchScore,
            'portal_name' => $portalName,
            'message' => $message
        ];
    }

    /**
     * Compute token-sort and phonetic similarity between Kenyan name variations.
     *
     * @param string $name1
     * @param string $name2
     * @return int Match score between 0 and 100
     */
    public static function calculateNameSimilarity($name1, $name2)
    {
        $clean1 = self::normalizeName($name1);
        $clean2 = self::normalizeName($name2);

        if (empty($clean1) || empty($clean2)) {
            return 0;
        }

        if ($clean1 === $clean2) {
            return 100;
        }

        similar_text($clean1, $clean2, $directScore);

        $tokens1 = array_values(array_filter(explode(' ', $clean1)));
        $tokens2 = array_values(array_filter(explode(' ', $clean2)));

        $count1 = count($tokens1);
        $count2 = count($tokens2);
        if ($count1 === 0 || $count2 === 0) {
            return 0;
        }

        $matchedTokens = 0;
        foreach ($tokens1 as $t1) {
            foreach ($tokens2 as $t2) {
                if ($t1 === $t2 || (strlen($t1) > 2 && strlen($t2) > 2 && (str_starts_with($t1, $t2) || str_starts_with($t2, $t1)))) {
                    $matchedTokens++;
                    break;
                }
            }
        }

        $maxTokens = max($count1, $count2);
        $tokenScore = ($maxTokens > 0) ? round(($matchedTokens / $maxTokens) * 100) : 0;

        // Kenyan name subset matching: When all entered names (>=2) match parts of the 3+ part official name
        $minTokens = min($count1, $count2);
        $subsetCoverage = ($minTokens > 0) ? ($matchedTokens / $minTokens) : 0;

        if ($matchedTokens >= 2 && $subsetCoverage >= 1.0) {
            $weightedScore = round(($subsetCoverage * 70) + (($matchedTokens / $maxTokens) * 30));
            $tokenScore = max($tokenScore, $weightedScore);
        }

        return intval(round(max($directScore, $tokenScore)));
    }

    /**
     * Normalize names for accurate Kenyan educator matching.
     */
    private static function normalizeName($name)
    {
        $name = strtolower($name);
        $name = preg_replace('/\b(tr|mr|mrs|ms|miss|dr|prof|sir|rev|pastor)\b\.?/i', '', $name);
        $name = preg_replace('/[^a-z\s]/i', '', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Query the official portal or fallback simulated validation engine.
     */
    private static function queryTscPortal($tscNumber, $idNumber, $candidateName)
    {
        $liveResult = self::executeLivePortalQuery($tscNumber, $idNumber);
        if ($liveResult['attempted'] && empty($liveResult['unreachable'])) {
            return $liveResult;
        }

        return self::resolveFallbackValidation($tscNumber, $idNumber, $candidateName);
    }

    /**
     * Perform live cURL request to official TSC registration status portal
     */
    private static function executeLivePortalQuery($tscNumber, $idNumber)
    {
        $queryNum = !empty($idNumber) ? trim($idNumber) : trim($tscNumber);
        if (empty($queryNum)) {
            return [
                'attempted' => false,
                'success' => false,
                'is_active' => false
            ];
        }

        $tempCookie = sys_get_temp_dir() . '/tsc_cookie_' . md5(uniqid()) . '.txt';

        // 1. GET page to obtain CSRF token and session cookies
        $ch = curl_init(self::$tscLookupUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $tempCookie);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

        $getHtml = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($getHtml)) {
            if (file_exists($tempCookie)) @unlink($tempCookie);
            return [
                'attempted' => true,
                'success' => false,
                'unreachable' => true,
                'is_active' => false
            ];
        }

        $csrf = '';
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/i', $getHtml, $m) || preg_match('/value="([^"]+)"\s+name="_csrf"/i', $getHtml, $m)) {
            $csrf = $m[1];
        }

        // 2. POST lookup with id_no and _csrf
        $ch = curl_init(self::$tscLookupUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            '_csrf' => $csrf,
            'id_no' => $queryNum
        ]));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $tempCookie);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $tempCookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

        $postHtml = curl_exec($ch);
        $postHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (file_exists($tempCookie)) @unlink($tempCookie);

        // If HTTP code is 200 (page returned) or 302 (portal redirects on not found)
        if (in_array($postHttpCode, [200, 302])) {
            // Case 1: Valid registration found in #complete-registration container
            if (!empty($postHtml) && (str_contains($postHtml, 'id="complete-registration"') || str_contains($postHtml, 'TEACHERS SERVICE COMMISSION OF KENYA ONLINE SERVICES'))) {
                $registeredName = '';
                $registeredTscNo = '';

                // Extract Name from <address><strong>NAME</strong></address>
                if (preg_match('/<address[^>]*>\s*<strong>(.*?)<\/strong>/is', $postHtml, $nameMatch)) {
                    $registeredName = trim(strip_tags($nameMatch[1]));
                }

                // Extract TSC number from "Registration Status : Registered TSC NO: 922722"
                if (preg_match('/TSC\s*(?:NO|NUMBER)?\s*[:\s]+([0-9]+)/i', $postHtml, $tscMatch)) {
                    $registeredTscNo = trim($tscMatch[1]);
                }

                $isActive = str_contains($postHtml, 'Registration Status : Registered');

                if (!empty($registeredName)) {
                    return [
                        'attempted' => true,
                        'success' => true,
                        'registered_name' => $registeredName,
                        'registered_tsc_no' => $registeredTscNo,
                        'is_active' => $isActive
                    ];
                }
            }

            // Case 2: Standard table structure (if portal returns table rows)
            if (!empty($postHtml) && preg_match('/<tbody[^>]*>(.*?)<\/tbody>/is', $postHtml, $tbMatches)) {
                $tbodyContent = trim(strip_tags($tbMatches[1]));
                if (!empty($tbodyContent)) {
                    if (preg_match('/<tr[^>]*>.*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>(.*?)<\/td>/is', $tbMatches[1], $rowCols)) {
                        $col1 = trim(strip_tags($rowCols[1]));
                        $col2 = trim(strip_tags($rowCols[2]));
                        return [
                            'attempted' => true,
                            'success' => true,
                            'registered_name' => $col2 ?: $col1,
                            'is_active' => true
                        ];
                    }
                }
            }

            // Case 3: No registration record found for this ID (HTTP 302 or empty results page)
            return [
                'attempted' => true,
                'success' => false,
                'unreachable' => false,
                'is_active' => false,
                'registered_name' => null
            ];
        }

        return [
            'attempted' => true,
            'success' => false,
            'unreachable' => true,
            'is_active' => false
        ];
    }

    /**
     * Resolves fallback verification when live portal is unreachable.
     * Note: Never auto-approves unverified or arbitrary numbers.
     */
    private static function resolveFallbackValidation($tscNumber, $idNumber, $candidateName)
    {
        return [
            'attempted' => false,
            'success' => false,
            'is_active' => false,
            'unreachable' => true
        ];
    }

    /**
     * Create or retrieve a secure token for institutional referee endorsement.
     *
     * @param int $teacherId
     * @param string $teacherName
     * @param string $refereeName
     * @param string $refereeEmail
     * @param string $institution
     * @param string $roleTitle
     * @return string Endorsement URL
     */
    public static function generateRefereeToken($teacherId, $teacherName, $refereeName, $refereeEmail, $institution, $roleTitle)
    {
        $existing = R::findOne('refereeendorsement', 'teacher_id = ? AND referee_name = ? AND institution = ?', [
            $teacherId,
            $refereeName,
            $institution
        ]);

        if ($existing && !empty($existing->token)) {
            return '/verify-referee?token=' . urlencode($existing->token);
        }

        $token = bin2hex(random_bytes(16));
        $endorsement = R::dispense('refereeendorsement');
        $endorsement->teacher_id = $teacherId;
        $endorsement->teacher_name = $teacherName;
        $endorsement->referee_name = $refereeName;
        $endorsement->referee_email = $refereeEmail;
        $endorsement->institution = $institution;
        $endorsement->role_title = $roleTitle;
        $endorsement->token = $token;
        $endorsement->status = 'pending';
        $endorsement->created_at = date('Y-m-d H:i:s');
        R::store($endorsement);

        return '/verify-referee?token=' . urlencode($token);
    }

    /**
     * Retrieve recent automated TSC audit logs for admin overview.
     */
    public static function getVerificationAuditLogs($limit = 50)
    {
        return R::find('tscverificationlog', 'ORDER BY id DESC LIMIT ?', [$limit]);
    }
}

