<?php

namespace App\Console\Commands;

use App\Models\Occupation;
use Illuminate\Console\Command;

class SyncSchoolOccupations extends Command
{
    protected $signature = 'occupations:sync-school-list';

    protected $description = 'Replace broad occupation data with a school-friendly parent occupation list.';

    private const SCHOOL_OCCUPATIONS = [
        ['Teacher', 'គ្រូបង្រៀន'],
        ['Doctor', 'វេជ្ជបណ្ឌិត'],
        ['Nurse', 'គិលានុបដ្ឋាក'],
        ['Government Officer', 'មន្ត្រីរាជការ'],
        ['Police Officer', 'មន្ត្រីនគរបាល'],
        ['Soldier', 'ទាហាន'],
        ['Business Owner', 'ម្ចាស់អាជីវកម្ម'],
        ['Seller', 'អ្នកលក់ដូរ'],
        ['Farmer', 'កសិករ'],
        ['Driver', 'អ្នកបើកបរ'],
        ['Factory Worker', 'កម្មកររោងចក្រ'],
        ['Construction Worker', 'កម្មករសំណង់'],
        ['Company Staff', 'បុគ្គលិកក្រុមហ៊ុន'],
        ['Housewife', 'មេផ្ទះ'],
        ['Monk', 'ព្រះសង្ឃ'],
        ['Retired', 'ចូលនិវត្តន៍'],
        ['Unemployed', 'គ្មានការងារ'],
        ['Other', 'ផ្សេងៗ'],
    ];

    public function handle(): int
    {
        $schoolNames = collect(self::SCHOOL_OCCUPATIONS)
            ->pluck(0)
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        $deleted = 0;
        $deactivated = 0;
        $saved = 0;

        Occupation::withTrashed()->get()->each(function (Occupation $occupation) use ($schoolNames, &$deleted, &$deactivated) {
            $isSchoolOccupation = in_array(mb_strtolower($occupation->occupation_name_en), $schoolNames, true);

            if ($isSchoolOccupation) {
                return;
            }

            if ($occupation->familyMembers()->exists()) {
                $occupation->update(['status' => 0]);
                $deactivated++;
                return;
            }

            $occupation->forceDelete();
            $deleted++;
        });

        foreach (self::SCHOOL_OCCUPATIONS as [$english, $khmer]) {
            $occupation = Occupation::withTrashed()->firstOrNew([
                'occupation_name_en' => $english,
            ]);

            $occupation->fill([
                'occupation_name_kh' => $khmer,
                'status' => 1,
            ]);

            if ($occupation->exists && $occupation->trashed()) {
                $occupation->restore();
            }

            $occupation->save();
            $saved++;
        }

        $this->info("School occupation list synced. Active school occupations: {$saved}. Removed unassigned broad occupations: {$deleted}. Deactivated assigned non-school occupations: {$deactivated}.");

        return self::SUCCESS;
    }
}
