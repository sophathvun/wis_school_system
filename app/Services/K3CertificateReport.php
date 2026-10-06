<?php

namespace App\Services;

use App\Models\K3Certificate;
use App\Models\K3CertificateYear;
use App\Models\K3CertificateTemplate;
use App\Models\BrandingSetting;
use App\Support\K3CertificateLayout;
use App\Support\K3CertificateTypography;
use App\Support\K3CertificatePermissions;
use App\Support\K3CertificateQr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class K3CertificateReport
{
    public const PREVIEW_SIZES = ['all', '30', '50', '75', '100'];
    public const PREVIEW_DEFAULT_SIZE = 30;

    public function query(int $academicYearId)
    {
        return DB::table('tb_student_enrollment as e')
            ->join('tb_student as s', 's.id', '=', 'e.student_id')
            ->join('tb_grade as g', 'g.id', '=', 'e.grade_id')
            ->join('tb_class as c', 'c.id', '=', 'e.class_id')
            ->join('tb_school_info as campus', 'campus.id', '=', 'e.campus_id')
            ->join('tb_academic_year as ay', 'ay.id', '=', 'e.academic_year_id')
            ->where('e.academic_year_id', $academicYearId)
            ->where('ay.period_type', 'regular')->whereNull('ay.deleted_at')
            ->where('e.status', 1)->where('s.status', 1)
            ->whereIn('e.enrollment_status', ['active', 'completed'])
            ->whereNull('g.deleted_at')->whereNull('c.deleted_at')
            ->whereRaw("UPPER(TRIM(REPLACE(COALESCE(NULLIF(TRIM(g.grade_short_name), ''), g.grade), '-', ''))) = ?", ['K3'])
            ->select('e.id as enrollment_id', 'e.student_id', 'e.campus_id', 'e.grade_id', 'e.class_id',
                's.student_id as student_code', 's.full_name_en', 's.full_name_kh',
                'campus.campus_name_en', 'g.grade_short_name', 'c.class_name')
            ->orderByRaw('LOWER(TRIM(campus.campus_name_en))')
            ->orderByRaw('LOWER(TRIM(c.class_name))')
            ->orderByRaw("LOWER(TRIM(COALESCE(s.full_name_en, '')))")
            ->orderBy('e.student_id')->orderByDesc('e.id');
    }

    private function accessible(Request $request, $query)
    {
        if (!$request->user()->isSuperAdmin()) {
            $query->whereIn('e.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id'));
        }
        return $query;
    }

    public function rows(Request $request, int $year): Collection
    {
        // A student can have more than one enrollment row in a year; issue one certificate.
        return $this->accessible($request, $this->query($year))->get()->unique('student_id')->values();
    }

    public function canPerformAction(Request $request, string $action): bool
    {
        $permission = K3CertificatePermissions::forAction($action);
        $user = $request->user();
        if (!$permission || !$user) return false;
        return $user->isSuperAdmin() || $user->hasPermission($permission, $user->active_campus_id);
    }

    public function payload(Request $request, array $filters, bool $preview = false): array
    {
        $year = (int) ($filters['academic_year_id'] ?? 0);
        $settings = $year ? K3CertificateYear::where('academic_year_id', $year)->first() : null;
        $template = K3CertificateTemplate::find(1);
        $all = $year ? $this->rows($request, $year) : collect();
        $campusStudents = $all->filter(fn ($row) => empty($filters['campus_id']) || $row->campus_id == $filters['campus_id'])->values();
        $classes = $campusStudents->unique(fn ($row) => $row->grade_id.':'.$row->class_id)
            ->map(fn ($row) => ['value' => $row->grade_id.':'.$row->class_id, 'label' => 'K3-'.ltrim($row->class_name, '-')])->values();
        $students = $campusStudents->filter(fn ($row) => empty($filters['grade_class']) || $row->grade_id.':'.$row->class_id === $filters['grade_class'])->values();
        $options = $students;
        if (!empty($filters['certificate_student_id'])) $students = $students->where('student_id', (int) $filters['certificate_student_id'])->values();
        $issued = $settings ? $settings->certificates()->get(['student_id', 'certificate_number', 'qr_token'])->keyBy('student_id') : collect();
        $numbers = $issued->pluck('certificate_number', 'student_id');
        $showQr = filter_var($filters['certificate_show_qr'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $filters['certificate_show_qr'] = $showQr ? '1' : '0';
        $faviconPath = (($preview || $showQr) && $issued->isNotEmpty()) ? (BrandingSetting::current()->favicon_path ?? '') : '';
        foreach ($students as $index => $student) {
            $certificate = $issued->get($student->student_id);
            $student->certificate_number = $certificate?->certificate_number;
            $student->certificate_verification_url = $certificate?->qr_token ? K3CertificateQr::url($certificate->qr_token) : null;
            $verificationAddress = $student->certificate_verification_url ? parse_url($student->certificate_verification_url) : [];
            $student->certificate_verification_site = $student->certificate_verification_url
                ? $verificationAddress['host'].(isset($verificationAddress['port']) ? ':'.$verificationAddress['port'] : '')
                : null;
            $student->certificate_qr_image = $certificate?->qr_token && (($preview && $index === 0) || (!$preview && $showQr))
                ? K3CertificateQr::image($certificate->qr_token, $faviconPath) : null;
        }
        $filters['given_date'] = $settings?->given_date?->toDateString() ?? '';
        $filters['number_prefix'] = $settings?->number_prefix ?? '';

        $previewRows = $students;
        $pagination = null;
        if ($preview) {
            $pageSize = (string) ($filters['preview_page_size'] ?? self::PREVIEW_DEFAULT_SIZE);
            if (!in_array($pageSize, self::PREVIEW_SIZES, true)) $pageSize = (string) self::PREVIEW_DEFAULT_SIZE;
            $total = $students->count();
            $lastPage = $pageSize === 'all' ? 1 : max(1, (int) ceil($total / (int) $pageSize));
            $page = $pageSize === 'all' ? 1 : min($lastPage, max(1, (int) ($filters['preview_page'] ?? 1)));
            $offset = $pageSize === 'all' ? 0 : ($page - 1) * (int) $pageSize;
            $previewRows = $pageSize === 'all' ? $students : $students->slice($offset, (int) $pageSize)->values();
            $filters['preview_page_size'] = $pageSize;
            $filters['preview_page'] = $page;
            $pagination = [
                'page' => $page, 'pageSize' => $pageSize,
                'perPage' => $pageSize === 'all' ? $total : (int) $pageSize,
                'total' => $total, 'lastPage' => $lastPage,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => $offset + $previewRows->count(), 'sizes' => self::PREVIEW_SIZES,
            ];
        }

        $canSaveGivenDate = $year && $this->canPerformAction($request, 'save_date');
        $canAssignNumbers = $year && $this->canPerformAction($request, 'assign');
        $canEditPrefix = $year && $this->canPerformAction($request, 'update_prefix');
        return [
            'isReportStub' => false, 'filters' => $filters, 'enrollments' => collect(),
            'certificates' => $students, 'certificateSettings' => $settings,
            'certificatePreviewRows' => $previewRows, 'certificatePagination' => $pagination,
            'certificateLayout'=>K3CertificateLayout::resolve($template?->layout,$settings?->typography),
            'certificateTemplateVersion'=>$template?->version??0,
            'canManageCertificateTemplate'=>$this->canManageTemplate($request),
            'certificateClasses' => $classes, 'certificateStudents' => $options,
            'canSaveGivenDate' => $canSaveGivenDate,
            'canAssignCertificateNumbers' => $canAssignNumbers,
            'canEditCertificatePrefix' => $canEditPrefix,
            'canManageCertificates' => $canSaveGivenDate || $canAssignNumbers || $canEditPrefix,
            'certificateNumbersAssigned' => $numbers->isNotEmpty(),
            'certificateShowQr' => $showQr,
            'certificateReady' => $settings && $students->isNotEmpty() && $students->every(fn ($row) => filled($row->certificate_number)),
            'hasDataFilter' => (bool) $year, 'hasMorePreviewRows' => false,
        ];
    }

    public function save(Request $request, array $data): int
    {
        abort_unless($this->canPerformAction($request, $data['action']), 403, 'You do not have permission for this K3 certificate action.');
        $year = (int) $data['academic_year_id'];
        return DB::transaction(function () use ($data, $year, $request) {
            // Serialize even the first assignment, before the settings row exists.
            DB::table('tb_academic_year')->where('id', $year)->lockForUpdate()->first();
            $settings = K3CertificateYear::where('academic_year_id', $year)->first();
            if (in_array($data['action'], ['save_style', 'reset_style'], true)) {
                if (!$settings) {
                    throw ValidationException::withMessages(['typography'=>'Save the Given Date and Certificate Prefix before customizing fonts.']);
                }
                $settings->update(['typography'=>$data['action'] === 'reset_style' ? null : K3CertificateTypography::resolve($data['typography'])]);
                return 0;
            }
            if ($data['action'] === 'update_prefix') {
                if (!$settings) {
                    throw ValidationException::withMessages(['number_prefix' => 'Save the Given Date before editing the certificate prefix.']);
                }
                if ($settings->number_prefix === $data['number_prefix']) return 0;
                $certificates = $settings->certificates()->orderBy('sequence')->get();
                // Stage distinct, invalid-as-public numbers to avoid unique-key collisions
                // when a numeric prefix overlaps the suffix of an existing number.
                foreach ($certificates as $certificate) {
                    $certificate->update(['certificate_number' => '@'.$certificate->id]);
                }
                foreach ($certificates as $certificate) {
                    $certificate->update(['certificate_number' => $data['number_prefix'].str_pad((string) $certificate->sequence, 3, '0', STR_PAD_LEFT)]);
                }
                $settings->update(['number_prefix' => $data['number_prefix']]);
                return $certificates->count();
            }
            $givenDate = $data['given_date'] ?? $settings?->given_date?->toDateString();
            $prefix = $data['number_prefix'] ?? $settings?->number_prefix ?? '';
            if (!$givenDate) throw ValidationException::withMessages(['given_date' => 'Save the Given Date before assigning certificate numbers.']);
            $givenDate = Carbon::parse($givenDate)->toDateString();
            if ($givenDate !== $settings?->given_date?->toDateString()) {
                abort_unless($this->canPerformAction($request, 'save_date'), 403, 'You do not have permission to change the Given Date.');
            }
            if ($prefix !== ($settings?->number_prefix ?? '')) {
                abort_unless($this->canPerformAction($request, 'update_prefix'), 403, 'You do not have permission to edit the certificate prefix.');
            }
            if ($data['action'] === 'assign' && blank($prefix)) throw ValidationException::withMessages(['number_prefix' => 'Save the certificate prefix before assigning certificate numbers.']);
            if ($settings && $settings->number_prefix !== $prefix && $settings->certificates()->exists()) {
                throw ValidationException::withMessages(['number_prefix' => 'Use Update Prefix to correct the prefix of existing certificate numbers.']);
            }
            $settings = K3CertificateYear::updateOrCreate(['academic_year_id' => $year], [
                'given_date' => $givenDate, 'number_prefix' => $prefix,
            ]);
            if ($data['action'] !== 'assign') return 0;
            $rows = $this->query($year)->get()->unique('student_id')->values();
            if ($rows->isEmpty()) throw ValidationException::withMessages(['academic_year_id' => 'No eligible K3 students were found for this academic year.']);
            if ($rows->contains(fn ($row) => blank($row->full_name_en))) {
                throw ValidationException::withMessages(['academic_year_id' => 'Every K3 student must have an English name before assigning certificate numbers.']);
            }
            $assigned = $settings->certificates()->pluck('student_id');
            $sequence = (int) ($settings->certificates()->max('sequence') ?? 0);
            $count = 0;
            foreach ($rows as $row) {
                if ($assigned->contains($row->student_id)) continue;
                $sequence++;
                K3Certificate::create([
                    'certificate_year_id' => $settings->id, 'student_id' => $row->student_id,
                    'enrollment_id' => $row->enrollment_id, 'sequence' => $sequence,
                    'certificate_number' => $settings->number_prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                ]);
                $count++;
            }
            return $count;
        });
    }

    public function canManageTemplate(Request $request): bool
    {
        return $this->canPerformAction($request, 'save_template');
    }

    public function saveTemplate(Request $request, array $layout, int $version): void
    {
        abort_unless($this->canManageTemplate($request),403,'You do not have permission to edit the K3 certificate template.');
        DB::transaction(function() use ($layout,$version) {
            $template = K3CertificateTemplate::whereKey(1)->lockForUpdate()->firstOrFail();
            if ($template->version !== $version) throw ValidationException::withMessages(['template_data'=>'The template was updated by another user. Refresh the report before editing again.']);
            $template->update(['layout'=>K3CertificateLayout::resolve($layout),'version'=>$version+1]);
        });
    }
}
