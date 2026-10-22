<?php
// Only renders real shared editor views; no production records.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode=$argv[1]??'standalone';
if($mode==='standalone'){
    echo view('photos._workspace')->render();
    echo view('photos._editor',['editorId'=>'photoEditorModal','photoTitle'=>'Photo Editor','allowPhotoSizes'=>true,
        'sizeUrl'=>route('photo-editor.size',[],false),'backgroundUrl'=>route('photo-editor.background',[],false)])->render();
}elseif($mode==='student'){
    echo '<div class="modal fade" id="enrollmentModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div id="studentPhotoDropzone" tabindex="0"><input id="student_photo" type="file"><div id="studentPhotoPreviewContainer"><img id="studentPhotoPreview"></div></div><button id="studentPhotoEdit" type="button">Edit Photo</button></div></div></div></div>';
    echo view('students._photo-editor')->render();
}else{
    echo '<div class="modal fade" id="staffModal"><div class="modal-dialog"><div class="modal-content"><form data-staff-profile-form><div class="modal-body"><div data-staff-photo-dropzone><input id="staff-photo" name="photo" type="file"><img data-staff-photo-preview></div><button data-staff-photo-edit type="button">Edit Photo</button></div></form></div></div></div>';
    echo view('photos._editor',['editorId'=>'hrmStaffPhotoCropModal','photoTitle'=>'Staff Photo Studio','photoWidth'=>400,'photoHeight'=>400,
        'backgroundUrl'=>route('staff.photo.background',[],false)])->render();
}
