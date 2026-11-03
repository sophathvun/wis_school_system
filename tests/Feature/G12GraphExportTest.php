<?php

use App\Http\Controllers\G12GraphExportController;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';
beforeEach(fn()=>createG12NationalSchema());

it('exports a graph image to PDF and enforces export permissions',function(){
    $image=imagecreatetruecolor(500,1200);imagefill($image,0,0,imagecolorallocate($image,240,245,255));
    ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);
    $request=g12Request(User::find(1),[]);
    $request->files->set('image',UploadedFile::fake()->createWithContent('graph.png',$bytes));
    $controller=app(G12GraphExportController::class);$response=$controller->pdf($request);
    expect(substr($response->getContent(),0,4))->toBe('%PDF')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf');
    $reader=g12ExamUser(['view','reports']);
    expect(fn()=>$controller->pdf(g12Request($reader,[])))->toThrow(HttpException::class);
});
