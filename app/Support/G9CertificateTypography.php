<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class G9CertificateTypography
{
    public static function fonts(): array
    {
        return [
            'old_english' => ['label'=>'Old English Text MT', 'family'=>'G9 Old English Text MT', 'directory'=>'certificates', 'file'=>'OldEnglishTextMT.ttf'],
            'maiandra' => ['label'=>'Maiandra GD', 'family'=>'G9 Maiandra GD', 'directory'=>'certificates', 'file'=>'MaiandraGD.ttf'],
            'siemreap' => ['label'=>'Khmer OS Siemreap', 'family'=>'G9 Siemreap', 'file'=>'KhmerOSsiemreap.ttf'],
            'battambang' => ['label'=>'Khmer OS Battambang', 'family'=>'G9 Battambang', 'file'=>'KhmerOSbattambang.ttf'],
            'muol_light' => ['label'=>'Khmer OS Muol Light', 'family'=>'G9 Muol Light', 'file'=>'KhmerOSmuollight.ttf'],
            'arial' => ['label'=>'Arial / Helvetica', 'family'=>'Arial, sans-serif', 'pdf'=>'Helvetica, sans-serif'],
            'times' => ['label'=>'Times New Roman', 'family'=>'Times New Roman, Times, serif', 'pdf'=>'Times, serif'],
        ];
    }

    public static function fields(): array
    {
        return [
            'title'=>['label'=>'Junior High School Diploma', 'class'=>'g9-title', 'font'=>'old_english', 'size'=>36, 'color'=>'#ff0000'],
            'heading'=>['label'=>'Heading: This certifies that', 'class'=>'g9-certifies', 'font'=>'old_english', 'size'=>18, 'color'=>'#000000'],
            'student_name'=>['label'=>'Student Name', 'class'=>'g9-student-name', 'font'=>'maiandra', 'size'=>24, 'color'=>'#305b93'],
            'completion'=>['label'=>'Completion Text', 'class'=>'g9-completion', 'font'=>'arial', 'size'=>18, 'color'=>'#000000'],
            'diploma'=>['label'=>'Diploma', 'class'=>'g9-diploma', 'font'=>'old_english', 'size'=>36, 'color'=>'#ff0000'],
            'given_date'=>['label'=>'Given Date', 'class'=>'g9-given-date', 'font'=>'arial', 'size'=>18, 'color'=>'#000000'],
            'chairman'=>['label'=>'Chairman', 'class'=>'g9-chairman', 'font'=>'arial', 'size'=>12, 'color'=>'#000000'],
            'director'=>['label'=>'Registrar Director', 'class'=>'g9-director', 'font'=>'arial', 'size'=>12, 'color'=>'#000000'],
            'class_campus'=>['label'=>'Class / Campus', 'class'=>'g9-class-campus', 'font'=>'arial', 'size'=>9, 'color'=>'#000000'],
            'number'=>['label'=>'Certificate Number', 'class'=>'g9-number', 'font'=>'arial', 'size'=>9, 'color'=>'#000000'],
        ];
    }

    public static function resolve(?array $saved): array
    {
        $styles = [];
        foreach (self::fields() as $key=>$field) {
            $value = $saved[$key] ?? [];
            $styles[$key] = [
                'font'=>isset(self::fonts()[$value['font'] ?? '']) ? $value['font'] : $field['font'],
                'size'=>is_numeric($value['size'] ?? null) ? max(6, min(60, (float)$value['size'])) : $field['size'],
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
            $rules["typography.$key.size"] = ['required','numeric','between:6,60'];
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
