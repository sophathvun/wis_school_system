<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Validation\Rule;

class StudentIdCardLayout
{
    public const WIDTH = 54;
    public const HEIGHT = 90;
    public const EDIT_PERMISSION = 'student-id-card-qr.edit-template';

    public static function fonts(): array
    {
        return [
            'arial' => ['label' => 'Arial / Helvetica', 'family' => 'Arial, Helvetica, sans-serif'],
            'times' => ['label' => 'Times New Roman', 'family' => '"Times New Roman", Times, serif'],
            'siemreap' => ['label' => 'Khmer OS Siemreap', 'family' => '"Khmer OS Siemreap", "Noto Sans Khmer", sans-serif'],
            'battambang' => ['label' => 'Khmer OS Battambang', 'family' => '"Khmer OS Battambang", "Noto Sans Khmer", sans-serif'],
            'muol_light' => ['label' => 'Khmer OS Muol Light', 'family' => '"Khmer OS Muol Light", "Noto Sans Khmer", sans-serif'],
        ];
    }

    public static function tokens(): array
    {
        return [
            'student_name' => 'Student Name', 'student_name_en' => 'Student Name (English)',
            'student_name_kh' => 'Student Name (Khmer)', 'student_id' => 'Student ID',
            'phone' => 'Telephone', 'grade' => 'Grade', 'class' => 'Grade / Class',
            'campus' => 'Campus', 'academic_year' => 'Academic Year',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender',
        ];
    }

    public static function textField(string $label, string $text, float $x, float $y, float $width, float $height, float $size = 8): array
    {
        return compact('label', 'text', 'x', 'y', 'width', 'height', 'size') + [
            'type' => 'text', 'font' => 'arial', 'color' => '#203a5f', 'align' => 'center',
            'bold' => false, 'visible' => true,
        ];
    }

    public static function defaults(): array
    {
        return ['fields' => [
            'logo' => ['label' => 'School Logo', 'type' => 'logo', 'x' => 6, 'y' => 3, 'width' => 42, 'height' => 16, 'visible' => true],
            'photo' => ['label' => 'Student Photo', 'type' => 'photo', 'x' => 16, 'y' => 22, 'width' => 22, 'height' => 22, 'visible' => true],
            'student_name' => array_replace(self::textField('Student Name', '{{student_name}}', 3, 47, 48, 8, 9), ['bold' => true]),
            'student_id' => array_replace(self::textField('Student ID', '{{student_id}}', 3, 57, 48, 5, 10), ['color' => '#60758f', 'bold' => true]),
            'phone' => array_replace(self::textField('Telephone', '{{phone}}', 3, 63, 48, 5), ['color' => '#2f6fa8', 'bold' => true]),
            'qr' => ['label' => 'QR Code', 'type' => 'qr', 'x' => 18, 'y' => 70, 'width' => 18, 'height' => 18, 'visible' => true],
            'scan' => array_replace(self::textField('QR Caption', 'SCAN', 3, 88, 48, 2, 4), ['bold' => true]),
        ]];
    }

    public static function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:40', function ($attribute, $value, $fail) {
                $media = [];
                foreach ($value as $key => $field) {
                    if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/', (string) $key)) {
                        $fail('Invalid card field identifier.');
                    }
                    if (($field['type'] ?? '') !== 'text' && isset($field['type'])) {
                        if (in_array($field['type'], $media, true)) $fail('Add only one logo, photo, and QR code.');
                        $media[] = $field['type'];
                    }
                }
            }],
            'fields.*' => ['required', 'array:label,type,text,x,y,width,height,font,size,color,align,bold,visible'],
            'fields.*.label' => ['required', 'string', 'max:80'],
            'fields.*.type' => ['required', 'in:text,logo,photo,qr'],
            'fields.*.x' => ['required', 'numeric', 'between:0,53'],
            'fields.*.y' => ['required', 'numeric', 'between:0,89'],
            'fields.*.width' => ['required', 'numeric', 'between:1,54'],
            'fields.*.height' => ['required', 'numeric', 'between:1,90'],
            'fields.*.visible' => ['required', 'boolean'],
            'fields.*.text' => ['required_if:fields.*.type,text', 'nullable', 'string', 'max:1000', function ($attribute, $value, $fail) {
                preg_match_all('/\{\{([^{}]+)\}\}/', $value ?? '', $matches);
                foreach ($matches[1] as $token) {
                    if (!array_key_exists($token, self::tokens())) $fail('Unknown student placeholder: ' . $token);
                }
            }],
            'fields.*.font' => ['required_if:fields.*.type,text', Rule::in(array_keys(self::fonts()))],
            'fields.*.size' => ['required_if:fields.*.type,text', 'numeric', 'between:4,40'],
            'fields.*.color' => ['required_if:fields.*.type,text', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fields.*.align' => ['required_if:fields.*.type,text', 'in:left,center,right'],
            'fields.*.bold' => ['required_if:fields.*.type,text', 'boolean'],
        ];
    }

    public static function resolve(?array $layout): array
    {
        if (!is_array($layout['fields'] ?? null)) return self::defaults();
        $fields = [];
        foreach ($layout['fields'] as $key => $field) {
            $width = round(max(1, min(self::WIDTH, (float) $field['width'])), 1);
            $height = $field['type'] === 'qr' ? $width : round(max(1, min(self::HEIGHT, (float) $field['height'])), 1);
            $fields[$key] = array_replace($field, [
                'width' => $width, 'height' => $height,
                'x' => round(max(0, min(self::WIDTH - $width, (float) $field['x'])), 1),
                'y' => round(max(0, min(self::HEIGHT - $height, (float) $field['y'])), 1),
                'visible' => (bool) $field['visible'],
            ]);
        }
        return ['fields' => $fields];
    }

    public static function values(?Student $student, ?StudentEnrollment $enrollment): array
    {
        $grade = rtrim(trim((string) ($enrollment?->grade?->grade_short_name ?: $enrollment?->grade?->grade)), '- ');
        $class = trim((string) ($enrollment?->schoolClass?->class_name ?? ''));
        $classLabel = $class !== '' && $grade !== '' && str_starts_with(strtolower($class), strtolower($grade))
            ? $class : $grade . (preg_match('/^(N|K[123])$/i', $grade) ? '-' : '') . $class;

        return [
            'student_name' => $student ? ($student->full_name_en ?: $student->full_name_kh) : 'STUDENT NAME',
            'student_name_en' => $student?->full_name_en ?? 'STUDENT NAME',
            'student_name_kh' => $student?->full_name_kh ?? '',
            'student_id' => $student ? ($student->student_id ?: $student->student_no) : 'STUDENT ID',
            'phone' => $student ? PhoneNumber::formatCambodian($student->home_phone) : '012 345 678',
            'grade' => $grade, 'class' => $enrollment ? $classLabel : '3A',
            'campus' => $enrollment?->campus?->campus_name_en ?? '',
            'academic_year' => $enrollment?->academicYear?->academic_year ?? '',
            'date_of_birth' => $student?->date_of_birth?->format('d-M-Y') ?? '',
            'gender' => $student?->gender ?? '',
        ];
    }

    public static function text(string $text, array $values): string
    {
        return preg_replace_callback('/\{\{([^{}]+)\}\}/', fn ($match) => $values[$match[1]] ?? $match[0], $text);
    }

    public static function style(array $field): string
    {
        $style = 'left:' . ($field['x'] / self::WIDTH * 100) . '%;top:' . ($field['y'] / self::HEIGHT * 100)
            . '%;width:' . ($field['width'] / self::WIDTH * 100) . '%;height:' . ($field['height'] / self::HEIGHT * 100) . '%;';
        if ($field['type'] === 'text') {
            $style .= 'font-family:' . self::fonts()[$field['font']]['family'] . ';font-size:calc(var(--id-card-scale,1) * '
                . $field['size'] . 'pt);color:' . $field['color'] . ';text-align:' . $field['align']
                . ';font-weight:' . ($field['bold'] ? '700' : '400') . ';';
        }
        return $style;
    }
}
