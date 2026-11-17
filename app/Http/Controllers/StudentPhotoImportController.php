<?php

namespace App\Http\Controllers;

use App\Services\StudentPhotoImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class StudentPhotoImportController
{
    public function index(Request $request, StudentPhotoImport $service)
    {
        $service->access($request->user());
        return view('student-photo-import', ['batches'=>DB::table('student_photo_batches')
            ->where('user_id',$request->user()->id)->latest('created_at')->paginate(10)]);
    }

    public function create(Request $request, StudentPhotoImport $service)
    {
        $request->validate(['replace_existing'=>['required','boolean']]);
        return response()->json(['id'=>$service->create($request->user(),$request->boolean('replace_existing'))]);
    }

    public function upload(Request $request, string $batch, StudentPhotoImport $service)
    {
        $service->batch($request->user(),$batch);
        $request->validate(['file'=>['required','file','max:51200','extensions:jpg,jpeg,png,webp,zip']]);
        $service->upload($request->user(),$batch,$request->file('file'));
        return response()->json(['message'=>'File added to preview.']);
    }

    public function inspect(Request $request, string $batch, StudentPhotoImport $service)
    {
        $record=$service->batch($request->user(),$batch);
        $rows=DB::table('student_photo_rows')->where('batch_id',$batch)->orderBy('id')->get();
        $access=$service->access($request->user()); $access->loadStudents($rows->pluck('student_id')->filter()->all());
        $summary=$rows->countBy('status')->all();
        return response()->json(['batch'=>['id'=>$record->id,'status'=>$record->status,'replace_existing'=>(bool)$record->replace_existing,
            'expired'=>now()->greaterThan($record->expires_at)],'total'=>$rows->count(),'summary'=>$summary,
            'rows'=>$rows->map(function ($row) use ($batch,$access) {
                $allowed=$row->student_id && $access->canAccessStudentCampus($row->student_id);
                return ['id'=>$row->id,'filename'=>$row->filename,'student_code'=>$row->student_code,
                    'student_name'=>$allowed?$row->student_name:null,'status'=>$row->status,'reason'=>$row->reason,
                    'source_url'=>$row->source_path?route('student-photo-import.image',[$batch,$row->id,'source'],false):null,
                    'current_url'=>$allowed && $row->before_path?route('student-photo-import.image',[$batch,$row->id,'current'],false):null];
            })]);
    }

    public function confirm(Request $request, string $batch, StudentPhotoImport $service)
    {
        $request->validate(['selected_ids'=>['required','array','max:500'],'selected_ids.*'=>['required','integer','distinct']]);
        $service->confirm($request->user(),$batch,$request->input('selected_ids'));
        return $this->inspect($request,$batch,$service);
    }

    public function process(Request $request, string $batch, StudentPhotoImport $service)
    {
        $service->process($request->user(),$batch);
        // Small response during processing: do not repeatedly transfer all 500 preview rows.
        $record=$service->batch($request->user(),$batch);
        $counts=DB::table('student_photo_rows')->where('batch_id',$batch)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status');
        return response()->json(['status'=>$record->status,'summary'=>$counts]);
    }

    public function image(Request $request, string $batch, int $row, string $kind, StudentPhotoImport $service)
    {
        $record=$service->batch($request->user(),$batch);
        abort_if(now()->greaterThan($record->expires_at),410,'Preview expired.');
        $entry=DB::table('student_photo_rows')->where('batch_id',$batch)->where('id',$row)->firstOrFail();
        if ($kind==='source') { $disk='local'; $path=$entry->source_path; }
        else {
            abort_unless($entry->student_id && $service->access($request->user())->canAccessStudentCampus($entry->student_id),403);
            $disk='public'; $path=$entry->before_path;
        }
        abort_unless($path && Storage::disk($disk)->exists($path),404);
        return response()->file(Storage::disk($disk)->path($path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function review(Request $request, string $batch, StudentPhotoImport $service)
    {
        $service->batch($request->user(),$batch);
        return response()->streamDownload(function () use ($batch) {
            $stream=fopen('php://output','wb'); fwrite($stream,"\xEF\xBB\xBF");
            fputcsv($stream,['Filename','Student ID','Status','Reason'],',','"','');
            foreach (DB::table('student_photo_rows')->where('batch_id',$batch)->where('status','<>','updated')->orderBy('id')->cursor() as $row) {
                $values=[$row->filename,$row->student_code,$row->status,$row->reason];
                $values=array_map(fn ($v) => preg_match('/^[=+@\-\t\r\n]/',(string)$v)?"'".$v:$v,$values);
                fputcsv($stream,$values,',','"','');
            }
            fclose($stream);
        },'student-photos-review-'.$batch.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
