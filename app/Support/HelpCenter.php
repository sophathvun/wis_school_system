<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class HelpCenter
{
    public const CATEGORIES = [
        'getting-started' => ['label'=>'Getting started','icon'=>'ti-compass','description'=>'Find your way around and understand access.'],
        'g9' => ['label'=>'G9 National Exams','icon'=>'ti-school','description'=>'Candidates, scores, results, reports, and certificates.'],
        'g12' => ['label'=>'G12 National Exams','icon'=>'ti-certificate','description'=>'Exam records, assignments, grading, imports, reports, and collection.'],
        'attendance' => ['label'=>'Attendance','icon'=>'ti-calendar-check','description'=>'Subject sessions, daily records, approved leave, calendars, and reports.'],
        'administrator' => ['label'=>'Administrator','icon'=>'ti-shield-lock','description'=>'Users, roles, permissions, dashboards, branding, and database backups.'],
        'students' => ['label'=>'Students','icon'=>'ti-users','description'=>'Student records, enrollment, academic changes, family, documents, and admissions.'],
    ];

    public function catalog(): array
    {
        return [
            'getting-started'=>['title'=>'Get started with the school system','summary'=>'Navigation, filters, permissions, and finding help.','category'=>'getting-started','action'=>null,'route'=>null,'keywords'=>'help navigation access campus year Khmer ជំនួយ'],
            'g9-workflow'=>['title'=>'G9 examination workflow','summary'=>'Follow the process from candidate preparation to certificate receipt.','category'=>'g9','action'=>'view','route'=>'index','keywords'=>'overview checklist process ថ្នាក់ទី៩'],
            'g9-candidates'=>['title'=>'Find and prepare G9 candidates','summary'=>'Check candidate details, exam center, room, seat, and attendance.','category'=>'g9','action'=>'candidates','route'=>'candidates','keywords'=>'student seat room បេក្ខជន'],
            'g9-settings'=>['title'=>'Set exam center details and document dates','summary'=>'Configure year defaults, center overrides, locations, and issue dates.','category'=>'g9','action'=>'settings','route'=>'settings','keywords'=>'date lunar solar principal city province Result Issue Date កាលបរិច្ឆេទ'],
            'g9-eoy'=>['title'=>'Assign the end-of-year average','summary'=>'Select candidates, record EOY averages, and open score-card reports.','category'=>'g9','action'=>'eoy','route'=>'eoy-average','keywords'=>'EOY average annual score មធ្យមភាគ'],
            'g9-scoring'=>['title'=>'Enter scores and record absence','summary'=>'Enter subject marks and understand passed, failed, absent, and pending results.','category'=>'g9','action'=>'scoring','route'=>'scoring','keywords'=>'marks total letter grade attendance ពិន្ទុ អវត្តមាន'],
            'g9-results'=>['title'=>'Explore results, graphs, and statistics','summary'=>'Use filters and compare outcomes, letter grades, and subjects.','category'=>'g9','action'=>'results','route'=>'results','keywords'=>'graph English Khmer percentage passed failed និទ្ទេស លទ្ធផល'],
            'g9-reports'=>['title'=>'Print and export G9 result reports','summary'=>'Choose the right report and understand dates, totals, and page sizes.','category'=>'g9','action'=>'results','route'=>'results','keywords'=>'Print Excel PDF DoEYS WIS branch room subject បញ្ជី ស្ថិតិ'],
            'g9-certificates'=>['title'=>'Track certificates and student receipt','summary'=>'Upload a provisional certificate, record collection, and review imported history.','category'=>'g9','action'=>'certificates','route'=>'certificates','keywords'=>'certificate received recipient WebP historical number សញ្ញាបត្រ'],
            'g9-certificate-numbers'=>['title'=>'Generate certificate numbers safely','summary'=>'Check eligibility, review numbering settings, and preserve issued numbers.','category'=>'g9','action'=>'certificates-number-manage','route'=>'certificates','keywords'=>'prefix serial numbering ៩២៥០០០១ generate'],
            'g9-imports'=>['title'=>'Import and export candidate data','summary'=>'Use the template, review a preview, and confirm an import.','category'=>'g9','action'=>'imports','route'=>'imports-exports','keywords'=>'Excel backup review template duplicates upload'],
            'g9-photos'=>['title'=>'Upload student photos in bulk','summary'=>'Preview photo matches before confirming a bulk upload.','category'=>'g9','action'=>'photos','route'=>'bulk-photos','keywords'=>'picture image student matching filename រូបថត'],
            'g12-workflow'=>['title'=>'G12 national examination workflow','summary'=>'Prepare exam records, assign centers, verify results, and track certificates.','category'=>'g12','action'=>'view','route'=>'index','keywords'=>'overview checklist ថ្នាក់ទី១២'],
            'g12-records'=>['title'=>'Manage G12 student exam records','summary'=>'Find students and maintain examination details separately from school profiles.','category'=>'g12','action'=>'records','route'=>'records','keywords'=>'student identity academic year campus create edit បេក្ខជន'],
            'g12-centers'=>['title'=>'Manage G12 exam centers','summary'=>'Create, edit, and activate centers for examination assignment.','category'=>'g12','action'=>'centers','route'=>'centers','keywords'=>'settings center ID active inactive មណ្ឌលប្រឡង'],
            'g12-assignments'=>['title'=>'Assign exam center, room, and seat','summary'=>'Check assignment totals and update each candidate’s examination location.','category'=>'g12','action'=>'assign-centers','route'=>'assign-centers','keywords'=>'room seat language selected class បន្ទប់ លេខតុ'],
            'g12-scoring'=>['title'=>'Enter G12 subject and overall grades','summary'=>'Record A–F subject grades, Score / Rank, and the overall Letter Grade.','category'=>'g12','action'=>'scoring','route'=>'scoring','keywords'=>'science social English Khmer marks ពិន្ទុ និទ្ទេស'],
            'g12-scoring-csv'=>['title'=>'Export and import G12 scoring CSV','summary'=>'Review a scoring spreadsheet before confirming grade updates.','category'=>'g12','action'=>'scoring','route'=>'scoring','keywords'=>'CSV Excel score import export preview'],
            'g12-ocr'=>['title'=>'Scan and review MoEYS result images','summary'=>'Upload a center’s images, inspect OCR matches, and confirm reviewed grades.','category'=>'g12','action'=>'scoring-ocr-upload','route'=>'scoring','keywords'=>'OCR Google Cloud Vision scan image unmatched review'],
            'g12-results'=>['title'=>'Review G12 results and result exports','summary'=>'Filter final outcomes and choose Grade A, result-list, or statistics reports.','category'=>'g12','action'=>'results','route'=>'results','keywords'=>'passed failed pending A F Print PDF Excel លទ្ធផល'],
            'g12-reports'=>['title'=>'Use G12 graphs and statistics','summary'=>'Explore the Reports workspace and export available graphs and tables.','category'=>'g12','action'=>'reports','route'=>'reports','keywords'=>'chart graph DoEYS statistics campus percentage ស្ថិតិ'],
            'g12-grade-a'=>['title'=>'Prepare Grade A certificates','summary'=>'Review eligible Grade A students, numbering, layout, and PDF output.','category'=>'g12','action'=>'reports','route'=>'reports','keywords'=>'A certificate editor frame issue date number សញ្ញាបត្រ'],
            'g12-certificates'=>['title'=>'Track G12 certificates and collection','summary'=>'Manage provisional and original certificates, school receipt, and student collection.','category'=>'g12','action'=>'certificates','route'=>'certificates','keywords'=>'soft hard MoEYS copy signed receipt received collected correction'],
            'g12-imports'=>['title'=>'Import G12 Excel records and review history','summary'=>'Match campuses and centers, inspect proposed changes, and confirm an import.','category'=>'g12','action'=>'imports','route'=>'imports','keywords'=>'MoEYS Excel template workbook duplicate backup'],
            'g12-photos'=>['title'=>'Preview and upload G12 student photos','summary'=>'Match images or ZIP contents to students and upload selected photos.','category'=>'g12','action'=>'photos','route'=>'photos','keywords'=>'photo ZIP image portrait replace bulk រូបថត'],
            'attendance-workflow'=>['title'=>'Understand the Attendance workflow','summary'=>'Choose subject attendance, daily attendance, leave, settings, or reports.','category'=>'attendance','action'=>'view','route'=>null,'keywords'=>'overview teacher admin student វត្តមាន'],
            'attendance-subject'=>['title'=>'Take and submit subject attendance','summary'=>'Open a scheduled session, mark each student, save a draft, and submit.','category'=>'attendance','action'=>'subject','route'=>'index','keywords'=>'teacher present absent late excused sick draft submit វត្តមានមុខវិជ្ជា'],
            'attendance-daily'=>['title'=>'Record admin daily attendance','summary'=>'Use P, E, U, and L, apply Present to blanks, and correct past records.','category'=>'attendance','action'=>'daily','route'=>'admin-daily','keywords'=>'autosave daily class missed periods absence E U P L អវត្តមាន'],
            'attendance-leave'=>['title'=>'Record and manage approved student leave','summary'=>'Record full or partial leave, attach approval, and shorten or cancel remaining leave.','category'=>'attendance','action'=>'daily','route'=>'admin-daily','keywords'=>'approved leave reason permission letter future excused ច្បាប់'],
            'attendance-calendar'=>['title'=>'Configure school days and public holidays','summary'=>'Set the year and campus calendar and understand scheduled subject sessions.','category'=>'attendance','action'=>'settings','route'=>'settings','keywords'=>'weekdays holidays schedule calendar Monday Friday ថ្ងៃឈប់សម្រាក'],
            'attendance-detail'=>['title'=>'View a student’s absent and late records','summary'=>'Select a student and date range in Stu. Attendance Detail.','category'=>'attendance','action'=>'detail','route'=>'reports','keywords'=>'Student Absent Record detail date range reason អវត្តមាន'],
            'attendance-statistics'=>['title'=>'Review Attendance Statistics','summary'=>'Compare attendance totals using the same year, campus, grade, and date range.','category'=>'attendance','action'=>'statistics','route'=>'reports','keywords'=>'statistics percentage totals daily students ស្ថិតិ'],
            'attendance-exports'=>['title'=>'Print and export attendance reports','summary'=>'Review filters and export student detail or statistics to Print, Excel, or PDF.','category'=>'attendance','action'=>'reports','route'=>'reports','keywords'=>'print Excel PDF report export download'],
            'attendance-corrections'=>['title'=>'Correct attendance and review its history','summary'=>'Reopen submitted sessions, edit past daily marks, and understand audit history.','category'=>'attendance','action'=>'view','route'=>null,'keywords'=>'reopen correction history audit delete locked version'],
            'admin-workflow'=>['title'=>'Administrator setup and access checklist','summary'=>'Plan users, roles, departments, campus access, and routine administration.','category'=>'administrator','action'=>'administrator.view','route'=>null,'keywords'=>'administrator setup security access admin អ្នកគ្រប់គ្រង'],
            'admin-users'=>['title'=>'Create and maintain user accounts','summary'=>'Manage account details, roles, campus assignment, and password changes.','category'=>'administrator','action'=>'super-admin','route'=>'users.index','keywords'=>'users account username email password campus active global'],
            'admin-roles'=>['title'=>'Create and maintain roles','summary'=>'Define reusable roles and understand their department and scope.','category'=>'administrator','action'=>'super-admin','route'=>'roles.index','keywords'=>'role code department global campus status'],
            'admin-department-permissions'=>['title'=>'Set permissions by department','summary'=>'Choose a department and configure its main menus, submenus, and actions.','category'=>'administrator','action'=>'settings.manage','route'=>'access-management.index','keywords'=>'Permissions by Dept Select Department Full Access search'],
            'admin-role-permissions'=>['title'=>'Set permissions by role','summary'=>'Select a role and department, then review and save its permission tree.','category'=>'administrator','action'=>'settings.manage','route'=>'access-management.index','keywords'=>'Permissions by Role Role Department Select Role main submenu'],
            'admin-user-permissions'=>['title'=>'Review inherited and additional user permissions','summary'=>'Inspect a user’s role and department access and assign additional permissions.','category'=>'administrator','action'=>'settings.manage','route'=>'access-management.index','keywords'=>'override inherited direct user campus Full Access denied'],
            'admin-permission-list'=>['title'=>'Find and report permission definitions','summary'=>'Search the Permission List and print or export access summaries.','category'=>'administrator','action'=>'settings.manage','route'=>'access-management.index','keywords'=>'Permission List code module action report Excel Print'],
            'admin-dashboards'=>['title'=>'Build and assign dashboard templates','summary'=>'Arrange sections and widgets and assign dashboard templates to users or roles.','category'=>'administrator','action'=>'super-admin','route'=>'dashboard-templates.index','keywords'=>'dashboard template widgets sections columns drag assignments'],
            'admin-branding'=>['title'=>'Update system branding and logos','summary'=>'Upload the correct sidebar, login, shortcut, report, and footer assets.','category'=>'administrator','action'=>'branding.view','route'=>'branding-settings.index','keywords'=>'branding logo favicon shortcut school footer theme image'],
            'admin-backups'=>['title'=>'Create and download database backups','summary'=>'Review storage, create SQL backups, and keep a secure external copy.','category'=>'administrator','action'=>'database-backups.view','route'=>'database-backups.index','keywords'=>'database backup SQL storage download delete restore'],
            'students-workflow'=>['title'=>'Find the right Student workspace','summary'=>'Understand all Student submenus and choose the correct enrollment or academic workflow.','category'=>'students','action'=>'students.view','route'=>null,'keywords'=>'student navigation overview workflow សិស្ស'],
            'students-search'=>['title'=>'Search students and verify their records','summary'=>'Find the right student using year, campus, class, and identity filters.','category'=>'students','action'=>'students.search.view','route'=>'searchStudent.index','keywords'=>'Search Students ID name phone enrollment ស្វែងរកសិស្ស'],
            'students-enrollment'=>['title'=>'Register and manage student enrollment','summary'=>'Create an enrollment and review student, family, class, and academic history.','category'=>'students','action'=>'students.enrollment.view','route'=>'studentEnrollment.index','keywords'=>'Student Enrollment new old family photo academic year ចុះឈ្មោះ'],
            'students-placement'=>['title'=>'Run a student placement test','summary'=>'Manage applicants, test forms, sessions, marking, results, and the enrollment queue.','category'=>'students','action'=>'placement-tests.view','route'=>'placement-tests.index','keywords'=>'Student Placement Test applicants questions scan publish passed admission'],
            'students-summer'=>['title'=>'Register Summer School students','summary'=>'Manage summer registrations and continue eligible students at Western.','category'=>'students','action'=>'summer-school.view','route'=>'summer-school.index','keywords'=>'Summer School convert continue enrollment family documents'],
            'students-families'=>['title'=>'Manage families, contacts, and siblings','summary'=>'Review shared family records and link the correct children and guardians.','category'=>'students','action'=>'families.view','route'=>'families.index','keywords'=>'Family Management parent guardian mother father phone siblings គ្រួសារ'],
            'students-promotion'=>['title'=>'Promote students to the next academic year','summary'=>'Review source enrollments and destination classes before applying promotion.','category'=>'students','action'=>'students.promotion.view','route'=>'studentPromotion.index','keywords'=>'Student Promotion next grade year history class batch'],
            'students-transfer'=>['title'=>'Transfer students between campuses or classes','summary'=>'Check the source and destination before applying a student transfer.','category'=>'students','action'=>'students.transfer.view','route'=>'studentTransfer.index','keywords'=>'Student Transfer campus class selected history reverse'],
            'students-graduation'=>['title'=>'Record student graduation','summary'=>'Choose the graduation scope, date, and certificate details and verify the outcome.','category'=>'students','action'=>'students.graduation.view','route'=>'studentGraduation.index','keywords'=>'Student Graduation Grade 12 certificate alumni scope'],
            'students-withdrawal'=>['title'=>'Request and review student withdrawal','summary'=>'Record a withdrawal request and distinguish pending requests from approved withdrawal.','category'=>'students','action'=>'students.view','route'=>'withdrawStudent.index','keywords'=>'Withdraw Student parent signed approve reject cancel reason'],
            'students-reentry'=>['title'=>'Re-enter an officially withdrawn student','summary'=>'Return an eligible student to a regular academic-year enrollment.','category'=>'students','action'=>'student-reentry.view','route'=>'student-reentry.index','keywords'=>'Student Re-entry withdrawn return source history effective date'],
            'students-id-cards'=>['title'=>'Generate and print student ID cards','summary'=>'Review student identity, generate QR links, and print individual or class cards.','category'=>'students','action'=>'student-id-card-qr.view','route'=>'student-id-card-qr.index','keywords'=>'Stu. ID Card QR code print template public link'],
            'students-documents'=>['title'=>'Upload and manage student documents','summary'=>'Choose the student and document type, then upload, preview, or download files.','category'=>'students','action'=>'student-documents.view','route'=>'student-documents.index','keywords'=>'Student Documents PDF JPG DOCX type title number attachment ឯកសារ'],
            'students-skipping'=>['title'=>'Prepare and approve a grade-skipping request','summary'=>'Create a draft, attach signed documents, and follow the saved approval flow.','category'=>'students','action'=>'student-skipping-grade.view','route'=>'student-skipping-grade.index','keywords'=>'Grade Skipping approval Central VP My Approvals archive parent signature'],
            'students-scholarship'=>['title'=>'Manage the WIS Scholarship examination','summary'=>'Follow exam setup, candidates, seating, scores, review, awards, and publication.','category'=>'students','action'=>'western-scholarship.view','route'=>'western-scholarship.index','keywords'=>'WIS Scholarship exam ranks award percentage certificate publish'],
            'students-data-transfer'=>['title'=>'Import and export student CSV data','summary'=>'Use separate files for student information, enrollment history, and family members.','category'=>'students','action'=>'student-data-transfer.view','route'=>'student-data-transfer.index','keywords'=>'Import Export Data Student ID CSV academic year failed rows template'],
        ];
    }

    public function articles(User $user): array
    {
        $articles=[];
        foreach($this->catalog() as $slug=>$article){
            $permissions=match($article['category']){'g12'=>G12NationalExamPermissions::class,'attendance'=>AttendancePermissions::class,default=>G9NationalExamPermissions::class};
            if($article['category']==='administrator'){
                if(!$this->administratorAllows($user,$article['action']))continue;
            }elseif($article['category']==='students'){
                if(!$this->studentsAllows($user,$article['action']))continue;
            }elseif($article['action']&&!$permissions::allows($user,$article['action']))continue;
            $body=file_get_contents(resource_path('help/'.$slug.'.md'));
            $articles[$slug]=$article+['slug'=>$slug,'body'=>$body,'route_prefix'=>match($article['category']){'g12'=>'g12-national-exams.','attendance'=>'attendance.','administrator','students'=>'',default=>'g9-national-exams.'},'minutes'=>max(1,(int)ceil(str_word_count($body)/180))];
        }
        return $articles;
    }

    private function administratorAllows(User $user,string $action): bool
    {
        if($user->isSuperAdmin())return true;
        if($action==='super-admin'||!$user->hasPermission('administrator.view'))return false;
        return $user->hasPermission($action)||($action==='database-backups.view'&&$user->hasPermission('settings.manage'));
    }

    private function studentsAllows(User $user,string $action): bool
    {
        if($user->isSuperAdmin())return true;
        return match($action){
            'students.enrollment.view'=>(new StudentEnrollmentPermissions($user))->allows('view'),
            'summer-school.view'=>(new SummerSchoolPermissions($user))->allows('view'),
            'families.view'=>(new FamilyPermissions($user))->allows('view'),
            'placement-tests.view'=>PlacementTestPermissions::allows($user,'view'),
            'student-id-card-qr.view'=>StudentIdCardPermissions::allows($user,'view'),
            'student-skipping-grade.view'=>StudentSkippingGradePermissions::allows($user,'view'),
            'western-scholarship.view'=>ScholarshipPermissions::allows($user,'view'),
            default=>$user->hasPermission('students.view',$user->active_campus_id)&&$user->hasPermission($action,$user->active_campus_id),
        };
    }

    public function search(array $articles,string $query,string $category=''): array
    {
        $terms=preg_split('/\s+/u',mb_strtolower(trim($query)),-1,PREG_SPLIT_NO_EMPTY);
        return array_filter($articles,function($article)use($terms,$category){
            if($category!==''&&$article['category']!==$category)return false;
            $text=mb_strtolower($article['title'].' '.$article['summary'].' '.$article['keywords'].' '.$article['body']);
            foreach($terms as $term)if(!str_contains($text,$term))return false;
            return true;
        });
    }

    public function render(string $body): string
    {
        return Str::markdown($body,['html_input'=>'strip','allow_unsafe_links'=>false,'heading_permalink'=>['apply_id_to_heading'=>true,'insert'=>'none']]);
    }
}
