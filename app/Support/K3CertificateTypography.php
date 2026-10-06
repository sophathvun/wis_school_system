<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class K3CertificateTypography
{
    public static function fonts(): array
    {
        return [
            'old_english' => ['label'=>'Old English Text MT', 'family'=>'K3 Old English Text MT', 'directory'=>'certificates', 'file'=>'OldEnglishTextMT.ttf'],
            'maiandra' => ['label'=>'Maiandra GD', 'family'=>'K3 Maiandra GD', 'directory'=>'certificates', 'file'=>'MaiandraGD.ttf'],
            'siemreap' => ['label'=>'Khmer OS Siemreap', 'family'=>'K3 Siemreap', 'file'=>'KhmerOSsiemreap.ttf'],
            'battambang' => ['label'=>'Khmer OS Battambang', 'family'=>'K3 Battambang', 'file'=>'KhmerOSbattambang.ttf'],
            'muol_light' => ['label'=>'Khmer OS Muol Light', 'family'=>'K3 Muol Light', 'file'=>'KhmerOSmuollight.ttf'],
            'arial' => ['label'=>'Arial / Helvetica', 'family'=>'Arial, sans-serif', 'pdf'=>'Helvetica, sans-serif'],
            'times' => ['label'=>'Times New Roman', 'family'=>'Times New Roman, Times, serif', 'pdf'=>'Times, serif'],
        ];
    }

    public static function fields(): array
    {
        return [
            'heading'=>['label'=>'Heading: This certifies that', 'class'=>'k3-certifies', 'font'=>'times', 'size'=>16, 'color'=>'#000000'],
            'student_name'=>['label'=>'Student Name', 'class'=>'k3-student-name', 'font'=>'siemreap', 'size'=>23, 'color'=>'#305b93'],
            'completion'=>['label'=>'Completion Text', 'class'=>'k3-completion', 'font'=>'siemreap', 'size'=>14, 'color'=>'#000000'],
            'given_date'=>['label'=>'Given Date', 'class'=>'k3-given-date', 'font'=>'siemreap', 'size'=>14, 'color'=>'#000000'],
            'chairman'=>['label'=>'Chairman', 'class'=>'k3-chairman', 'font'=>'arial', 'size'=>12, 'color'=>'#000000'],
            'director'=>['label'=>'International Program Director', 'class'=>'k3-director', 'font'=>'arial', 'size'=>12, 'color'=>'#000000'],
            'class_campus'=>['label'=>'Class / Campus', 'class'=>'k3-class-campus', 'font'=>'arial', 'size'=>9, 'color'=>'#000000'],
            'number'=>['label'=>'Certificate Number', 'class'=>'k3-number', 'font'=>'arial', 'size'=>9, 'color'=>'#000000'],
        ];
    }

    public static function resolve(?array $saved): array
    {
        $styles = [];
        foreach (self::fields() as $key=>$field) {
            $value = $saved[$key] ?? [];
            $styles[$key] = [
                'font'=>isset(self::fonts()[$value['font'] ?? '']) ? $value['font'] : $field['font'],
                'size'=>is_numeric($value['size'] ?? null) ? max(6, min(36, (float)$value['size'])) : $field['size'],
                'color'=>preg_match('/^#[0-9a-fA-F]{6}$/', $value['color'] ?? '') ? $value['color'] : $field['color'],
            ];
        }
        return $styles;
    }

    public static function rules(): array
    {
        $rules = ['typography'=>['required', 'array:'.implode(',', array_keys(self::fields()))]];
        foreach (self::fields() as $key=>$field) {
            $rules["typography.$key"] = ['required','array:font,size,color'];
            $rules["typography.$key.font"] = ['required', Rule::in(array_keys(self::fonts()))];
            $rules["typography.$key.size"] = ['required','numeric','between:6,36'];
            $rules["typography.$key.color"] = ['required','regex:/^#[0-9a-fA-F]{6}$/'];
        }
        return $rules;
    }

    public static function family(string $font, bool $pdf): string
    {
        $definition = self::fonts()[$font];
        return $pdf ? ($definition['pdf'] ?? $definition['family']) : $definition['family'];
    }

    public static function nameSize(string $name, array $style, float $widthPercent = 72): float
    {
        $size = (float)$style['size'];
        $font = self::fonts()[$style['font']];
        $file = $font['file'] ?? null;
        if ($file && function_exists('imagettfbbox') && $name !== '') {
            $bounds = imagettfbbox($size, 0, public_path(self::fontPath($font)), $name);
            return min($size, $size * 785 * $widthPercent / 72 / max(1, abs($bounds[2] - $bounds[0])));
        }
        return $size; // Browser and PDF fitting use the selected font's actual metrics.
    }

    public static function fontPath(array $font): string
    {
        return 'fonts/'.($font['directory'] ?? 'khmer').'/'.$font['file'];
    }
}
