<?php
// services/JobAlertService.php - Multi-channel Automated Educator Job Alert Engine
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';
require_once __DIR__ . '/JobIngestionService.php';

class JobAlertService
{
    /**
     * Dispatch matching job alerts to all qualified registered teachers for a newly published vacancy.
     *
     * @param int|object $job Bean or Job ID
     * @return array Summary of notifications sent
     */
    public static function notifyMatchingTeachers($job): array
    {
        JobIngestionService::ensureDb();

        if (is_numeric($job)) {
            $job = R::load('job', (int) $job);
        }

        if (!$job || !$job->id || $job->aggregation_status !== 'published') {
            return ['status' => 'skipped', 'reason' => 'Job not published or invalid'];
        }

        $jobId = (int) $job->id;
        $jobTitle = $job->title;
        $jobDesc = $job->description ?? '';
        $jobReq = $job->requirements ?? '';
        $jobCurriculum = $job->curriculum ?? '';
        $jobLocation = $job->location_text ?? '';
        $jobCategory = $job->subject_category ?? '';
        $companyName = !empty($job->company_name) ? $job->company_name : ($job->source_name ?: 'Educational Institution');

        // Extract job tokens
        $searchableJobText = strtolower("{$jobTitle} {$jobDesc} {$jobReq} {$jobCurriculum} {$jobCategory}");

        // Find active teachers who have not yet received an alert for this specific job
        $teachers = R::find('teacher', "status = 'active' OR status IS NULL OR status = ''");

        $alertsDispatched = 0;
        $emailsSent = 0;
        $skippedCount = 0;

        foreach ($teachers as $teacher) {
            // Check if teacher has disabled job alerts
            if (isset($teacher->job_alerts_enabled) && $teacher->job_alerts_enabled === 0) {
                continue;
            }

            // Check deduplication in jobalert table
            $alreadyAlerted = R::findOne('jobalert', 'teacher_id = ? AND job_id = ?', [$teacher->id, $jobId]);
            if ($alreadyAlerted) {
                $skippedCount++;
                continue;
            }

            // Perform matching evaluation
            $matchScore = self::evaluateMatch($teacher, $searchableJobText, $jobLocation, $jobCurriculum);

            if ($matchScore['matched']) {
                // 1. Record In-App Alert Bean
                $alertBean = R::dispense('jobalert');
                $alertBean->teacher_id = $teacher->id;
                $alertBean->job_id = $jobId;
                $alertBean->match_reason = $matchScore['reason'];
                $alertBean->is_read = 0;
                $alertBean->created_at = date('Y-m-d H:i:s');
                R::store($alertBean);
                $alertsDispatched++;

                // 2. Dispatch Branded Email Alert (Skip if CLI test mode)
                if (!empty($teacher->email) && !defined('MWALIMU_TEST_MODE')) {
                    $emailSuccess = self::sendJobAlertEmail($teacher, $job, $companyName, $matchScore['reason']);
                    if ($emailSuccess) {
                        $emailsSent++;
                    }
                }
            }
        }

        return [
            'status' => 'completed',
            'job_id' => $jobId,
            'job_title' => $jobTitle,
            'alerts_dispatched' => $alertsDispatched,
            'emails_sent' => $emailsSent,
            'skipped_duplicates' => $skippedCount
        ];
    }

    /**
     * Evaluate whether a teacher matches a specific job based on subjects, county and curriculum.
     */
    public static function evaluateMatch($teacher, string $jobText, string $jobLocation, string $jobCurriculum): array
    {
        $subjects = strtolower($teacher->teaching_subjects ?? '');
        $county = strtolower($teacher->county ?? '');
        $gradeLevels = strtolower($teacher->grade_levels ?? '');

        // Tokenize teacher subjects (split by commas, slashes, ampersands, 'and')
        $tokens = preg_split('/[\/,\+&]|\band\b/i', $subjects);
        $subjectTokens = [];
        foreach ($tokens as $t) {
            $t = trim($t);
            if (strlen($t) >= 3) {
                $subjectTokens[] = $t;
            }
        }

        $matchedSubject = null;
        foreach ($subjectTokens as $token) {
            if (strpos($jobText, $token) !== false) {
                $matchedSubject = $token;
                break;
            }
        }

        // Check for ECD / Early Childhood Match
        if (!$matchedSubject && (strpos($gradeLevels, 'ecde') !== false || strpos($gradeLevels, 'kindergarten') !== false || strpos($gradeLevels, 'pre-primary') !== false)) {
            if (strpos($jobText, 'ecd') !== false || strpos($jobText, 'early childhood') !== false || strpos($jobText, 'kindergarten') !== false || strpos($jobText, 'pp1') !== false || strpos($jobText, 'pp2') !== false) {
                $matchedSubject = 'Early Childhood / ECD';
            }
        }

        // If no subject match, check if county and teacher category match strongly
        $countyMatched = (!empty($county) && (strpos($jobText, $county) !== false || strpos(strtolower($jobLocation), $county) !== false));

        if ($matchedSubject) {
            $reason = "Matches your specialization: " . ucwords($matchedSubject) . ($countyMatched ? " in {$teacher->county}" : "");
            return ['matched' => true, 'reason' => $reason];
        } elseif ($countyMatched && empty($subjects)) {
            // General match for unspecialized teachers in the same county
            return ['matched' => true, 'reason' => "New vacancy in your location: {$teacher->county}"];
        }

        return ['matched' => false, 'reason' => ''];
    }

    /**
     * Send clean, branded notification email to teacher with no emojis in subject.
     */
    public static function sendJobAlertEmail($teacher, $job, string $companyName, string $matchReason): bool
    {
        $teacherName = $teacher->name ?: 'Educator';
        $jobTitle = $job->title;
        $appUrl = env('APP_URL', 'https://mwalimu.info') . '/teacher/apply?job_id=' . $job->id;
        $location = !empty($job->location_text) ? $job->location_text : 'Kenya';
        $deadlineText = !empty($job->deadline) ? date('M d, Y', strtotime($job->deadline)) : 'Open until filled';
        $curriculum = !empty($job->curriculum) ? $job->curriculum : 'Standard';

        // Subject line with no emoji
        $subject = "New Teaching Opportunity: {$jobTitle} at {$companyName}";

        $htmlBody = "
        <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);\">
            <div style=\"background: #0f766e; padding: 24px 30px; text-align: center;\">
                <h1 style=\"color: #ffffff; margin: 0; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.02em;\">MwalimuLink</h1>
                <p style=\"color: #ccfbf1; margin: 6px 0 0; font-size: 0.85rem;\">Verified Educator Placement & Vacancy Alerts</p>
            </div>
            
            <div style=\"padding: 30px;\">
                <div style=\"display: inline-block; background: #ecfdf5; color: #047857; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 16px; border: 1px solid #a7f3d0;\">
                    NEW VACANCY MATCH
                </div>
                
                <h2 style=\"color: #0f172a; font-size: 1.25rem; margin: 0 0 10px;\">Hello " . htmlspecialchars($teacherName) . ",</h2>
                
                <p style=\"color: #334155; font-size: 0.92rem; line-height: 1.6; margin: 0 0 16px;\">
                    A new teaching vacancy has been published on MwalimuLink that matches your professional qualifications:
                </p>

                <!-- Vacancy Summary Box -->
                <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin: 20px 0;\">
                    <h3 style=\"margin: 0 0 6px; font-size: 1.15rem; color: #0f172a; font-weight: 800;\">" . htmlspecialchars($jobTitle) . "</h3>
                    <div style=\"color: #0f766e; font-weight: 700; font-size: 0.9rem; margin-bottom: 12px;\">" . htmlspecialchars($companyName) . "</div>
                    
                    <div style=\"display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.83rem; color: #475569; border-top: 1px solid #e2e8f0; padding-top: 12px;\">
                        <div><strong>Location:</strong> " . htmlspecialchars($location) . "</div>
                        <div><strong>Curriculum:</strong> " . htmlspecialchars($curriculum) . "</div>
                        <div><strong>Deadline:</strong> " . htmlspecialchars($deadlineText) . "</div>
                        <div><strong>Match Reason:</strong> " . htmlspecialchars($matchReason) . "</div>
                    </div>
                </div>

                <div style=\"text-align: center; margin: 28px 0 24px;\">
                    <a href=\"{$appUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.95rem; padding: 12px 30px; border-radius: 8px; text-decoration: none; box-shadow: 0 3px 6px rgba(15,118,110,0.25);\">
                        View & Easy Apply via MwalimuLink →
                    </a>
                </div>
                
                <p style=\"font-size: 0.8rem; color: #64748b; line-height: 1.5; margin: 20px 0 0; text-align: center;\">
                    You received this automated notification because your MwalimuLink job alert preferences are active. You can manage notification settings in your profile dashboard.
                </p>
            </div>

            <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
                <p style=\"margin: 0;\">MwalimuLink &bull; Empowering Kenya's Teaching Workforce &bull; Nairobi, Kenya</p>
            </div>
        </div>";

        return send_system_email($teacher->email, $teacher->name, $subject, $htmlBody);
    }
}
