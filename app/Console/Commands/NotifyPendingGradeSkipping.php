<?php

namespace App\Console\Commands;

use App\Models\StudentSkippingGrade;
use App\Services\SkippingGradeNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyPendingGradeSkipping extends Command
{
    protected $signature = 'grade-skipping:notify-pending';
    protected $description = 'Create missing in-app alerts for currently awaiting grade skipping reviewers.';

    public function handle(SkippingGradeNotifications $notifications): int
    {
        $count = 0;
        StudentSkippingGrade::where('status', 'pending')->select('id')->chunkById(100, function ($records) use ($notifications, &$count) {
            foreach ($records as $item) DB::transaction(function () use ($item, $notifications, &$count) {
                $record = StudentSkippingGrade::whereKey($item->id)->lockForUpdate()->first();
                if (!$record || $record->status !== 'pending') return;
                $notifications->awaiting($record);
                $count++;
            });
        });
        $this->info('Checked '.$count.' pending requests. Existing alerts were preserved without duplicates.');
        return self::SUCCESS;
    }
}
