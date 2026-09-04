<?php
// views/register_choice.php - Interactive Registration Role Selection Hub

// Redirect if already logged in
if (is_logged_in()) {
    if (has_role('school')) {
        header('Location: /school/dashboard');
        exit();
    } elseif (has_role('teacher')) {
        header('Location: /teacher/dashboard');
        exit();
    } elseif (has_role('admin')) {
        header('Location: /admin/dashboard');
        exit();
    }
}
?>

<div class="container" style="max-width: 860px; margin: 3rem auto 4rem; padding: 0 1rem;">
    <div style="text-align: center; margin-bottom: 2.5rem;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: #f0fdfa; color: #0f766e; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.75rem;">
            <i class="fa fa-user-plus"></i>
        </div>
        <h2 style="margin: 0 0 8px; font-size: 1.6rem; color: #0f172a; font-weight: 800;">Join MwalimuLink</h2>
        <p style="margin: 0; font-size: 0.95rem; color: #64748b;">Select how you want to use Kenya's premier educator and school recruitment network.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Teacher Role Card -->
        <div style="background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 14px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fa fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">I am an Educator</h3>
                        <span style="font-size: 0.8rem; color: #64748b;">Teacher, Tutor, or Student Intern</span>
                    </div>
                </div>

                <p style="font-size: 0.88rem; color: #475569; line-height: 1.5; margin-bottom: 1.25rem;">
                    Create your professional teaching CV, get verified with DCI clearance standing, and connect with top public and private schools.
                </p>

                <ul style="margin: 0 0 1.5rem; padding-left: 20px; font-size: 0.84rem; color: #334155; line-height: 1.7;">
                    <li>Free verified candidate profile</li>
                    <li>Apply for active vacancies across Kenya</li>
                    <li>Access Teaching Practice (TP) placements</li>
                    <li>Direct interview invitations via WhatsApp</li>
                </ul>
            </div>

            <a href="/register/teacher" class="btn-primary" style="width: 100%; height: 46px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                Create Educator Profile →
            </a>
        </div>

        <!-- School Role Card -->
        <div style="background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 14px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #fef3c7; color: #92400e; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fa fa-school"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">I am a School</h3>
                        <span style="font-size: 0.8rem; color: #64748b;">Primary, Secondary, or International School</span>
                    </div>
                </div>

                <p style="font-size: 0.88rem; color: #475569; line-height: 1.5; margin-bottom: 1.25rem;">
                    Recruit qualified teachers, post subject vacancies, and filter candidates by TSC registration, county, and verified police clearance.
                </p>

                <ul style="margin: 0 0 1.5rem; padding-left: 20px; font-size: 0.84rem; color: #334155; line-height: 1.7;">
                    <li>Post unlimited teaching job openings</li>
                    <li>Instant candidate search by subject & grade</li>
                    <li>Direct contact access with verified teachers</li>
                    <li>Priority directory placement for your academy</li>
                </ul>
            </div>

            <a href="/register/school" style="width: 100%; height: 46px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; background: #0f172a; color: white !important;">
                Register Your Institution →
            </a>
        </div>
    </div>

    <div style="text-align: center; font-size: 0.9rem; color: #64748b;">
        Already have an account? <a href="/login" style="color: #0f766e; font-weight: 700; text-decoration: underline;">Sign In here</a>
    </div>
</div>
