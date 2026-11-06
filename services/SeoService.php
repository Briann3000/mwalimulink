<?php
// services/SeoService.php - Centralized SEO, Social Graph & Structured Data Engine
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

class SeoService
{
    /**
     * Resolve all metadata for a given route URI.
     *
     * @param string $requestUri
     * @param array $queryParams
     * @return array
     */
    public static function resolve(string $requestUri, array $queryParams = []): array
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'mwalimu.info';
        $baseUrl = rtrim($scheme . $host, '/');

        // Dynamic Robots Directive (Strictly protect private dashboards & forms from wasting crawl budget)
        $robots = 'index, follow';
        if (
            str_starts_with($requestUri, 'admin') ||
            str_starts_with($requestUri, 'teacher/') ||
            str_starts_with($requestUri, 'school/') ||
            str_starts_with($requestUri, 'messages') ||
            str_starts_with($requestUri, 'login') ||
            str_starts_with($requestUri, 'register') ||
            str_starts_with($requestUri, 'reset-password') ||
            str_starts_with($requestUri, 'unsubscribe') ||
            str_starts_with($requestUri, 'staff-invitation') ||
            str_starts_with($requestUri, 'payment') ||
            str_starts_with($requestUri, 'directory/pay') ||
            str_starts_with($requestUri, 'schools/pay') ||
            str_starts_with($requestUri, 'api/')
        ) {
            $robots = 'noindex, nofollow';
        }

        // Default fallback metadata
        $title = 'MwalimuLink - Teaching Jobs, School Directories & Teacher Resources in Kenya';
        $description = 'Kenya\'s leading educator network. Discover verified teaching vacancies, browse 30,000+ public and private schools, and access CBC teaching resources.';
        $canonicalPath = '/' . ltrim($requestUri, '/');
        $ogType = 'website';
        $ogImage = $baseUrl . '/hero-teacher.jpg';
        $schemas = [];

        // Base WebSite and Organization Schema
        $siteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'MwalimuLink',
            'url' => $baseUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/jobs?q={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];

        switch ($requestUri) {
            case '':
            case 'home':
                $title = 'MwalimuLink - Teaching Jobs, School Directories & Teacher Resources in Kenya';
                $description = 'Discover active teaching vacancies across Kenyan schools, browse over 30,000 institution directories, and access CBC curriculum revision notes and schemes.';
                $canonicalPath = '/';
                $schemas[] = $siteSchema;
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'MwalimuLink',
                    'url' => $baseUrl,
                    'logo' => $baseUrl . '/mwalimu-link-logo-clean.png',
                    'description' => 'Comprehensive digital educational platform connecting teachers, institutions, and learners across Kenya.'
                ];
                break;

            case 'jobs':
                $q = trim($queryParams['q'] ?? '');
                $county = trim($queryParams['county'] ?? '');
                if (!empty($q) && !empty($county)) {
                    $title = htmlspecialchars($q) . ' Teaching Jobs in ' . htmlspecialchars($county) . ' | MwalimuLink';
                    $description = 'Find active ' . htmlspecialchars($q) . ' teacher vacancies in ' . htmlspecialchars($county) . ' County. Apply directly with verified credentials.';
                } elseif (!empty($q)) {
                    $title = htmlspecialchars($q) . ' Teacher Vacancies in Kenya 2026 | MwalimuLink';
                    $description = 'Explore latest ' . htmlspecialchars($q) . ' teaching positions across Kenyan schools, junior secondary, and academies.';
                } elseif (!empty($county)) {
                    $title = 'Teaching Jobs in ' . htmlspecialchars($county) . ' County | MwalimuLink';
                    $description = 'Browse current teaching opportunities in ' . htmlspecialchars($county) . ' County. Primary, secondary, and international school vacancies.';
                } else {
                    $title = 'Teaching Job Vacancies in Kenya 2026 - CBC, High School & International Posts | MwalimuLink';
                    $description = 'Browse active teaching jobs across Kenyan counties. Verified opportunities in CBC junior secondary, primary schools, international academies, and colleges.';
                }
                $canonicalPath = '/jobs';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Teaching Vacancies', 'url' => '/jobs']
                ]);
                break;

            case 'jobs/view':
                $jobId = intval($queryParams['id'] ?? 0);
                if ($jobId > 0 && class_exists('R') && R::testConnection()) {
                    $job = R::load('job', $jobId);
                    if ($job && $job->id) {
                        $isExt = ($job->source_type === 'external');
                        $school = !$isExt ? R::load('school', $job->school_id) : null;
                        $instName = $isExt ? ($job->company_name ?: ($job->source_name ?: 'Education Institution')) : ($school->name ?? 'Registered School');
                        $instLoc = $isExt ? ($job->location_text ?: 'Kenya') : ($school->county ?? 'Kenya');

                        $title = "{$job->title} at {$instName} ({$instLoc}) - Teaching Vacancy Kenya | MwalimuLink";
                        
                        $descText = strip_tags($job->description ?: ($job->requirements ?: ''));
                        $descText = preg_replace('/\s+/', ' ', trim($descText));
                        if (strlen($descText) > 155) {
                            $descText = substr($descText, 0, 152) . '...';
                        }
                        $description = !empty($descText) ? $descText : "Apply for {$job->title} at {$instName} in {$instLoc}. Verified teaching opportunity on MwalimuLink.";
                        $canonicalPath = '/jobs/view?id=' . $job->id;
                        $ogType = 'article';

                        // Breadcrumb Schema
                        $schemas[] = self::buildBreadcrumbs($baseUrl, [
                            ['name' => 'Home', 'url' => '/'],
                            ['name' => 'Teaching Vacancies', 'url' => '/jobs'],
                            ['name' => $job->title, 'url' => '/jobs/view?id=' . $job->id]
                        ]);
                    }
                }
                break;

            case 'schools/public':
                $county = trim($queryParams['county'] ?? '');
                if (!empty($county)) {
                    $title = "Public Schools in {$county} County - Primary & Secondary Directory | MwalimuLink";
                    $description = "Directory of public primary and secondary schools in {$county} County, Kenya. View school contacts, sub-counties, and facilities.";
                } else {
                    $title = 'Public Schools Directory Kenya - Over 28,000 Institutions | MwalimuLink';
                    $description = 'Search over 28,000 public primary and secondary schools across all 47 Kenyan counties. Find locations, levels, and school profiles.';
                }
                $canonicalPath = '/schools/public';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Public Schools Directory', 'url' => '/schools/public']
                ]);
                break;

            case 'schools/public/detail':
                $schoolId = intval($queryParams['id'] ?? 0);
                if ($schoolId > 0 && class_exists('R') && R::testConnection()) {
                    $pubSchool = R::load('public_school', $schoolId);
                    if ($pubSchool && $pubSchool->id) {
                        $sName = trim($pubSchool->name);
                        $sCounty = trim($pubSchool->county ?? 'Kenya');
                        $sLevel = trim($pubSchool->level ?? 'School');
                        $title = "{$sName} ({$sCounty} County) - {$sLevel} Profile & Contacts | MwalimuLink";
                        $description = "{$sName} official school profile in {$sCounty} County. Details on school level, constituency, educator capacity, and contact information.";
                        $canonicalPath = '/schools/public/detail?id=' . $pubSchool->id;
                        
                        $schemas[] = [
                            '@context' => 'https://schema.org',
                            '@type' => 'School',
                            'name' => $sName,
                            'address' => [
                                '@type' => 'PostalAddress',
                                'addressLocality' => $sCounty,
                                'addressCountry' => 'KE'
                            ]
                        ];
                        $schemas[] = self::buildBreadcrumbs($baseUrl, [
                            ['name' => 'Home', 'url' => '/'],
                            ['name' => 'Public Schools', 'url' => '/schools/public'],
                            ['name' => $sName, 'url' => '/schools/public/detail?id=' . $pubSchool->id]
                        ]);
                    }
                }
                break;

            case 'schools/private':
                $title = 'Private Schools & Academies in Kenya Directory | MwalimuLink';
                $description = 'Explore verified private schools, premier academies, and international curriculum institutions in Kenya. Discover academic levels and teaching opportunities.';
                $canonicalPath = '/schools/private';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Private Academies', 'url' => '/schools/private']
                ]);
                break;

            case 'schools/private/detail':
                $privId = intval($queryParams['id'] ?? 0);
                if ($privId > 0 && class_exists('R') && R::testConnection()) {
                    $privSchool = R::load('private_school', $privId);
                    if ($privSchool && $privSchool->id) {
                        $pName = trim($privSchool->name);
                        $pCounty = trim($privSchool->county ?? 'Kenya');
                        $title = "{$pName} ({$pCounty}) - Private Academy Profile | MwalimuLink";
                        $description = "Profile for {$pName} in {$pCounty}. Learn about academic programs, curriculum, educator vacancies, and institutional facilities.";
                        $canonicalPath = '/schools/private/detail?id=' . $privSchool->id;
                        
                        $schemas[] = [
                            '@context' => 'https://schema.org',
                            '@type' => 'School',
                            'name' => $pName,
                            'address' => [
                                '@type' => 'PostalAddress',
                                'addressLocality' => $pCounty,
                                'addressCountry' => 'KE'
                            ]
                        ];
                        $schemas[] = self::buildBreadcrumbs($baseUrl, [
                            ['name' => 'Home', 'url' => '/'],
                            ['name' => 'Private Academies', 'url' => '/schools/private'],
                            ['name' => $pName, 'url' => '/schools/private/detail?id=' . $privSchool->id]
                        ]);
                    }
                }
                break;

            case 'schools/international':
                $title = 'International Schools in Kenya - British, IB & Cambridge Curriculums | MwalimuLink';
                $description = 'Comprehensive directory of international schools in Kenya offering Cambridge IGCSE, International Baccalaureate (IB), and American curriculum diplomas.';
                $canonicalPath = '/schools/international';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'International Schools', 'url' => '/schools/international']
                ]);
                break;

            case 'schools/international/detail':
                $intlId = intval($queryParams['id'] ?? 0);
                if ($intlId > 0 && class_exists('R') && R::testConnection()) {
                    $intlSchool = R::load('international_school', $intlId);
                    if ($intlSchool && $intlSchool->id) {
                        $iName = trim($intlSchool->name);
                        $iCity = trim($intlSchool->city ?? '');
                        $iCountry = trim($intlSchool->country ?? 'Kenya');
                        $title = "{$iName}" . (!empty($iCity) ? " ({$iCity}, {$iCountry})" : " ({$iCountry})") . " - International School Profile | MwalimuLink";
                        $description = "{$iName} profile in {$iCountry}. Explore international curriculum diplomas (IB, Cambridge, British, American) and educator opportunities.";
                        $canonicalPath = '/schools/international/detail?id=' . $intlSchool->id;
                        
                        $schemas[] = [
                            '@context' => 'https://schema.org',
                            '@type' => 'School',
                            'name' => $iName,
                            'address' => [
                                '@type' => 'PostalAddress',
                                'addressLocality' => $iCity ?: $iCountry,
                                'addressCountry' => $iCountry
                            ]
                        ];
                        $schemas[] = self::buildBreadcrumbs($baseUrl, [
                            ['name' => 'Home', 'url' => '/'],
                            ['name' => 'International Schools', 'url' => '/schools/international'],
                            ['name' => $iName, 'url' => '/schools/international/detail?id=' . $intlSchool->id]
                        ]);
                    }
                }
                break;

            case 'elibrary':
            case 'resources':
                $title = 'E-Library & CBC Teaching Materials - Notes, Schemes of Work & Exams | MwalimuLink';
                $description = 'Download CBC curriculum resources, primary and secondary teacher lesson plans, schemes of work, revision notes, and exam past papers in Kenya.';
                $canonicalPath = '/elibrary';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'E-Library Catalog', 'url' => '/elibrary']
                ]);
                break;

            case 'elibrary/view':
            case 'resources/view':
                $resId = intval($queryParams['id'] ?? 0);
                if ($resId > 0 && class_exists('R') && R::testConnection()) {
                    $resource = R::load('resource', $resId);
                    if ($resource && $resource->id) {
                        $rTitle = trim($resource->title);
                        $rSubject = trim($resource->subject ?? 'General');
                        $rGrade = trim($resource->grade_level ?? 'CBC');
                        $title = "{$rTitle} ({$rGrade} {$rSubject}) Notes & Revision Material PDF | MwalimuLink";
                        
                        $rDesc = strip_tags($resource->description ?? '');
                        $rDesc = preg_replace('/\s+/', ' ', trim($rDesc));
                        if (strlen($rDesc) > 155) {
                            $rDesc = substr($rDesc, 0, 152) . '...';
                        }
                        $description = !empty($rDesc) ? $rDesc : "Download {$rTitle} for {$rGrade} {$rSubject} on MwalimuLink E-Library.";
                        $canonicalPath = '/elibrary/view?id=' . $resource->id;
                        $ogType = 'article';

                        $schemas[] = [
                            '@context' => 'https://schema.org',
                            '@type' => 'LearningResource',
                            'name' => $rTitle,
                            'description' => $description,
                            'learningResourceType' => 'Lesson Plan / Notes',
                            'educationalLevel' => $rGrade
                        ];
                    }
                }
                break;

            case 'forum':
                $title = 'Teachers Forum Kenya - Educator Community Discussions & Professional Advice | MwalimuLink';
                $description = 'Join Kenyan educators in open professional discussions. Discuss TSC guidelines, CBC implementation, classroom management, and teaching careers.';
                $canonicalPath = '/forum';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Teachers Forum', 'url' => '/forum']
                ]);
                break;

            case 'forum/thread':
                $threadId = intval($queryParams['id'] ?? 0);
                if ($threadId > 0 && class_exists('R') && R::testConnection()) {
                    $thread = R::load('forumthread', $threadId);
                    if ($thread && $thread->id) {
                        $thTitle = trim($thread->title);
                        $title = "{$thTitle} | MwalimuLink Teachers Forum";
                        $thSnippet = strip_tags($thread->content ?? '');
                        $thSnippet = preg_replace('/\s+/', ' ', trim($thSnippet));
                        if (strlen($thSnippet) > 155) {
                            $thSnippet = substr($thSnippet, 0, 152) . '...';
                        }
                        $description = !empty($thSnippet) ? $thSnippet : "Read and contribute to educator discussion on {$thTitle}.";
                        $canonicalPath = '/forum/thread?id=' . $thread->id;
                        $ogType = 'article';

                        $schemas[] = [
                            '@context' => 'https://schema.org',
                            '@type' => 'DiscussionForumPosting',
                            'headline' => $thTitle,
                            'articleBody' => $thSnippet,
                            'author' => [
                                '@type' => 'Person',
                                'name' => 'Kenyan Educator'
                            ],
                            'datePublished' => !empty($thread->created_at) ? date('c', strtotime($thread->created_at)) : date('c'),
                            'interactionStatistic' => [
                                [
                                    '@type' => 'InteractionCounter',
                                    'interactionType' => 'https://schema.org/CommentAction',
                                    'userInteractionCount' => intval($thread->replies_count ?? 0)
                                ],
                                [
                                    '@type' => 'InteractionCounter',
                                    'interactionType' => 'https://schema.org/ViewAction',
                                    'userInteractionCount' => intval($thread->views_count ?? 0)
                                ]
                            ],
                            'mainEntityOfPage' => $baseUrl . $canonicalPath
                        ];
                        $schemas[] = self::buildBreadcrumbs($baseUrl, [
                            ['name' => 'Home', 'url' => '/'],
                            ['name' => 'Teachers Forum', 'url' => '/forum'],
                            ['name' => $thTitle, 'url' => '/forum/thread?id=' . $thread->id]
                        ]);
                    }
                }
                break;

            case 'publications':
            case 'blog':
                $title = 'Educational Research & Publications Hub | MwalimuLink';
                $description = 'Peer-reviewed educational research, CBC transition studies, policy papers, and thought leadership from Kenyan and African educators.';
                $canonicalPath = '/publications';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Educational Research', 'url' => '/publications']
                ]);
                break;

            case 'publications/view':
            case 'blog/view':
                $pubId = intval($queryParams['id'] ?? 0);
                if ($pubId > 0 && class_exists('R') && R::testConnection()) {
                    $publication = R::load('publication', $pubId);
                    if (!$publication || !$publication->id) {
                        $publication = R::load('publications', $pubId);
                    }
                    if ($publication && $publication->id) {
                        $pTitle = trim($publication->title);
                        $title = "{$pTitle} | MwalimuLink Educational Research Hub";
                        $pAbs = strip_tags($publication->abstract ?: ($publication->content ?: ''));
                        $pAbs = preg_replace('/\s+/', ' ', trim($pAbs));
                        if (strlen($pAbs) > 155) {
                            $pAbs = substr($pAbs, 0, 152) . '...';
                        }
                        $description = !empty($pAbs) ? $pAbs : "Read educational research paper: {$pTitle} on MwalimuLink.";
                        $canonicalPath = '/publications/view?id=' . $publication->id;
                        $ogType = 'article';

                        $schemas[] = [
                            '@context' => 'https://schema.org',
                            '@type' => 'ScholarlyArticle',
                            'headline' => $pTitle,
                            'description' => $description,
                            'author' => [
                                '@type' => 'Person',
                                'name' => trim($publication->author ?? 'Educator Author')
                            ],
                            'datePublished' => !empty($publication->created_at) ? date('c', strtotime($publication->created_at)) : date('c'),
                            'publisher' => [
                                '@type' => 'Organization',
                                'name' => 'MwalimuLink Educational Research Hub',
                                'url' => $baseUrl,
                                'logo' => $baseUrl . '/mwalimu-link-logo-clean.png'
                            ],
                            'mainEntityOfPage' => $baseUrl . $canonicalPath
                        ];
                        $schemas[] = self::buildBreadcrumbs($baseUrl, [
                            ['name' => 'Home', 'url' => '/'],
                            ['name' => 'Educational Research', 'url' => '/publications'],
                            ['name' => $pTitle, 'url' => '/publications/view?id=' . $publication->id]
                        ]);
                    }
                }
                break;

            case 'tp-hub':
                $title = 'Teaching Practice (TP) Hub Kenya - Placements & Guidelines | MwalimuLink';
                $description = 'Connect student teachers with accredited Kenyan primary and secondary schools for Teaching Practice (TP) attachments, guidelines, and assessments.';
                $canonicalPath = '/tp-hub';
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'TP Hub', 'url' => '/tp-hub']
                ]);
                break;

            case 'about':
                $title = 'About MwalimuLink - Connecting Kenyan Teachers & Schools';
                $description = 'Learn about MwalimuLink\'s mission to empower educators and institutions with verified recruitment, comprehensive directories, and digital curriculum tools.';
                $canonicalPath = '/about';
                break;

            case 'contacts':
                $title = 'Contact MwalimuLink Support & Educator Helpdesk';
                $description = 'Get in touch with MwalimuLink for assistance with teacher verification, school job postings, or platform inquiries.';
                $canonicalPath = '/contacts';
                break;

            case 'faqs':
                $title = 'Frequently Asked Questions for Educators & Schools | MwalimuLink';
                $description = 'Answers to common questions about teacher profiles, verified registration, school directories, and 1-click vacancy applications.';
                $canonicalPath = '/faqs';
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => [
                        [
                            '@type' => 'Question',
                            'name' => 'Is registration free for teachers on MwalimuLink?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Yes. Registration is 100% free for all teachers and educators. Once registered, you can build your digital resume, showcase your TSC registration, list your subject specializations, and explore job vacancies across Kenya.'
                            ]
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'Can I update my profile after registration?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Yes. Log in to your educator account and navigate to Edit Profile. You can update your academic qualifications, teaching subjects, years of experience, preferred counties, and upload new certifications at any time.'
                            ]
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'What file formats are accepted for CV and certificate uploads?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'We accept PDF, DOC, DOCX, and high-resolution image files (JPG/PNG). We recommend PDF format for clean, professional rendering across all devices. Maximum file size per document is 5MB.'
                            ]
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'Will I be notified when a school reviews my profile or shortlists me?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Yes. You will receive an instant email notification whenever a school administrator shortlists you or invites you for an interview. You can also monitor your active applications directly from your Teacher Dashboard.'
                            ]
                        ]
                    ]
                ];
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Help & FAQs', 'url' => '/faqs']
                ]);
                break;

            case 'faqs-overseas':
                $title = 'Teaching Overseas FAQs - Work Abroad Guide for Kenyan Teachers | MwalimuLink';
                $description = 'Guide for Kenyan teachers seeking international teaching jobs. Requirements, curriculum adaptations (IGCSE/IB), TSC clearance, and visas.';
                $canonicalPath = '/faqs-overseas';
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => [
                        [
                            '@type' => 'Question',
                            'name' => 'Can Kenyan TSC-registered teachers teach overseas?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Yes. Many countries in the UK, Middle East (UAE, Qatar, Oman), Asia, and North America hire Kenyan educators, especially in STEM subjects, English, and CBC-compatible curricula.'
                            ]
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'What documentation is required to teach abroad?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Requirements typically include a Bachelor of Education or PGDE, TSC registration certificate, certificate of good conduct, official academic transcripts, and certified reference letters.'
                            ]
                        ]
                    ]
                ];
                $schemas[] = self::buildBreadcrumbs($baseUrl, [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Teaching Overseas FAQs', 'url' => '/faqs-overseas']
                ]);
                break;
                break;
        }

        $canonicalUrl = $baseUrl . $canonicalPath;

        // Build Open Graph & Twitter Cards HTML
        $ogTags = self::generateSocialMeta([
            'title' => $title,
            'description' => $description,
            'url' => $canonicalUrl,
            'type' => $ogType,
            'image' => $ogImage,
            'site_name' => 'MwalimuLink'
        ]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'robots' => $robots,
            'og_tags' => $ogTags,
            'schemas' => $schemas
        ];
    }

    /**
     * Build rich Open Graph & Twitter Card meta HTML.
     */
    private static function generateSocialMeta(array $meta): string
    {
        $tags = [];
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($meta['site_name']) . '">';
        $tags[] = '<meta property="og:type" content="' . htmlspecialchars($meta['type']) . '">';
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($meta['title']) . '">';
        $tags[] = '<meta property="og:description" content="' . htmlspecialchars($meta['description']) . '">';
        $tags[] = '<meta property="og:url" content="' . htmlspecialchars($meta['url']) . '">';
        $tags[] = '<meta property="og:image" content="' . htmlspecialchars($meta['image']) . '">';
        $tags[] = '<meta property="og:image:width" content="1200">';
        $tags[] = '<meta property="og:image:height" content="630">';
        $tags[] = '<meta property="og:locale" content="en_KE">';

        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars($meta['title']) . '">';
        $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars($meta['description']) . '">';
        $tags[] = '<meta name="twitter:image" content="' . htmlspecialchars($meta['image']) . '">';

        return implode("\n  ", $tags);
    }

    /**
     * Generate BreadcrumbList Schema.org JSON-LD.
     */
    private static function buildBreadcrumbs(string $baseUrl, array $items): array
    {
        $itemList = [];
        foreach ($items as $idx => $it) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $idx + 1,
                'name' => $it['name'],
                'item' => rtrim($baseUrl, '/') . '/' . ltrim($it['url'], '/')
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList
        ];
    }
}
