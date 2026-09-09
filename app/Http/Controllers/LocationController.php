<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Country;
use App\Models\District;
use App\Models\Province;
use App\Models\BrandingSetting;
use App\Models\SchoolInfo;
use App\Models\Village;
use Illuminate\Http\Request;
use App\Support\SettingsQuery;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class LocationController
{
    private array $levels = [
        'country' => [Country::class, 'country_name_en', 'country_name_kh', null],
        'province' => [Province::class, 'province_name_en', 'province_name_kh', 'country_id'],
        'district' => [District::class, 'district_name_en', 'district_name_kh', 'province_id'],
        'commune' => [Commune::class, 'commune_name_en', 'commune_name_kh', 'district_id'],
        'village' => [Village::class, 'village_name_en', 'village_name_kh', 'commune_id'],
    ];

    public function index() { return view('locations'); }

    private function whereLocationName($query, string $en, string $kh, string $search): void
    {
        $query->where($en, 'like', "%{$search}%")
            ->orWhere($kh, 'like', "%{$search}%");
    }

    private function applySearch($query, string $level, string $en, ?string $kh, string $search): void
    {
        $query->where(function ($sub) use ($level, $en, $kh, $search) {
            $sub->where($en, 'like', "%{$search}%");
            if ($kh) {
                $sub->orWhere($kh, 'like', "%{$search}%");
            }

            if ($level === 'country') {
                $sub->orWhere('nationality_name_en', 'like', "%{$search}%")
                    ->orWhere('nationality_name_kh', 'like', "%{$search}%")
                    ->orWhere('country_code', 'like', "%{$search}%")
                    ->orWhere('international_phone_code', 'like', "%{$search}%");
            }

            if ($level === 'province') {
                $sub->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
            }

            if ($level === 'district') {
                $sub->orWhereHas('province', function ($province) use ($search) {
                    $this->whereLocationName($province, 'province_name_en', 'province_name_kh', $search);
                    $province->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
                });
            }

            if ($level === 'commune') {
                $sub->orWhereHas('district', function ($district) use ($search) {
                    $this->whereLocationName($district, 'district_name_en', 'district_name_kh', $search);
                    $district->orWhereHas('province', function ($province) use ($search) {
                        $this->whereLocationName($province, 'province_name_en', 'province_name_kh', $search);
                        $province->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
                    });
                });
            }

            if ($level === 'village') {
                $sub->orWhereHas('commune', function ($commune) use ($search) {
                    $this->whereLocationName($commune, 'commune_name_en', 'commune_name_kh', $search);
                    $commune->orWhereHas('district', function ($district) use ($search) {
                        $this->whereLocationName($district, 'district_name_en', 'district_name_kh', $search);
                        $district->orWhereHas('province', function ($province) use ($search) {
                            $this->whereLocationName($province, 'province_name_en', 'province_name_kh', $search);
                            $province->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
                        });
                    });
                });
            }
        });
    }

    public function options(Request $request)
    {
        $map = [
            'countries' => Country::where('status', 1)->orderBy('country_name_en')->get(['id','country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','country_code','international_phone_code','flag_path']),
            'provinces' => Province::where('status', 1)->when($request->country_id, fn($q, $id) => $q->where('country_id', $id))->orderBy('province_name_en')->get(['id','country_id','province_name_en','province_name_kh']),
            'districts' => District::where('status', 1)->when($request->province_id, fn($q, $id) => $q->where('province_id', $id))->orderBy('district_name_en')->get(['id','province_id','district_name_en','district_name_kh']),
            'communes' => Commune::where('status', 1)->when($request->district_id, fn($q, $id) => $q->where('district_id', $id))->orderBy('commune_name_en')->get(['id','district_id','commune_name_en','commune_name_kh']),
            'villages' => Village::where('status', 1)->when($request->commune_id, fn($q, $id) => $q->where('commune_id', $id))->orderBy('village_name_en')->get(['id','commune_id','village_name_en','village_name_kh']),
        ];
        return response()->json($map);
    }

    public function fetch(Request $request)
    {
        abort_unless(isset($this->levels[$request->level]), 404);
        [$model, $en, $kh, $parent] = $this->levels[$request->level];
        $query = $model::query();
        if ($request->filled('search')) {
            $this->applySearch($query, $request->level, $en, $kh, $request->search);
        }
        $this->applyHierarchyFilters($query, $request->level, $request);
        if ($parent && $request->parent_id) $query->where($parent, $request->parent_id);
        $sortBy = $request->get('sortBy', 'name');
        $sortDir = $request->get('sortDir', 'asc') === 'desc' ? 'desc' : 'asc';
        if ($request->level === 'country') {
            $countrySortColumn = match ($sortBy) {
                'nationality' => 'nationality_name_en',
                'country_code' => 'country_code',
                'international_phone_code' => 'international_phone_code',
                'status' => 'status',
                default => 'country_name_en',
            };
            $query->orderBy($countrySortColumn, $sortDir);
        } else {
            $relation = match ($parent) { 'country_id' => ['country', 'tb_country', 'country_name_en'], 'province_id' => ['province', 'tb_province', 'province_name_en'], 'district_id' => ['district', 'tb_district', 'district_name_en'], default => ['commune', 'tb_commune', 'commune_name_en'] };
            $query->with(match ($request->level) { 'district' => 'province.country', 'commune' => 'district.province.country', 'village' => 'commune.district.province.country', default => $relation[0] });
            if ($request->level === 'village') {
                $table = $model::make()->getTable();
                $needsLocationJoin = $request->filled('country_id')
                    || $request->filled('province_id')
                    || $request->filled('district_id')
                    || $request->filled('commune_id')
                    || in_array($sortBy, ['parent', 'district', 'province', 'country'], true);
                if ($needsLocationJoin) {
                    $query->join('tb_commune', "{$table}.commune_id", '=', 'tb_commune.id')
                        ->join('tb_district', 'tb_commune.district_id', '=', 'tb_district.id')
                        ->join('tb_province', 'tb_district.province_id', '=', 'tb_province.id')
                        ->join('tb_country', 'tb_province.country_id', '=', 'tb_country.id')
                        ->select("{$table}.*");
                    if ($request->filled('commune_id')) $query->where('tb_commune.id', $request->integer('commune_id'));
                    if ($request->filled('district_id')) $query->where('tb_district.id', $request->integer('district_id'));
                    if ($request->filled('province_id')) $query->where('tb_province.id', $request->integer('province_id'));
                    if ($request->filled('country_id')) $query->where('tb_country.id', $request->integer('country_id'));
                }
                if ($sortBy === 'parent') {
                    $query->orderBy('tb_commune.commune_name_en', $sortDir);
                } elseif ($sortBy === 'district') {
                    $query->orderBy('tb_district.district_name_en', $sortDir);
                } elseif ($sortBy === 'province') {
                    $query->orderBy('tb_province.province_name_en', $sortDir);
                } elseif ($sortBy === 'country') {
                    $query->orderBy('tb_country.country_name_en', $sortDir);
                } else {
                    $query->orderBy($en, $sortDir);
                }
            } elseif ($sortBy === 'parent') {
                $table = $model::make()->getTable();
                $query->join($relation[1], "{$table}.{$parent}", '=', "{$relation[1]}.id")->select("{$table}.*")->orderBy($relation[1] . '.' . $relation[2], $sortDir);
            } elseif ($sortBy === 'country' && in_array($request->level, ['district', 'commune'], true)) {
                $table = $model::make()->getTable();
                if ($request->level === 'district') {
                    $query->join('tb_province', "{$table}.province_id", '=', 'tb_province.id')->join('tb_country', 'tb_province.country_id', '=', 'tb_country.id');
                } else {
                    $query->join('tb_district', "{$table}.district_id", '=', 'tb_district.id')->join('tb_province', 'tb_district.province_id', '=', 'tb_province.id')->join('tb_country', 'tb_province.country_id', '=', 'tb_country.id');
                }
                $query->select("{$table}.*")->orderBy('tb_country.country_name_en', $sortDir);
            } else {
                $query->orderBy($en, $sortDir);
            }
        }
        return response()->json($query->paginate(SettingsQuery::perPage($request)));
    }

    public function print(Request $request)
    {
        $level = $request->get('level', 'country');
        abort_unless(isset($this->levels[$level]), 404);

        $rows = $this->locationReportRows($request, $level);

        return view('locations-pdf', [
            'level' => $level,
            'levelLabel' => $this->levelLabel($level),
            'rows' => $rows,
            'tableRows' => $rows->values()->map(fn ($row, $index) => $this->locationReportCells($row, $level, $index + 1)),
            'headers' => $this->locationReportHeaders($level),
            'logoData' => $this->locationReportLogoDataUri(),
            'printMode' => true,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $level = $request->get('level', 'country');
        abort_unless(isset($this->levels[$level]), 404);

        $filename = 'location-list-' . $level . '-' . now()->format('Ymd-His') . '.xlsx';
        $path = $this->locationExcelPath($this->locationReportRows($request, $level), $level);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function locationReportRows(Request $request, string $level)
    {
        [$model, $en, $kh] = $this->levels[$level];
        $query = $model::query();

        if ($request->filled('search')) {
            $this->applySearch($query, $level, $en, $kh, $request->search);
        }

        $this->applyHierarchyFilters($query, $level, $request);

        $query->with(match ($level) {
            'province' => 'country',
            'district' => 'province.country',
            'commune' => 'district.province.country',
            'village' => 'commune.district.province.country',
            default => [],
        });

        return $query->orderBy($en)->get();
    }

    private function levelLabel(string $level): string
    {
        return match ($level) {
            'province' => 'Province / City',
            'district' => 'District / Khan',
            'commune' => 'Commune',
            'village' => 'Village',
            default => 'Country',
        };
    }

    private function locationReportLogoPath(): ?string
    {
        $school = SchoolInfo::latest('id')->first();
        $branding = BrandingSetting::current();
        $logoPath = $branding?->report_logo_1_path
            ? storage_path('app/public/' . ltrim($branding->report_logo_1_path, '/'))
            : ($school?->logo_path
                ? storage_path('app/public/' . ltrim($school->logo_path, '/'))
                : storage_path('app/public/school_logo/wis_logo.png'));

        if (!is_file($logoPath)) {
            $logoPath = storage_path('app/public/school_logo/wis_logo.png');
        }

        return is_file($logoPath) ? $logoPath : null;
    }

    private function locationReportLogoDataUri(): ?string
    {
        $logoPath = $this->locationReportLogoPath();

        if (!$logoPath) {
            return null;
        }

        return 'data:' . (mime_content_type($logoPath) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function locationExcelPath($rows, string $level): string
    {
        $path = tempnam(sys_get_temp_dir(), 'location-list-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes(),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->locationWorksheetXml($rows, $level),
        ];

        $this->writeZipArchive($xlsxPath, $entries);

        return $xlsxPath;
    }

    private function locationWorksheetXml($rows, string $level): string
    {
        $headers = $this->locationReportHeaders($level);
        $lastColumn = chr(64 + count($headers));
        $lastRow = $rows->count() + 6;
        $sheetRows = [
            $this->xlsxRow(1, [['A', 'បញ្ជីឈ្មោះប្រទេស', 1]], 30),
            $this->xlsxRow(2, [['A', $this->levelLabel($level) . ' List', 2]], 24),
            $this->xlsxRow(3, [['A', 'Generated: ' . now()->format('d-M-Y h:i A'), 6]], 20),
            $this->xlsxRow(4, [], 8),
            $this->xlsxRow(5, collect($headers)->map(fn ($header, $index) => [chr(65 + $index), $header, 3])->all(), 24),
        ];

        foreach ($rows as $index => $row) {
            $cells = $this->locationReportCells($row, $level, $index + 1);
            $sheetRows[] = $this->xlsxRow(
                $index + 6,
                collect($cells)->map(fn ($cell, $cellIndex) => [chr(65 + $cellIndex), $cell, str_contains($headers[$cellIndex] ?? '', 'Khmer') ? 4 : 5])->all(),
                22,
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="' . count($headers) . '" width="22" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<mergeCells count="4"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A2:' . $lastColumn . '2"/><mergeCell ref="A3:' . $lastColumn . '3"/><mergeCell ref="A4:' . $lastColumn . '4"/></mergeCells>'
            . '</worksheet>';
    }

    private function locationReportHeaders(string $level): array
    {
        $base = ['No.', $this->levelLabel($level) . ' (English)', $this->levelLabel($level) . ' (Khmer)'];

        return match ($level) {
            'country' => [...$base, 'Nationality (English)', 'Nationality (Khmer)', 'Country Code', 'International Phone Code', 'Status'],
            'province' => [...$base, 'Country (English)', 'Country (Khmer)', 'Status'],
            'district' => [...$base, 'Province / City (English)', 'Province / City (Khmer)', 'Country (English)', 'Country (Khmer)', 'Status'],
            'commune' => [...$base, 'District / Khan (English)', 'District / Khan (Khmer)', 'Province / City (English)', 'Province / City (Khmer)', 'Country (English)', 'Country (Khmer)', 'Status'],
            'village' => [...$base, 'Commune (English)', 'Commune (Khmer)', 'District / Khan (English)', 'District / Khan (Khmer)', 'Province / City (English)', 'Province / City (Khmer)', 'Country (English)', 'Country (Khmer)', 'Status'],
            default => $base,
        };
    }

    private function locationReportCells($row, string $level, int $number): array
    {
        $nameEn = $row->{$level . '_name_en'} ?? '';
        $nameKh = $row->{$level . '_name_kh'} ?? '';
        $base = [$number, $nameEn ?: '-', $nameKh ?: '-'];
        $status = $row->status ? 'Active' : 'Inactive';

        return match ($level) {
            'country' => [...$base, $row->nationality_name_en ?: '-', $row->nationality_name_kh ?: '-', $row->country_code ?: '-', $row->international_phone_code ?: '-', $status],
            'province' => [...$base, ...$this->locationNameColumns($row->country, 'country'), $status],
            'district' => [...$base, ...$this->locationNameColumns($row->province, 'province'), ...$this->locationNameColumns($row->province?->country, 'country'), $status],
            'commune' => [...$base, ...$this->locationNameColumns($row->district, 'district'), ...$this->locationNameColumns($row->district?->province, 'province'), ...$this->locationNameColumns($row->district?->province?->country, 'country'), $status],
            'village' => [...$base, ...$this->locationNameColumns($row->commune, 'commune'), ...$this->locationNameColumns($row->commune?->district, 'district'), ...$this->locationNameColumns($row->commune?->district?->province, 'province'), ...$this->locationNameColumns($row->commune?->district?->province?->country, 'country'), $status],
            default => [...$base, $status],
        };
    }

    private function locationNameColumns($row, string $level): array
    {
        if (!$row) {
            return ['-', '-'];
        }

        return [$row->{$level . '_name_en'} ?: '-', $row->{$level . '_name_kh'} ?: '-'];
    }

    private function locationName($row, string $level): string
    {
        if (!$row) {
            return '-';
        }

        return trim(($row->{$level . '_name_kh'} ?: '') . ' ' . ($row->{$level . '_name_en'} ?: '')) ?: '-';
    }

    private function xlsxRow(int $row, array $cells, ?int $height = null): string
    {
        $heightAttribute = $height ? ' ht="' . $height . '" customHeight="1"' : '';

        return '<row r="' . $row . '"' . $heightAttribute . '>' . collect($cells)->map(function ($cell) use ($row) {
            [$column, $value, $style] = $cell;

            return '<c r="' . $column . $row . '" s="' . $style . '" t="inlineStr"><is><t>' . $this->xlsxEscape((string) $value) . '</t></is></c>';
        })->implode('') . '</row>';
    }

    private function xlsxEscape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Location List" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="11"/><name val="Arial"/></font><font><sz val="20"/><name val="Khmer OS Muol Light"/><color rgb="FF4F6380"/></font><font><b/><sz val="12"/><name val="Arial"/></font><font><sz val="11"/><name val="Khmer OS Siemreap"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6F1FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="thin"><color rgb="FF999999"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function writeZipArchive(string $path, array $entries): void
    {
        $file = fopen($path, 'wb');
        $centralDirectory = '';
        $offset = 0;
        $dosTime = $this->zipDosTime();
        $dosDate = $this->zipDosDate();

        foreach ($entries as $name => $contents) {
            $name = str_replace('\\', '/', $name);
            $contents = (string) $contents;
            $size = strlen($contents);
            $crc = crc32($contents);
            $nameLength = strlen($name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0);

            fwrite($file, $localHeader . $name . $contents);
            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $nameLength + $size;
        }

        $centralDirectorySize = strlen($centralDirectory);
        fwrite($file, $centralDirectory);
        fwrite($file, pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralDirectorySize, $offset, 0));
        fclose($file);
    }

    private function zipDosTime(): int
    {
        $time = now();
        return ($time->hour << 11) | ($time->minute << 5) | intdiv($time->second, 2);
    }

    private function zipDosDate(): int
    {
        $time = now();
        return (($time->year - 1980) << 9) | ($time->month << 5) | $time->day;
    }

    private function applyHierarchyFilters($query, string $level, Request $request): void
    {
        if ($level === 'country') {
            return;
        }

        if ($level === 'province') {
            if ($request->filled('country_id')) $query->where('country_id', $request->integer('country_id'));
            if ($request->filled('province_id')) $query->whereKey($request->integer('province_id'));
            return;
        }

        if ($level === 'district') {
            if ($request->filled('district_id')) $query->whereKey($request->integer('district_id'));
            if ($request->filled('province_id')) $query->where('province_id', $request->integer('province_id'));
            if ($request->filled('country_id')) {
                $query->whereHas('province', fn ($province) => $province->where('country_id', $request->integer('country_id')));
            }
            return;
        }

        if ($level === 'commune') {
            if ($request->filled('commune_id')) $query->whereKey($request->integer('commune_id'));
            if ($request->filled('district_id')) $query->where('district_id', $request->integer('district_id'));
            if ($request->filled('province_id') || $request->filled('country_id')) {
                $query->whereHas('district.province', function ($province) use ($request) {
                    if ($request->filled('province_id')) $province->whereKey($request->integer('province_id'));
                    if ($request->filled('country_id')) $province->where('country_id', $request->integer('country_id'));
                });
            }
            return;
        }

        if ($level === 'village') {
            if ($request->filled('village_id')) $query->whereKey($request->integer('village_id'));
            if ($request->filled('commune_id')) $query->where('commune_id', $request->integer('commune_id'));
            if ($request->filled('district_id')) {
                $query->whereHas('commune.district', fn ($district) => $district->whereKey($request->integer('district_id')));
            }
            if ($request->filled('province_id') || $request->filled('country_id')) {
                $query->whereHas('commune.district.province', function ($province) use ($request) {
                    if ($request->filled('province_id')) $province->whereKey($request->integer('province_id'));
                    if ($request->filled('country_id')) $province->where('country_id', $request->integer('country_id'));
                });
            }
        }
    }

    public function save(Request $request)
    {
        abort_unless(isset($this->levels[$request->level]), 404);
        [$model, $en, $kh, $parent] = $this->levels[$request->level];
        $rules = ['name_en' => ['required','string','max:100'], 'name_kh' => ['nullable','string','max:100'], 'status' => ['required','boolean']];
        if ($request->level === 'country') {
            $rules['nationality_name_en'] = ['nullable', 'string', 'max:100'];
            $rules['nationality_name_kh'] = ['nullable', 'string', 'max:100'];
        }
        if ($parent) {
            $parentTable = match ($parent) { 'country_id' => 'tb_country', 'province_id' => 'tb_province', 'district_id' => 'tb_district', default => 'tb_commune' };
            $rules['parent_id'] = ['required', 'integer', "exists:{$parentTable},id"];
        }
        if ($request->level === 'country') {
            $rules['country_code'] = ['nullable','string','max:10'];
            $rules['international_phone_code'] = ['nullable','string','max:20'];
        }
        if ($request->level === 'country') $rules['flag_image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        $data = $request->validate($rules);
        $item = $request->id ? $model::findOrFail($request->id) : new $model();
        $item->{$en} = $data['name_en']; $item->{$kh} = $data['name_kh'] ?? null; $item->status = $data['status'];
        if ($request->level === 'country') {
            $item->nationality_name_en = $data['nationality_name_en'] ?? null;
            $item->nationality_name_kh = $data['nationality_name_kh'] ?? null;
        }
        if ($parent) $item->{$parent} = $data['parent_id'];
        if ($request->level === 'country') {
            $item->country_code = $data['country_code'] ?? null;
            $item->international_phone_code = $data['international_phone_code'] ?? null;
            if ($request->hasFile('flag_image')) {
                if ($item->flag_path && str_starts_with($item->flag_path, 'storage/')) {
                    Storage::disk('public')->delete(substr($item->flag_path, 8));
                }
                $item->flag_path = 'storage/' . $request->file('flag_image')->store('country_flags', 'public');
            } elseif ($request->filled('flag_path')) {
                $item->flag_path = $request->flag_path;
            }
        }
        $item->save();
        return response()->json(['status'=>'success','message'=>'Location saved successfully.','data'=>$item]);
    }

    public function delete(Request $request, $id)
    {
        abort_unless(isset($this->levels[$request->level]), 404);
        $model = $this->levels[$request->level][0]; $model::findOrFail($id)->delete();
        return response()->json(['status'=>'success','message'=>'Location deleted successfully.']);
    }
}
