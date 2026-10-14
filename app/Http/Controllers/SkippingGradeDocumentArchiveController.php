<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolInfo;
use App\Models\SkippingDocumentArchive as Archive;
use App\Models\SkippingDocumentArchivePart as Part;
use App\Models\StudentSkippingGrade;
use App\Services\SkippingGradeDocumentArchiveService as Archives;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SkippingGradeDocumentArchiveController
{
    public function __construct(private readonly Archives $archives) {}

    public function index(Request $request)
    {
        $this->archives->actor($request->user()->id,'document-archive');
        $filters=$request->validate(['academic_year_id'=>['nullable','integer',Rule::exists('tb_academic_year','id')->whereNull('deleted_at')],
            'campus_id'=>['nullable','integer',Rule::exists('tb_school_info','id')->whereNull('deleted_at')]]);
        $years=AcademicYear::regular()->orderByDesc('academic_year')->get();
        $campuses=SchoolInfo::orderBy('campus_name_en')->get();
        $yearId=$filters['academic_year_id']??$years->firstWhere('lifecycle_status','started')?->id??$years->first()?->id;
        $campusId=$filters['campus_id']??null;
        $summary=['requests'=>0,'documents'=>0,'bytes'=>0,'archived'=>0,'unfinished'=>0];
        StudentSkippingGrade::where('target_academic_year_id',$yearId)->when($campusId,fn($q)=>$q->where('campus_id',$campusId))
            ->chunkById(100,function ($records) use (&$summary) {
                foreach ($records as $record) {
                    if (!in_array($record->status,['approved','rejected'],true)) { $summary['unfinished']++; continue; }
                    $summary['requests']++;
                    foreach ($record->supporting_documents??[] as $document) {
                        if (!empty($document['archived_at'])) { $summary['archived']++; continue; }
                        $summary['documents']++;
                        if (Storage::disk('local')->exists($document['path'])) $summary['bytes']+=Storage::disk('local')->size($document['path']);
                    }
                    if ($record->signed_request_path) {
                        if ($record->signed_request_archived_at) $summary['archived']++;
                        else { $summary['documents']++; if (Storage::disk('local')->exists($record->signed_request_path)) $summary['bytes']+=Storage::disk('local')->size($record->signed_request_path); }
                    }
                }
            });
        $records=Archive::with(['creator','campus'])->withCount(['parts','files'])->where('academic_year_id',$yearId)
            ->when($campusId,fn($q)=>$q->where('campus_id',$campusId))->latest('id')->paginate(15)->withQueryString();
        return view('student-skipping-grade.archive-index',compact('years','campuses','yearId','campusId','summary','records')+['retention'=>$this->archives->retention()]);
    }

    public function show(Request $request, Archive $archive)
    {
        $this->archives->actor($request->user()->id,'document-archive');
        $archive->load(['parts'=>fn($q)=>$q->withCount('files'),'creator','campus']);
        $events=DB::table('skipping_document_archive_events as e')->leftJoin('users as u','u.id','=','e.user_id')
            ->where('e.archive_id',$archive->id)->orderByDesc('e.id')->select('e.*','u.name as user_name')->paginate(20);
        $files=$archive->files()->orderBy('id')->paginate(30,['*'],'files_page');
        return view('student-skipping-grade.archive-show',compact('archive','events','files')+['canDeleteYear'=>$this->archives->canDeleteYear($archive->academic_year)]);
    }

    public function create(Request $request)
    {
        $this->archives->actor($request->user()->id,'archive-backup');
        $data=$request->validate(['academic_year_id'=>['required','integer',Rule::exists('tb_academic_year','id')->whereNull('deleted_at')],
            'campus_id'=>['nullable','integer',Rule::exists('tb_school_info','id')->whereNull('deleted_at')]]);
        $archive=$this->archives->create($request->user(),$data['academic_year_id'],empty($data['campus_id'])?null:(int)$data['campus_id']);
        return $this->success($request,$archive,'Backup queued. Refresh this page to check progress.');
    }

    public function verify(Request $request, Archive $archive)
    {
        $this->archives->requestVerification($archive,$request->user());
        return $this->success($request,$archive,'Verification queued. No documents will be deleted.');
    }

    public function download(Request $request, Archive $archive, Part $part)
    {
        $this->archives->actor($request->user()->id,'archive-backup');
        abort_unless((int)$part->archive_id===(int)$archive->id,404);
        abort_unless($archive->status==='ready' && $part->status==='verified' && $part->verified_at && Storage::disk('local')->exists($part->path),409,'The verified backup is not ready to download.');
        if (!hash_equals($part->sha256,hash_file('sha256',Storage::disk('local')->path($part->path)))) abort(409,'The ZIP changed. Verify the archive again before downloading.');
        $part->update(['downloaded_at'=>now()]);
        $this->archives->event($archive,'download_started',$request->user()->id,'Part '.$part->number.'. External saving must be confirmed separately.');
        return Storage::disk('local')->download($part->path,'Grade-Skipping-'.$archive->academic_year.'-'.$archive->reference.'-part-'.$part->number.'.zip',['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function delete(Request $request, Archive $archive)
    {
        $this->archives->actor($request->user()->id,'archive-delete');
        $request->validate(['external_backup_saved'=>['required','accepted'],'confirmation'=>['required',Rule::in([$archive->reference])]]);
        $this->archives->requestDeletion($archive,$request->user());
        return $this->success($request,$archive,'Deletion queued. Student and approval records will be retained.');
    }

    public function restore(Request $request, Archive $archive, Part $part)
    {
        $this->archives->actor($request->user()->id,'archive-restore');
        abort_unless((int)$part->archive_id===(int)$archive->id,404);
        $request->validate(['backup'=>['required','file','mimes:zip','max:225280']]);
        $path=$request->file('backup')->store('grade-skipping/archive-restores','local');
        if (!$path) throw ValidationException::withMessages(['backup'=>'Unable to save the uploaded backup.']);
        try {
            DB::transaction(function () use ($archive,$part,$request,$path) {
                $archive=Archive::whereKey($archive->id)->lockForUpdate()->firstOrFail();
                if (!in_array($archive->status,['deleted','partially_restored','restored','delete_failed','restore_failed'],true)) throw ValidationException::withMessages(['backup'=>'Another operation is running, or this archive has not been deleted.']);
                $archive->update(['status'=>'restoring','error'=>null]);
                $this->archives->event($archive,'restore_requested',$request->user()->id,'Part '.$part->number);
                $this->archives->queue('restore',$part->id,$request->user()->id,$path);
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return $this->success($request,$archive,'Restore queued. The original ZIP and every file checksum will be checked.');
    }

    private function success(Request $request, Archive $archive, string $message)
    {
        $url=route('student-skipping-grade.archive.show',$archive);
        return $request->expectsJson()?response()->json(['message'=>$message,'redirect'=>$url]):redirect($url)->with('success',$message);
    }
}
