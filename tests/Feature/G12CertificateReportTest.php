<?php

use App\Models\G12Certificate;
use App\Models\G12CertificateYear;
use App\Models\User;
use App\Services\G12CertificateReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Support\G12CertificatePermissions;

function g12CertificatePermissionUser(array $codes = [], string $source = 'user'): User
{
    Schema::table('tb_school_info', function (Blueprint $t) { $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('is_global')->default(false); $t->integer('active_campus_id')->nullable(); $t->integer('department_id')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_roles', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('description')->nullable(); $t->integer('department_id')->nullable(); $t->boolean('is_global')->default(false); $t->boolean('is_system')->default(false); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_permissions', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('module'); $t->string('action'); $t->string('name'); $t->timestamps(); });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); $t->timestamps(); });
    Schema::create('access_role_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); $t->timestamps(); });
    Schema::create('access_user_permission_overrides', function (Blueprint $t) { $t->integer('user_id'); $t->integer('permission_id'); $t->boolean('allowed'); $t->timestamps(); });
    Schema::create('access_user_campuses', function (Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
    Schema::create('access_departments', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_department_permissions', function (Blueprint $t) { $t->integer('department_id'); $t->integer('permission_id'); $t->timestamps(); });
    DB::table('access_roles')->insert([['id'=>1,'code'=>'super-admin','name'=>'Super Administrator'],['id'=>2,'code'=>'registrar','name'=>'Registrar']]);
    DB::table('access_permissions')->insert(['code'=>'reports.view','module'=>'reports','action'=>'view','name'=>'View Reports']);
    DB::table('access_permissions')->insert(\App\Support\AcademicReportPermissions::catalog());
    (require database_path('migrations/2026_10_06_000013_add_g12_certificate_action_permissions.php'))->up();
    DB::table('users')->insert(['id'=>1,'name'=>'Permission Test User','active_campus_id'=>2,'department_id'=>$source==='department'?1:null]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>2,'campus_id'=>2]);
    if ($source==='department') DB::table('access_departments')->insert(['id'=>1,'code'=>'test','name'=>'Test Department']);
    $ids=DB::table('access_permissions')->whereIn('code',array_merge(['reports.view','reports.g12.view'],$codes))->pluck('id');
    foreach ($ids as $id) {
        if ($source==='role') DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>$id]);
        elseif ($source==='department') DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>$id]);
        else DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>$id,'allowed'=>true]);
    }
    \Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag);
    return User::findOrFail(1);
}

beforeEach(function () {
    Schema::create('tb_academic_year', function (Blueprint $t) { $t->id(); $t->string('academic_year')->default('2025-2026'); $t->string('period_type')->default('regular'); $t->softDeletes(); });
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); });
    Schema::create('tb_grade', function (Blueprint $t) { $t->id(); $t->string('grade'); $t->string('grade_short_name'); $t->softDeletes(); });
    Schema::create('tb_class', function (Blueprint $t) { $t->id(); $t->string('class_name'); $t->softDeletes(); });
    Schema::create('tb_student', function (Blueprint $t) { $t->id(); $t->string('student_id'); $t->string('full_name_en'); $t->string('full_name_kh')->default(''); $t->integer('status')->default(1); });
    Schema::create('tb_student_enrollment', function (Blueprint $t) {
        $t->id(); foreach (['student_id','academic_year_id','campus_id','grade_id','class_id'] as $column) $t->integer($column);
        $t->integer('status')->default(1); $t->string('enrollment_status')->default('active');
    });
    (require database_path('migrations/2026_10_06_000010_create_g12_certificates.php'))->up();
    (require database_path('migrations/2026_10_06_000011_add_g12_certificate_typography.php'))->up();
    (require database_path('migrations/2026_10_06_000012_create_g12_certificate_template.php'))->up();
    (require database_path('migrations/2026_10_06_000014_add_g12_certificate_qr_tokens.php'))->up();
    DB::table('tb_academic_year')->insert([['id'=>1],['id'=>2]]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BKK'],['id'=>2,'campus_name_en'=>'BCH']]);
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'12','grade_short_name'=>'12'],['id'=>2,'grade'=>'8','grade_short_name'=>'8']]);
    DB::table('tb_class')->insert([['id'=>1,'class_name'=>'A'],['id'=>2,'class_name'=>'B']]);
    $names = ['ZOE','BOB','ALICE','CHARLIE','OTHER GRADE','OTHER YEAR','WITHDRAWN','INACTIVE'];
    foreach ($names as $offset=>$name) {
        $id=$offset+1;
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'ID-'.$id,'full_name_en'=>$name]);
        DB::table('tb_student_enrollment')->insert([
            'id'=>$id,'student_id'=>$id,'academic_year_id'=>$id===6?2:1,'campus_id'=>$id===1?1:2,
            'grade_id'=>$id===5?2:1,'class_id'=>$id===4?2:1,'enrollment_status'=>$id===7?'withdrawn':'active','status'=>$id===8?0:1,
        ]);
    }
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(true);
    $this->request=Request::create('/reports'); $this->request->setUserResolver(fn()=>$user);
    $this->service=new G12CertificateReport;
    $this->data=['academic_year_id'=>1,'given_date'=>'2026-08-15','number_prefix'=>'26G12','action'=>'assign'];
});

it('assigns G12 only by campus then class then English name across the whole year', function () {
    expect($this->service->save($this->request,$this->data))->toBe(4);
    expect(G12Certificate::orderBy('sequence')->pluck('student_id')->all())->toBe([3,2,4,1]);
    expect(G12Certificate::orderBy('sequence')->pluck('certificate_number')->all())->toBe(['26G12001','26G12002','26G12003','26G12004']);
    expect(DB::table('tb_student_enrollment')->where('enrollment_status','active')->count())->toBe(7);
});

it('stores distinct QR tokens and keeps them after reassigning numbers or editing the prefix', function () {
    $this->service->save($this->request, $this->data);
    $tokens = G12Certificate::orderBy('student_id')->pluck('qr_token', 'student_id')->all();
    expect(array_unique($tokens))->toHaveCount(4);
    foreach ($tokens as $token) expect($token)->toMatch('/^[A-Za-z0-9]{40}$/');
    $this->service->save($this->request, $this->data);
    $this->service->save($this->request, array_replace($this->data, ['action'=>'update_prefix', 'number_prefix'=>'NEW-G12']));
    expect(G12Certificate::orderBy('student_id')->pluck('qr_token', 'student_id')->all())->toBe($tokens);
});

it('uses the Branding favicon in the QR center for both preview and exported content', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $logo = imagecreatetruecolor(128, 64);
    imagefill($logo, 0, 0, imagecolorallocate($logo, 220, 25, 35));
    ob_start(); imagepng($logo); $contents = ob_get_clean(); imagedestroy($logo);
    \Illuminate\Support\Facades\Storage::disk('public')->put('branding/favicon.png', $contents);
    Schema::create('tb_branding_setting', function (Blueprint $t) { $t->id(); $t->string('favicon_path')->nullable(); });
    DB::table('tb_branding_setting')->insert(['favicon_path'=>'branding/favicon.png']);
    $this->service->save($this->request, $this->data);
    foreach ([true, false] as $preview) {
        $payload = $this->service->payload($this->request, ['academic_year_id'=>1, 'certificate_show_qr'=>'1'], $preview);
        $image = imagecreatefromstring(base64_decode(substr($payload['certificates']->first()->certificate_qr_image, strlen('data:image/png;base64,'))));
        $center = imagecolorsforindex($image, imagecolorat($image, 210, 210));
        expect([$center['red'], $center['green'], $center['blue']])->toBe([220,25,35]);
        $backing = imagecolorsforindex($image, imagecolorat($image, 210, 170));
        expect([$backing['red'], $backing['green'], $backing['blue']])->toBe([255,255,255]);
        imagedestroy($image);
    }
});

it('keeps QR generation working if the favicon file is missing or unreadable', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $token = str_repeat('a', 40);
    $plain = \App\Support\G12CertificateQr::image($token, '');
    expect(\App\Support\G12CertificateQr::image($token, 'branding/missing.png'))->toBe($plain);
    \Illuminate\Support\Facades\Storage::disk('public')->put('branding/broken.png', 'broken image');
    expect(\App\Support\G12CertificateQr::image($token, 'branding/broken.png'))->toBe($plain);
});

it('backfills QR tokens for existing certificates without changing their numbers', function () {
    $this->service->save($this->request, $this->data);
    $numbers = G12Certificate::orderBy('id')->pluck('certificate_number')->all();
    $migration = require database_path('migrations/2026_10_06_000014_add_g12_certificate_qr_tokens.php');
    $migration->down();
    $migration->up();
    expect(G12Certificate::whereNotNull('qr_token')->count())->toBe(4)
        ->and(G12Certificate::distinct()->count('qr_token'))->toBe(4)
        ->and(G12Certificate::orderBy('id')->pluck('certificate_number')->all())->toBe($numbers);
});

it('verifies an issued G12 certificate publicly without a login and reflects prefix corrections', function () {
    $this->service->save($this->request, $this->data);
    $certificate = G12Certificate::where('student_id', 3)->firstOrFail();
    $url = route('g12-certificate.verify', $certificate->qr_token);
    $this->get($url)->assertOk()->assertSee('Verified G12 Certificate')->assertSee('ALICE')
        ->assertSee('26G12001')->assertSee('2025-2026')->assertSee('15 Aug 2026')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('ID-3');
    $this->service->save($this->request, array_replace($this->data, ['action'=>'update_prefix', 'number_prefix'=>'FIX-G12']));
    $this->get($url)->assertOk()->assertSee('FIX-G12001')->assertDontSee('26G12001');
});

it('rejects missing and malformed public G12 tokens', function () {
    $this->get(route('g12-certificate.verify', str_repeat('a', 40)))->assertNotFound();
    $this->get('/certificates/g12/1')->assertNotFound();
});

it('exports one G12 PDF page with verification inside the QR border and scan instructions below', function () {
    $this->service->save($this->request, $this->data);
    $payload = $this->service->payload($this->request, ['academic_year_id'=>1, 'certificate_student_id'=>3, 'certificate_show_qr'=>'1']);
    $html = \App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print', $payload + ['pdfMode'=>true])->render());
    $temp = storage_path('framework/testing/g12-pdf');
    \Illuminate\Support\Facades\File::ensureDirectoryExists($temp);
    $pdf = new \Dompdf\Dompdf(['chroot'=>[public_path(), resource_path('css'), $temp], 'tempDir'=>$temp, 'fontDir'=>$temp, 'fontCache'=>$temp]);
    $pdf->loadHtml($html);
    $pdf->setPaper('a4', 'landscape');
    \App\Support\G12CertificatePdfLayout::configure($pdf);
    $prepare = $pdf->getCallbacks()['begin_page_reflow'][0];
    $boxes = [];
    $pdf->setCallbacks([
        ['event'=>'begin_page_reflow', 'f'=>$prepare],
        ['event'=>'begin_page_render', 'f'=>function ($page) use (&$boxes) {
            foreach ($page->get_subtree() as $frame) {
                $node = $frame->get_node();
                if (!$node instanceof DOMElement) continue;
                if ($node->tagName === 'img' && $node->getAttribute('alt') === 'QR code to verify this G12 certificate') {
                    $boxes['qr'] = $frame->get_border_box();
                }
                foreach (['g12-qr-verification-note'=>'verification', 'g12-qr-scan-note'=>'scan'] as $class=>$key) {
                    if ($node->getAttribute('class') === $class) $boxes[$key] = $frame->get_border_box();
                }
            }
        }],
    ]);
    $pdf->render();
    expect($pdf->getCanvas()->get_page_count())->toBe(1)
        ->and($pdf->output())->toContain('/Subtype /Image');
    expect($boxes)->toHaveKeys(['qr', 'verification', 'scan']);
    expect($boxes['verification']['y'])->toBeGreaterThan($boxes['qr']['y'])
        ->and($boxes['verification']['y'] + $boxes['verification']['h'])->toBeLessThan($boxes['qr']['y'] + $boxes['qr']['h'])
        ->and($boxes['scan']['y'])->toBeGreaterThanOrEqual($boxes['qr']['y'] + $boxes['qr']['h']);
});

it('shows QR in preview and printed content only when checked', function () {
    $this->service->save($this->request, $this->data);
    $filters = ['academic_year_id'=>1, 'certificate_student_id'=>3];
    $without = $this->service->payload($this->request, $filters);
    $with = $this->service->payload($this->request, $filters + ['certificate_show_qr'=>'1']);
    expect(view('reports.g12-certificate-print', $without)->render())->not->toContain('class="g12-certificate-qr"');
    $html = view('reports.g12-certificate-print', $with)->render();
    expect($html)->toContain('class="g12-certificate-qr"', 'data:image/png;base64,', 'data-g12-layout-key="qr"');
    $verificationUrl = route('g12-certificate.verify', G12Certificate::where('student_id', 3)->firstOrFail()->qr_token);
    expect($html)->toContain('class="g12-qr-verification-note"', 'href="'.$verificationUrl.'"', 'g12-qr-verification-tick', 'Verify:');
    $png = base64_decode(substr($with['certificates']->first()->certificate_qr_image, strlen('data:image/png;base64,')));
    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    $size = getimagesizefromstring($png);
    expect($size[0])->toBe(420)->and($size[1])->toBe(420);
    $preview = $this->service->payload($this->request, $filters, true);
    $document = new DOMDocument();
    @$document->loadHTML(view('reports._g12-certificate-page', $preview + ['certificate'=>$preview['certificates']->first(), 'showFrame'=>true])->render());
    expect((new DOMXPath($document))->query('//div[@class="g12-certificate-qr" and @hidden]')->length)->toBe(1);
    expect((new DOMXPath($document))->query('//div[@class="g12-certificate-qr" and not(@hidden)]')->length)->toBe(0);
    $previewWithQr = $this->service->payload($this->request, $filters + ['certificate_show_qr'=>'1'], true);
    @$document->loadHTML(view('reports._g12-certificate-page', $previewWithQr + ['certificate'=>$previewWithQr['certificates']->first(), 'showFrame'=>true])->render());
    expect((new DOMXPath($document))->query('//div[@class="g12-certificate-qr" and not(@hidden)]')->length)->toBe(1);
    $link = (new DOMXPath($document))->query('//div[@class="g12-certificate-qr"]/div[@class="g12-qr-frame"]/a')->item(0);
    expect($link->getAttribute('href'))->toBe($verificationUrl);
    expect((new DOMXPath($document))->query('//div[@class="g12-certificate-qr"]/span[@class="g12-qr-scan-note"]')->item(0)->textContent)->toBe('Scan to Verify.');
});

it('can position QR in preview before certificate numbers are assigned', function () {
    $payload = $this->service->payload($this->request, ['academic_year_id'=>1], true);
    $html = view('reports._g12-certificate-page', $payload + ['certificate'=>$payload['certificates']->first(), 'showFrame'=>true])->render();
    expect($html)->toContain('data-g12-layout-key="qr"', 'Assign certificate numbers to activate');
});

it('saves the QR position and uses it for future certificate previews and PDF output', function () {
    $layout = \App\Support\G12CertificateLayout::defaults();
    $layout['fields']['qr'] = ['x'=>65, 'y'=>71, 'width'=>9];
    $this->actingAs($this->request->user())->withoutMiddleware();
    $this->post(route('reports.g12-certificates.template'), ['template_data'=>json_encode($layout), 'template_version'=>0])->assertRedirect();
    $this->service->save($this->request, $this->data);
    $payload = $this->service->payload($this->request, ['academic_year_id'=>1, 'certificate_show_qr'=>'1']);
    expect($payload['certificateLayout']['fields']['qr'])->toEqual($layout['fields']['qr']);
    $future = $this->service->payload($this->request, ['academic_year_id'=>2], true);
    expect($future['certificateLayout']['fields']['qr'])->toEqual($layout['fields']['qr']);
    $html = \App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print', $payload + ['pdfMode'=>true])->render());
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $qr = (new DOMXPath($document))->query('//*[@data-g12-layout-key="qr"]')->item(0);
    expect($qr->getAttribute('style'))->toContain('left:65%', 'top:71%', 'width:9%');
});

it('keeps older templates compatible and contains resized QR blocks inside the page', function () {
    $layout = \App\Support\G12CertificateLayout::defaults();
    unset($layout['fields']['qr']);
    expect(\Illuminate\Support\Facades\Validator::make($layout, \App\Support\G12CertificateLayout::rules())->passes())->toBeTrue();
    expect(\App\Support\G12CertificateLayout::resolve($layout)['fields']['qr'])->toEqual(['x'=>33, 'y'=>68, 'width'=>10]);
    $layout['fields']['qr'] = ['x'=>98, 'y'=>95, 'width'=>20];
    $qr = \App\Support\G12CertificateLayout::resolve($layout)['fields']['qr'];
    expect($qr['x'] + $qr['width'])->toBeLessThanOrEqual(100)
        ->and($qr['y'] + ($qr['width']*297/100+3)*100/210)->toBeLessThanOrEqual(100);
});

it('accepts numeric and G12 grade codes while excluding K3 and G9', function () {
    DB::table('tb_grade')->insert([
        ['id'=>3,'grade'=>'K3','grade_short_name'=>'K3-'],
        ['id'=>4,'grade'=>'9','grade_short_name'=>'G9'],
    ]);
    DB::table('tb_student_enrollment')->where('student_id',5)->update(['grade_id'=>3]);
    DB::table('tb_student_enrollment')->where('student_id',6)->update(['academic_year_id'=>1,'grade_id'=>4]);
    expect($this->service->rows($this->request,1)->pluck('student_id')->all())->toBe([3,2,4,1]);
    DB::table('tb_grade')->where('id',1)->update(['grade_short_name'=>'G12-']);
    expect($this->service->rows($this->request,1)->pluck('student_id')->all())->toBe([3,2,4,1]);
});

it('curves the title upward with editable text and embedded fonts on every PDF page', function () {
    $this->service->save($this->request,$this->data);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    $html=App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print',$payload+['pdfMode'=>true])->render());
    $document=new DOMDocument;
    @$document->loadHTML($html);
    $xpath=new DOMXPath($document);
    $titles=$xpath->query('//*[contains(@class,"g12-title-arc")]');
    expect($titles)->toHaveCount(4);
    foreach ($titles as $title) {
        expect($title->textContent)->toBe('High School Diploma');
        $letters=$xpath->query('./span', $title);
        expect($letters)->toHaveCount(mb_strlen($title->textContent));
        expect($letters[0]->getAttribute('style'))->toContain('transform:rotate(-');
        $firstTop=(float)explode('pt',explode('top:',$letters[0]->getAttribute('style'))[1])[0];
        $middleTop=(float)explode('pt',explode('top:',$letters[12]->getAttribute('style'))[1])[0];
        expect($firstTop-$middleTop)->toBeGreaterThan(10);
    }
    $temp=storage_path('framework/testing/g12-pdf');
    Illuminate\Support\Facades\File::ensureDirectoryExists($temp);
    $pdf=new Dompdf\Dompdf(['chroot'=>[public_path(),resource_path('css'),$temp],'tempDir'=>$temp,'fontDir'=>$temp,'fontCache'=>$temp]);
    $pdf->loadHtml($html);$pdf->setPaper('a4','landscape');App\Support\G12CertificatePdfLayout::configure($pdf);
    $pdf->render();
    expect($pdf->getCanvas()->get_page_count())->toBe(4);
    $layout=App\Support\G12CertificateLayout::defaults();
    $layout['fields']['title']=array_replace($layout['fields']['title'],['text'=>'Edited Diploma','font'=>'times','color'=>'#123456','width'=>25]);
    $html=App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print',array_replace($payload,['pdfMode'=>true,'certificateLayout'=>$layout]))->render());
    expect($html)->toContain('aria-label="Edited Diploma"','g12-title-letter','color:#123456');
});

it('keeps G12 numbers dates templates and QR tokens independent from G9', function () {
    (require database_path('migrations/2026_10_06_000005_create_g9_certificates.php'))->up();
    (require database_path('migrations/2026_10_06_000006_add_g9_certificate_typography.php'))->up();
    (require database_path('migrations/2026_10_06_000007_create_g9_certificate_template.php'))->up();
    (require database_path('migrations/2026_10_06_000009_add_g9_certificate_qr_tokens.php'))->up();
    $g9 = App\Models\G9CertificateYear::create(['academic_year_id'=>1,'given_date'=>'2026-08-01','number_prefix'=>'G9-']);
    $issued = App\Models\G9Certificate::create(['certificate_year_id'=>$g9->id,'student_id'=>3,'enrollment_id'=>3,'sequence'=>1,'certificate_number'=>'G9-001']);
    App\Models\G9CertificateTemplate::find(1)->update(['layout'=>['fields'=>['heading'=>['text'=>'G9 ONLY']]],'version'=>7]);
    $this->service->save($this->request,$this->data);
    $layout=App\Support\G12CertificateLayout::defaults();
    $layout['fields']['title']['text']='G12 ONLY';
    $this->service->saveTemplate($this->request,$layout,0);
    $this->service->save($this->request,array_replace($this->data,['action'=>'update_prefix','number_prefix'=>'G12-']));
    expect($g9->fresh()->number_prefix)->toBe('G9-')->and($g9->fresh()->given_date->toDateString())->toBe('2026-08-01')
        ->and($issued->fresh()->certificate_number)->toBe('G9-001')->and($issued->fresh()->qr_token)->toBe($issued->qr_token)
        ->and(App\Models\G9CertificateTemplate::find(1)->version)->toBe(7)
        ->and(App\Models\G9CertificateTemplate::find(1)->layout['fields']['heading']['text'])->toBe('G9 ONLY')
        ->and(App\Models\G12CertificateTemplate::find(1)->layout['fields']['title']['text'])->toBe('G12 ONLY');
    $g12 = G12Certificate::where('student_id',3)->firstOrFail();
    $this->get(route('g12-certificate.verify',$issued->qr_token))->assertNotFound();
    $this->get(route('g9-certificate.verify',$g12->qr_token))->assertNotFound();
    $this->get(route('g12-certificate.verify',$g12->qr_token))->assertOk()->assertSee('G12-001')->assertSee('12A');
});

it('keeps G12 numbers dates and templates independent from K3', function () {
    (require database_path('migrations/2026_10_06_000001_create_k3_certificates.php'))->up();
    (require database_path('migrations/2026_10_06_000002_add_k3_certificate_typography.php'))->up();
    (require database_path('migrations/2026_10_06_000003_create_k3_certificate_template.php'))->up();
    (require database_path('migrations/2026_10_06_000015_add_k3_certificate_qr_tokens.php'))->up();
    $k3 = App\Models\K3CertificateYear::create(['academic_year_id'=>1,'given_date'=>'2026-08-01','number_prefix'=>'K3-']);
    App\Models\K3Certificate::create(['certificate_year_id'=>$k3->id,'student_id'=>3,'enrollment_id'=>3,'sequence'=>1,'certificate_number'=>'K3-001']);
    App\Models\K3CertificateTemplate::find(1)->update(['layout'=>['fields'=>['heading'=>['text'=>'K3 ONLY']]],'version'=>7]);
    $this->service->save($this->request,$this->data);
    $layout=App\Support\G12CertificateLayout::defaults();
    $layout['fields']['title']['text']='G12 ONLY';
    $this->service->saveTemplate($this->request,$layout,0);
    $this->service->save($this->request,array_replace($this->data,['action'=>'update_prefix','number_prefix'=>'G12-']));
    expect($k3->fresh()->number_prefix)->toBe('K3-')->and($k3->fresh()->given_date->toDateString())->toBe('2026-08-01')
        ->and(App\Models\K3Certificate::value('certificate_number'))->toBe('K3-001')
        ->and(App\Models\K3CertificateTemplate::find(1)->version)->toBe(7)
        ->and(App\Models\K3CertificateTemplate::find(1)->layout['fields']['heading']['text'])->toBe('K3 ONLY')
        ->and(App\Models\G12CertificateTemplate::find(1)->layout['fields']['title']['text'])->toBe('G12 ONLY');
});

it('saves the title curve and applies it to future preview and PDF output', function ($curve) {
    $this->actingAs($this->request->user())->withoutMiddleware();
    $this->service->save($this->request,$this->data);
    $layout=App\Support\G12CertificateLayout::defaults();
    $layout['fields']['title']['curve']=$curve;
    $this->post(route('reports.g12-certificates.template'),['template_data'=>json_encode($layout),'template_version'=>0])->assertRedirect();
    expect(App\Models\G12CertificateTemplate::find(1)->layout['fields']['title']['curve'])->toEqual($curve);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    $future=$this->service->payload($this->request,['academic_year_id'=>2]);
    expect($future['certificateLayout']['fields']['title']['curve'])->toEqual($curve);
    $preview=view('reports._g12-certificate-page',$payload+['certificate'=>$payload['certificates']->first()])->render();
    expect($preview)->toContain('data-g12-curve="'.$curve.'"');
    expect(view('reports._g12-certificate-editor',$payload)->render())->toContain('data-g12-property="curve"','0 = straight');
    $html=App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print',$payload+['pdfMode'=>true])->render());
    $document=new DOMDocument;@$document->loadHTML($html);
    $letter=(new DOMXPath($document))->query('//*[contains(@class,"g12-title-letter")]')->item(0);
    preg_match('/top:([^;]+)pt/', $letter->getAttribute('style'), $position);
    $top=(float)$position[1];
    if ($curve === 0) {
        expect($top)->toEqual(0)->and($letter->getAttribute('style'))->toContain('rotate(0deg)');
    } elseif ($curve < 26) expect($top)->toBeGreaterThan(0)->toBeLessThan(21);
    else expect($top)->toBeGreaterThan(21);
})->with(['straight'=>0,'gentle'=>10,'deeper'=>60]);

it('preserves the original curve in old templates and rejects invalid curve values', function () {
    $layout=App\Support\G12CertificateLayout::defaults();
    unset($layout['fields']['title']['curve']);
    expect(App\Support\G12CertificateLayout::resolve($layout)['fields']['title']['curve'])->toBe(26);
    $this->actingAs($this->request->user())->withoutMiddleware()->withoutExceptionHandling();
    foreach ([-1,91,'invalid'] as $curve) {
        $layout['fields']['title']['curve']=$curve;
        try {
            $this->post(route('reports.g12-certificates.template'),['template_data'=>json_encode($layout),'template_version'=>0]);
            $this->fail('Invalid curve should be rejected.');
        } catch (Illuminate\Validation\ValidationException $exception) {
            expect(array_keys($exception->errors()))->toContain('fields.title.curve');
        }
    }
    expect(App\Models\G12CertificateTemplate::find(1)->version)->toBe(0);
});

it('retains numbers on repeat assignment and on filtered reprints', function () {
    $this->service->save($this->request,$this->data);
    expect($this->service->save($this->request,$this->data))->toBe(0);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>2,'grade_class'=>'1:1','certificate_student_id'=>2]);
    expect($payload['certificates'])->toHaveCount(1)->and($payload['certificates']->first()->certificate_number)->toBe('26G12002')
        ->and($payload['certificateReady'])->toBeTrue()->and($payload['certificateStudents'])->toHaveCount(2);
});

it('saves separate given dates for each academic year without assigning numbers', function () {
    $this->service->save($this->request,array_replace($this->data,['action'=>'save_date']));
    $this->service->save($this->request,array_replace($this->data,['academic_year_id'=>2,'action'=>'save_date','given_date'=>'2027-09-12','number_prefix'=>'CUSTOM-']));
    expect($this->service->payload($this->request,['academic_year_id'=>1])['filters']['given_date'])->toBe('2026-08-15');
    expect($this->service->payload($this->request,['academic_year_id'=>2])['filters']['given_date'])->toBe('2027-09-12');
    expect(G12Certificate::count())->toBe(0);
});

it('prevents a changed prefix from renumbering issued certificates', function () {
    $this->service->save($this->request,$this->data);
    expect(fn()=>$this->service->save($this->request,array_replace($this->data,['number_prefix'=>'27G12'])))->toThrow(ValidationException::class);
    expect(G12CertificateYear::first()->number_prefix)->toBe('26G12');
});

it('corrects an assigned prefix without changing students, sequences, given date, or another year', function () {
    $this->service->save($this->request,array_replace($this->data,['number_prefix'=>'26']));
    $this->service->save($this->request,array_replace($this->data,['academic_year_id'=>2,'number_prefix'=>'27G12','given_date'=>'2027-08-15']));
    $year=G12CertificateYear::where('academic_year_id',1)->first();
    $before=$year->certificates()->orderBy('sequence')->get(['id','student_id','enrollment_id','sequence'])->toArray();
    expect($this->service->save($this->request,array_replace($this->data,['action'=>'update_prefix','given_date'=>'2026-09-01'])))->toBe(4);
    expect($year->fresh()->number_prefix)->toBe('26G12')->and($year->fresh()->given_date->toDateString())->toBe('2026-08-15');
    expect($year->certificates()->orderBy('sequence')->get(['id','student_id','enrollment_id','sequence'])->toArray())->toBe($before);
    expect($year->certificates()->orderBy('sequence')->pluck('certificate_number')->all())->toBe(['26G12001','26G12002','26G12003','26G12004']);
    expect(G12CertificateYear::where('academic_year_id',2)->first()->certificates()->value('certificate_number'))->toBe('27G12001');
});

it('corrects numeric prefixes even when new numbers overlap old numbers during the update', function () {
    $this->service->save($this->request,array_replace($this->data,['number_prefix'=>'26']));
    G12Certificate::where('sequence',4)->update(['sequence'=>1001,'certificate_number'=>'261001']);
    expect($this->service->save($this->request,array_replace($this->data,['number_prefix'=>'261','action'=>'update_prefix'])))->toBe(4);
    expect(G12Certificate::orderBy('sequence')->pluck('certificate_number')->all())->toBe(['261001','261002','261003','2611001']);
});

it('requires the edit prefix permission when correcting previously issued certificates', function () {
    $this->service->save($this->request,$this->data);
    DB::table('tb_student_enrollment')->where('campus_id',1)->update(['enrollment_status'=>'withdrawn']);
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>DB::table('tb_school_info')->where('id',2));
    $this->request->setUserResolver(fn()=>$user);
    expect(fn()=>$this->service->save($this->request,array_replace($this->data,['action'=>'update_prefix','number_prefix'=>'26'])))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(G12CertificateYear::first()->number_prefix)->toBe('26G12');
});

it('blocks out of scope reprints and year-wide assignment for a single campus user', function () {
    $this->service->save($this->request,$this->data);
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>DB::table('tb_school_info')->where('id',2));
    $this->request->setUserResolver(fn()=>$user);
    expect($this->service->payload($this->request,['academic_year_id'=>1,'certificate_student_id'=>1])['certificates'])->toBeEmpty();
    expect(fn()=>$this->service->save($this->request,$this->data))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('rejects missing English names atomically and issues one certificate per student', function () {
    DB::table('tb_student')->where('id',2)->update(['full_name_en'=>'']);
    expect(fn()=>$this->service->save($this->request,$this->data))->toThrow(ValidationException::class);
    expect(G12Certificate::count())->toBe(0)->and(G12CertificateYear::count())->toBe(0);
    DB::table('tb_student')->where('id',2)->update(['full_name_en'=>'BOB']);
    DB::table('tb_student_enrollment')->insert(['id'=>9,'student_id'=>2,'academic_year_id'=>1,'campus_id'=>2,'grade_id'=>1,'class_id'=>1]);
    expect($this->service->save($this->request,$this->data))->toBe(4);
});

it('prints dynamic content and the saved date without the preprinted frame', function () {
    $this->service->save($this->request,$this->data);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    $html=view('reports.g12-certificate-print',$payload+['pdfMode'=>true])->render();
    expect($html)->toContain('ALICE','26G12001','15th day of August 2026','12A / BCH','css/g12Certificate.css')
        ->not->toContain('<img','ID-5','OTHER GRADE','window.print');
    expect(substr_count($html,'<section class="g12-certificate-page'))->toBe(4);
});

it('renders one landscape PDF page per certificate and fits a long English name', function () {
    DB::table('tb_student')->where('id',3)->update(['full_name_en'=>'WILLIAM ALEXANDER BENJAMIN CHRISTOPHER EXAMPLE']);
    $this->service->save($this->request,$this->data);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    $html=\App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print',$payload+['pdfMode'=>true])->render());
    if (PHP_OS_FAMILY==='Windows') $html=preg_replace('~file:///([A-Za-z]:/)~','file://$1',$html);
    $temp=storage_path('framework/testing/g12-pdf');
    \Illuminate\Support\Facades\File::ensureDirectoryExists($temp);
    $pdf=new \Dompdf\Dompdf(['chroot'=>[public_path(),resource_path('css'),$temp],'tempDir'=>$temp,'fontDir'=>$temp,'fontCache'=>$temp]);
    $pdf->loadHtml($html); $pdf->setPaper('a4','landscape');
    \App\Support\G12CertificatePdfLayout::configure($pdf);
    $prepare=$pdf->getCallbacks()['begin_page_reflow'][0];
    $names=[];
    $pdf->setCallbacks([
        ['event'=>'begin_page_reflow','f'=>$prepare],
        ['event'=>'begin_frame','f'=>static function($frame,$canvas,$fonts) use(&$names) {
            if (!$frame->is_text_node() || trim($frame->get_node()->textContent)==='') return;
            $parent=$frame->get_parent()->get_parent();$node=$parent->get_node();
            if (!$node instanceof DOMElement || !str_contains($node->getAttribute('class'),'g12-student-name')) return;
            $style=$parent->get_style();
            $names[]=['width'=>$frame->get_margin_width(),'available'=>(float)$style->length_in_pt($style->width,$canvas->get_width())];
        }],
    ]);
    $pdf->render();
    expect($pdf->getCanvas()->get_page_count())->toBe(4)->and($names)->toHaveCount(4);
    foreach($names as $name) expect($name['width'])->toBeLessThanOrEqual($name['available']+.1);
});

it('saves through the report route and prints the stored certificate data', function () {
    $this->actingAs($this->request->user())->withoutMiddleware();
    $this->post(route('reports.g12-certificates.save'),array_replace($this->data,['number_prefix'=>'26']))
        ->assertRedirect(route('reports.index',['type'=>'g12-certificate-wis','academic_year_id'=>1]));
    $this->post(route('reports.g12-certificates.save'),array_replace($this->data,['action'=>'update_prefix']))
        ->assertRedirect(route('reports.index',['type'=>'g12-certificate-wis','academic_year_id'=>1]));
    $this->get(route('reports.show','g12-certificate-wis').'?academic_year_id=1&certificate_student_id=3')
        ->assertOk()->assertSee('ALICE')->assertSee('26G12001')->assertSee('15th day of August 2026');
});

it('exports selected G12 content through the PDF endpoint and blocks export before assignment', function () {
    $this->actingAs($this->request->user())->withoutMiddleware();
    $url = route('reports.pdf','g12-certificate-wis').'?academic_year_id=1&certificate_student_id=3&certificate_show_qr=1';
    $this->get($url)->assertStatus(422);
    $this->service->save($this->request,$this->data);
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type','application/pdf');
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        expect(file_get_contents($path,false,null,0,5))->toBe('%PDF-');
    } finally {
        unlink($path);
    }
    $this->get(route('reports.excel','g12-certificate-wis').'?academic_year_id=1')->assertNotFound();
});

it('does not include summer enrollments or accept an invalid given date', function () {
    DB::table('tb_academic_year')->where('id',1)->update(['period_type'=>'summer']);
    expect($this->service->rows($this->request,1))->toBeEmpty();
    $this->actingAs($this->request->user())->withoutMiddleware()->withoutExceptionHandling();
    expect(fn()=>$this->post(route('reports.g12-certificates.save'),array_replace($this->data,['given_date'=>'2026-02-30'])))
        ->toThrow(ValidationException::class);
    expect(G12CertificateYear::count())->toBe(0);
});

it('saves fonts per year without changing issued numbers, dates or prefixes and restores defaults', function () {
    $this->service->save($this->request,$this->data);
    $this->service->save($this->request,array_replace($this->data,['academic_year_id'=>2,'number_prefix'=>'27G12','given_date'=>'2027-08-15']));
    $numbers=G12Certificate::orderBy('id')->get()->toArray();
    $style=\App\Support\G12CertificateTypography::resolve(null);
    $style['student_name']=['font'=>'battambang','size'=>20,'color'=>'#123456'];
    $style['heading']=['font'=>'muol_light','size'=>18,'color'=>'#654321'];
    $this->actingAs($this->request->user())->withoutMiddleware();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'save_style','typography'=>$style])->assertRedirect();
    $year=G12CertificateYear::where('academic_year_id',1)->first();
    expect($year->typography['student_name'])->toBe($style['student_name'])
        ->and($year->given_date->toDateString())->toBe('2026-08-15')->and($year->number_prefix)->toBe('26G12')
        ->and(G12Certificate::orderBy('id')->get()->toArray())->toBe($numbers)
        ->and(G12CertificateYear::where('academic_year_id',2)->value('typography'))->toBeNull();
    $html=view('reports.g12-certificate-print',$this->service->payload($this->request,['academic_year_id'=>1])+['pdfMode'=>true])->render();
    expect($html)->toContain('data-g12-font-family="G12 Battambang"','data-g12-font-color="#123456"','data-g12-font-family="G12 Muol Light"','data-g12-font-color="#654321"')
        ->not->toContain('<img');
    $pdfHtml=\App\Support\G12CertificatePdfLayout::prepareHtml($html);
    expect($pdfHtml)->toContain('font-family:G12 Battambang;font-size:20pt;color:#123456','font-family:G12 Muol Light;font-size:18pt;color:#654321');
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'reset_style'])->assertRedirect();
    expect($year->fresh()->typography)->toBeNull()->and(G12Certificate::orderBy('id')->get()->toArray())->toBe($numbers);
});

it('rejects unsupported font names, CSS colors, and out of range sizes without changing fonts', function () {
    $this->service->save($this->request,$this->data);
    $this->actingAs($this->request->user())->withoutMiddleware()->withoutExceptionHandling();
    $style=\App\Support\G12CertificateTypography::resolve(null);
    $style['heading']=['font'=>'url(evil)','size'=>1000,'color'=>'red;display:none'];
    try {
        $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'save_style','typography'=>$style]);
        $this->fail('Invalid certificate fonts should be rejected.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toContain('typography.heading.font','typography.heading.size','typography.heading.color');
    }
    expect(G12CertificateYear::first()->typography)->toBeNull();
});

it('requires the edit template permission for legacy year wide font edits', function () {
    $this->service->save($this->request,$this->data);
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>DB::table('tb_school_info')->where('id',2));
    $this->request->setUserResolver(fn()=>$user);
    expect(fn()=>$this->service->save($this->request,['academic_year_id'=>1,'action'=>'save_style','typography'=>\App\Support\G12CertificateTypography::resolve(null)]))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(G12CertificateYear::first()->typography)->toBeNull();
});

it('fits long names in PDF for every supported certificate font', function () {
    $name='WILLIAM ALEXANDER BENJAMIN CHRISTOPHER EXAMPLE';
    $settings=new G12CertificateYear(['given_date'=>'2026-08-15','number_prefix'=>'26G12']);
    $certificate=(object)['full_name_en'=>$name,'class_name'=>'A','campus_name_en'=>'BCH','certificate_number'=>'26G12001'];
    $temp=storage_path('framework/testing/g12-pdf');
    \Illuminate\Support\Facades\File::ensureDirectoryExists($temp);
    foreach (\App\Support\G12CertificateTypography::fonts() as $font=>$definition) {
        $styles=\App\Support\G12CertificateTypography::resolve(null);
        $styles['student_name']=['font'=>$font,'size'=>36,'color'=>'#123456'];
        $styles['heading']['font']=$font;
        $settings->typography=$styles;
        $html=\App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print',['certificates'=>collect([$certificate]),'certificateSettings'=>$settings,'pdfMode'=>true])->render());
        $pdf=new \Dompdf\Dompdf(['chroot'=>[public_path(),resource_path('css'),$temp],'tempDir'=>$temp,'fontDir'=>$temp,'fontCache'=>$temp]);
        $pdf->loadHtml($html); $pdf->setPaper('a4','landscape');
        \App\Support\G12CertificatePdfLayout::configure($pdf);
        $prepare=$pdf->getCallbacks()['begin_page_reflow'][0]; $bounds=[]; $headingFonts=[];
        $pdf->setCallbacks([
            ['event'=>'begin_page_reflow','f'=>$prepare],
            ['event'=>'begin_frame','f'=>static function($frame,$canvas,$fonts) use(&$bounds,&$headingFonts) {
                if (!$frame->is_text_node() || trim($frame->get_node()->textContent)==='') return;
                $line=$frame->get_parent(); $parent=$line->get_parent(); $node=$parent->get_node();
                if ($node instanceof DOMElement && str_contains($node->getAttribute('class'),'g12-certifies')) $headingFonts[]=$parent->get_style()->font_family;
                if (!$node instanceof DOMElement || !str_contains($node->getAttribute('class'),'g12-student-name')) return;
                $bounds[]=['width'=>$frame->get_margin_width(),'available'=>(float)$parent->get_style()->length_in_pt($parent->get_style()->width,$canvas->get_width()),'font'=>$line->get_style()->font_family];
            }],
        ]);
        $pdf->render();
        expect($pdf->getCanvas()->get_page_count())->toBe(1)->and($bounds)->toHaveCount(1)
            ->and($bounds[0]['width'])->toBeLessThanOrEqual($bounds[0]['available']+.1);
        if (isset($definition['file'])) {
            expect(basename($bounds[0]['font']))->toStartWith(str_replace(' ', '_', strtolower($definition['family'])).'_normal_');
            expect($headingFonts)->toHaveCount(1)
                ->and(basename($headingFonts[0]))->toStartWith(str_replace(' ', '_', strtolower($definition['family'])).'_normal_');
        }
    }
});

it('saves a shared editable template and reuses it with each years student data and dates', function () {
    $this->service->save($this->request,$this->data);
    $this->service->save($this->request,array_replace($this->data,['academic_year_id'=>2,'number_prefix'=>'27G12','given_date'=>'2027-09-12']));
    $numbers=G12Certificate::orderBy('id')->get()->toArray();
    $dates=G12CertificateYear::orderBy('id')->get()->toArray();
    $layout=\App\Support\G12CertificateLayout::defaults();
    $layout['fields']['heading']=array_replace($layout['fields']['heading'],['text'=>'Certificate of Completion','font'=>'old_english','color'=>'#123456','x'=>5,'y'=>38,'width'=>90]);
    $layout['fields']['student_name']['text']='Awarded to {{student_name}}';
    $layout['fields']['completion']['text']="Completed Kindergarten\nWestern International School";
    $layout['fields']['photo']=['x'=>45,'y'=>70,'width'=>8,'height'=>15];
    $this->actingAs($this->request->user())->withoutMiddleware();
    $this->post(route('reports.g12-certificates.template'),['template_data'=>json_encode($layout),'template_version'=>0,'academic_year_id'=>1])->assertRedirect();
    $template=\App\Models\G12CertificateTemplate::find(1);
    expect($template->version)->toBe(1)->and($template->layout['fields']['heading']['text'])->toBe('Certificate of Completion')
        ->and(G12Certificate::orderBy('id')->get()->toArray())->toBe($numbers)->and(G12CertificateYear::orderBy('id')->get()->toArray())->toBe($dates);
    $first=$this->service->payload($this->request,['academic_year_id'=>1]);
    $future=$this->service->payload($this->request,['academic_year_id'=>2]);
    expect($future['certificateLayout'])->toBe($first['certificateLayout']);
    $html=view('reports.g12-certificate-print',$first+['pdfMode'=>true])->render();
    expect($html)->toContain('Certificate of Completion','Awarded to ALICE','15th day of August 2026','26G12001','data-g12-x="5"','data-g12-y="38"')->not->toContain('<img');
    $html=view('reports.g12-certificate-print',$future+['pdfMode'=>true])->render();
    expect($html)->toContain('Awarded to OTHER YEAR','12th day of September 2027','27G12001');
    $prepared=\App\Support\G12CertificatePdfLayout::prepareHtml($html);
    expect($prepared)->toContain('left:5%;top:38%;width:90%','font-family:G12 Old English Text MT','height:15%');
});

it('rejects stale template saves without overwriting another users changes', function () {
    $layout=\App\Support\G12CertificateLayout::defaults();
    $layout['fields']['heading']['text']='First saved template';
    $this->service->saveTemplate($this->request,$layout,0);
    $layout['fields']['heading']['text']='Stale replacement';
    expect(fn()=>$this->service->saveTemplate($this->request,$layout,0))->toThrow(ValidationException::class);
    expect(\App\Models\G12CertificateTemplate::find(1)->layout['fields']['heading']['text'])->toBe('First saved template');
});

it('requires the edit template permission to edit the shared future certificate template', function () {
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>DB::table('tb_school_info')->where('id',2));
    $this->request->setUserResolver(fn()=>$user);
    expect($this->service->canManageTemplate($this->request))->toBeFalse();
    expect(fn()=>$this->service->saveTemplate($this->request,\App\Support\G12CertificateLayout::defaults(),0))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(\App\Models\G12CertificateTemplate::find(1)->version)->toBe(0);
});

it('validates template fonts positions and placeholders and renders wording as plain text', function () {
    $layout=\App\Support\G12CertificateLayout::defaults();
    $layout['fields']['heading']=array_replace($layout['fields']['heading'],['x'=>-5,'font'=>'url(evil)','text'=>'{{unknown_value}}']);
    $this->actingAs($this->request->user())->withoutMiddleware()->withoutExceptionHandling();
    try {
        $this->post(route('reports.g12-certificates.template'),['template_data'=>json_encode($layout),'template_version'=>0]);
        $this->fail('Invalid template should be rejected.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toContain('fields.heading.x','fields.heading.font','fields.heading.text');
    }
    expect(\App\Models\G12CertificateTemplate::find(1)->version)->toBe(0);
    $layout=\App\Support\G12CertificateLayout::defaults();
    $layout['fields']['heading']['text']='<script>alert(1)</script>';
    $this->service->saveTemplate($this->request,$layout,0);
    $this->service->save($this->request,$this->data);
    $html=view('reports.g12-certificate-print',$this->service->payload($this->request,['academic_year_id'=>1])+['pdfMode'=>true])->render();
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>');
});

it('paginates only the certificate list while retaining every student for preview totals and export', function ($size, $requestedPage, $page, $count, $from, $to, $lastPage) {
    $students = $enrollments = [];
    for ($id = 9; $id <= 128; $id++) {
        $students[] = ['id'=>$id, 'student_id'=>'ID-'.$id, 'full_name_en'=>'PAGE STUDENT '.str_pad((string) ($id-8), 3, '0', STR_PAD_LEFT)];
        $enrollments[] = ['id'=>$id, 'student_id'=>$id, 'academic_year_id'=>1, 'campus_id'=>2, 'grade_id'=>1, 'class_id'=>1];
    }
    DB::table('tb_student')->insert($students);
    DB::table('tb_student_enrollment')->insert($enrollments);
    DB::table('tb_student_enrollment')->insert(['id'=>200, 'student_id'=>9, 'academic_year_id'=>1, 'campus_id'=>2, 'grade_id'=>1, 'class_id'=>1]);
    $filters = ['academic_year_id'=>1, 'preview_page'=>$requestedPage];
    if ($size !== null) $filters['preview_page_size'] = $size;
    $payload = $this->service->payload($this->request, $filters, true);
    $all = $payload['certificates'];
    $pagination = $payload['certificatePagination'];
    expect($all)->toHaveCount(124)->and($payload['certificateStudents'])->toHaveCount(124)
        ->and($payload['certificatePreviewRows'])->toHaveCount($count)
        ->and($pagination)->toMatchArray(['page'=>$page, 'total'=>124, 'from'=>$from, 'to'=>$to, 'lastPage'=>$lastPage, 'sizes'=>['all','30','50','75','100']])
        ->and($payload['certificatePreviewRows']->pluck('student_id')->all())->toBe($all->slice($from-1,$count)->pluck('student_id')->all())
        ->and($payload['filters']['preview_page'])->toBe($page)
        ->and($payload['filters']['preview_page_size'])->toBe(in_array($size, G12CertificateReport::PREVIEW_SIZES, true) ? $size : '30');
    $export = $this->service->payload($this->request, $filters);
    expect($export['certificates'])->toHaveCount(124)->and($export['certificatePagination'])->toBeNull();
})->with([
    'default 30' => [null, 1, 1, 30, 1, 30, 5],
    'all resets page' => ['all', 99, 1, 124, 1, 124, 1],
    '30 clamps to final page' => ['30', 99, 5, 4, 121, 124, 5],
    '50 second page' => ['50', 2, 2, 50, 51, 100, 3],
    '75 final page' => ['75', 2, 2, 49, 76, 124, 2],
    '100 final page' => ['100', 2, 2, 24, 101, 124, 2],
    'unsupported size falls back' => ['25', 1, 1, 30, 1, 30, 5],
]);

it('paginates after applying campus class student and access filters and handles empty results', function () {
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('hasPermission')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>DB::table('tb_school_info')->where('id',2));
    $this->request->setUserResolver(fn()=>$user);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>2,'grade_class'=>'1:1','certificate_student_id'=>2,'preview_page'=>999],true);
    expect($payload['certificatePagination'])->toMatchArray(['total'=>1,'from'=>1,'to'=>1,'page'=>1,'lastPage'=>1])
        ->and($payload['certificatePreviewRows']->pluck('student_id')->all())->toBe([2])
        ->and($payload['certificateStudents'])->toHaveCount(2);
    $empty=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>1,'preview_page'=>999],true);
    expect($empty['certificatePagination'])->toMatchArray(['total'=>0,'from'=>0,'to'=>0,'page'=>1,'lastPage'=>1])
        ->and($empty['certificates'])->toBeEmpty()->and($empty['certificatePreviewRows'])->toBeEmpty();
});

it('renders the shared student list pagination with G12 sizes and preserved filters', function () {
    $request=Request::create('/reports?type=g12-certificate-wis&academic_year_id=1&campus_id=2&grade_class=1%3A1&preview_page=2');
    $request->setUserResolver(fn()=>$this->request->user());
    $this->app->instance('request',$request);
    $pagination=['page'=>2,'pageSize'=>'30','perPage'=>30,'total'=>92,'from'=>31,'to'=>60,'lastPage'=>4,'sizes'=>G12CertificateReport::PREVIEW_SIZES];
    $html=view('reports._preview-pagination',['pagination'=>$pagination])->render();
    $dom=new DOMDocument;@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    $options=$xpath->query('//select[@data-preview-page-size]/option');$sizes=[];
    foreach($options as $option) $sizes[]=$option->getAttribute('value');
    expect($sizes)->toBe(['all','30','50','75','100'])
        ->and($xpath->query('//option[@selected]')->item(0)->getAttribute('value'))->toBe('30')
        ->and($html)->toContain('premium-pagination','Showing <strong>31 to 60</strong>','of <strong>92 entries</strong>','data-preview-goto');
    foreach($options as $option) {
        parse_str(parse_url($option->getAttribute('data-url'),PHP_URL_QUERY),$query);
        expect($query)->toMatchArray(['type'=>'g12-certificate-wis','academic_year_id'=>'1','campus_id'=>'2','grade_class'=>'1:1','preview_page'=>'1','preview_page_size'=>$option->getAttribute('value')]);
    }
    $pagination['pageSize']='all';$pagination['page']=1;$pagination['lastPage']=1;
    expect(view('reports._preview-pagination',['pagination'=>$pagination])->render())->not->toContain('data-preview-goto');
    $pagination['pageSize']='25';$pagination['sizes']=['all','25','50','75','100'];
    $studentList=view('reports._preview-pagination',['pagination'=>$pagination])->render();
    expect($studentList)->toContain('value="25"')->not->toContain('value="30"');
});

it('accepts G12 pagination at the report controller and never limits the print document', function () {
    $this->service->save($this->request,$this->data);
    $request=Request::create('/reports?academic_year_id=1&preview_page_size=30&preview_page=2');
    $request->setUserResolver(fn()=>$this->request->user());
    $controller=new \App\Http\Controllers\ReportsController;
    $preview=(new ReflectionMethod($controller,'reportPayload'))->invoke($controller,$request,'g12-certificate-wis',true);
    expect($preview['certificatePagination']['pageSize'])->toBe('30')->and($preview['certificatePagination']['total'])->toBe(4);
    $print=$controller->show($request,'g12-certificate-wis');
    expect($print->getData()['certificates'])->toHaveCount(4)->and($print->getData()['certificatePagination'])->toBeNull();
});

it('exposes four separate permissions under Reports without granting non-super roles automatically', function () {
    g12CertificatePermissionUser();
    $permissions=\App\Models\Permission::all();
    $codes=array_keys(G12CertificatePermissions::catalog());
    $tree=\App\Support\PermissionHierarchy::tree($permissions);
    expect($tree['reports']['modules']['reports.g12']['actions']->pluck('code')->all())->toBe($codes)
        ->and(DB::table('access_role_permissions')->where('role_id',2)->count())->toBe(0)
        ->and(DB::table('access_role_permissions')->where('role_id',1)->count())->toBe(4);
    $ids=$permissions->whereIn('code',$codes)->modelKeys();
    expect(\App\Support\PermissionHierarchy::normalizeIds($ids,$permissions))->toBe([]);
    $ids[]=$permissions->firstWhere('code','reports.view')->id;
    $ids[]=$permissions->firstWhere('code','reports.g12.view')->id;
    expect(\App\Support\PermissionHierarchy::normalizeIds($ids,$permissions))->toHaveCount(6);
    $html=view('partials.permission-tree',['permissionHierarchy'=>$tree,'permissionPrefix'=>'test','assignedPermissions'=>collect()])->render();
    foreach(G12CertificatePermissions::catalog() as $code=>$name) expect($html)->toContain($code,$name);
});

it('allows a granted Given Date permission from user role or department without allowing other actions', function ($source) {
    $this->service->save($this->request,array_replace($this->data,['action'=>'save_date']));
    $user=g12CertificatePermissionUser([G12CertificatePermissions::SAVE_GIVEN_DATE],$source);
    $this->request->setUserResolver(fn()=>$user);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    expect($payload['canSaveGivenDate'])->toBeTrue()->and($payload['canAssignCertificateNumbers'])->toBeFalse()
        ->and($payload['canEditCertificatePrefix'])->toBeFalse()->and($payload['canManageCertificateTemplate'])->toBeFalse()
        ->and($payload['certificates'])->toHaveCount(3);
    $this->actingAs($user)->withoutMiddleware();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'save_date','given_date'=>'2026-09-12'])->assertRedirect();
    expect(G12CertificateYear::first()->given_date->toDateString())->toBe('2026-09-12')->and(G12CertificateYear::first()->number_prefix)->toBe('26G12');
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'assign'])->assertForbidden();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'update_prefix','number_prefix'=>'X'])->assertForbidden();
    $this->post(route('reports.g12-certificates.template'),['template_data'=>json_encode(\App\Support\G12CertificateLayout::defaults()),'template_version'=>0])->assertForbidden();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'save_date','given_date'=>'2026-10-06','number_prefix'=>'X'])->assertForbidden();
    expect(G12Certificate::count())->toBe(0)->and(G12CertificateYear::first()->given_date->toDateString())->toBe('2026-09-12')
        ->and(G12CertificateYear::first()->number_prefix)->toBe('26G12');
    $html=view('reports._g12-certificate-settings',$payload)->render();
    expect($html)->toContain('value="save_date"')->not->toContain('value="assign"','value="update_prefix"');
})->with(['user','role','department']);

it('allows assignment with saved settings but blocks forged date or prefix edits without their permissions', function () {
    $this->service->save($this->request,array_replace($this->data,['action'=>'save_date']));
    $user=g12CertificatePermissionUser([G12CertificatePermissions::ASSIGN_NUMBERS]);
    $this->request->setUserResolver(fn()=>$user);
    $this->actingAs($user)->withoutMiddleware();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'assign'])->assertRedirect();
    expect(G12Certificate::orderBy('sequence')->pluck('student_id')->all())->toBe([3,2,4,1]);
    $before=G12Certificate::orderBy('id')->get()->toArray();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'assign','given_date'=>'2027-01-01'])->assertForbidden();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'assign','number_prefix'=>'OTHER'])->assertForbidden();
    expect(G12Certificate::orderBy('id')->get()->toArray())->toBe($before)
        ->and(G12CertificateYear::first()->given_date->toDateString())->toBe('2026-08-15')->and(G12CertificateYear::first()->number_prefix)->toBe('26G12');
});

it('supports initial setup by separately authorized date prefix and numbering actions', function () {
    $user=g12CertificatePermissionUser([G12CertificatePermissions::SAVE_GIVEN_DATE]);
    $this->actingAs($user)->withoutMiddleware();
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'save_date','given_date'=>'2026-08-15'])->assertRedirect();
    expect(G12CertificateYear::first()->number_prefix)->toBe('')->and(G12Certificate::count())->toBe(0);
    $grant=function($code) use ($user) {
        $ids=DB::table('access_permissions')->whereIn('code',['reports.view',$code])->pluck('id');
        $user->permissionOverrides()->sync($ids->mapWithKeys(fn($id)=>[$id=>['allowed'=>true]])->all());
    };
    $grant(G12CertificatePermissions::EDIT_PREFIX);
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'update_prefix','number_prefix'=>'26G12'])->assertRedirect();
    expect(G12CertificateYear::first()->number_prefix)->toBe('26G12')->and(G12Certificate::count())->toBe(0);
    $grant(G12CertificatePermissions::ASSIGN_NUMBERS);
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'assign'])->assertRedirect();
    expect(G12Certificate::count())->toBe(4);
    $issued=G12Certificate::orderBy('sequence')->get(['id','student_id','sequence'])->toArray();
    $grant(G12CertificatePermissions::EDIT_PREFIX);
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'update_prefix','number_prefix'=>'CUSTOM-'])->assertRedirect();
    expect(G12Certificate::orderBy('sequence')->get(['id','student_id','sequence'])->toArray())->toBe($issued)
        ->and(G12CertificateYear::first()->given_date->toDateString())->toBe('2026-08-15')
        ->and(G12Certificate::orderBy('sequence')->pluck('certificate_number')->all())->toBe(['CUSTOM-001','CUSTOM-002','CUSTOM-003','CUSTOM-004']);
    $grant('reports.view');
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'update_prefix','number_prefix'=>'BLOCKED'])->assertForbidden();
    expect(G12CertificateYear::first()->number_prefix)->toBe('CUSTOM-');
});

it('allows an explicitly granted shared template edit without broad campus access', function () {
    $this->service->save($this->request,$this->data);
    $before=G12Certificate::orderBy('id')->get()->toArray();$date=G12CertificateYear::first()->given_date->toDateString();
    $user=g12CertificatePermissionUser([G12CertificatePermissions::EDIT_TEMPLATE]);
    $this->request->setUserResolver(fn()=>$user);$this->actingAs($user)->withoutMiddleware();
    $layout=\App\Support\G12CertificateLayout::defaults();$layout['fields']['heading']['text']='Authorized template';
    $this->post(route('reports.g12-certificates.template'),['template_data'=>json_encode($layout),'template_version'=>0])->assertRedirect();
    expect(\App\Models\G12CertificateTemplate::find(1)->layout['fields']['heading']['text'])->toBe('Authorized template')
        ->and(G12Certificate::orderBy('id')->get()->toArray())->toBe($before)->and(G12CertificateYear::first()->given_date->toDateString())->toBe($date);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    expect($payload['canManageCertificateTemplate'])->toBeTrue()->and($payload['canManageCertificates'])->toBeFalse()
        ->and(view('reports._g12-certificate-editor',$payload)->render())->toContain('data-g12-edit-toggle','data-g12-save-template');
    $this->post(route('reports.g12-certificates.save'),['academic_year_id'=>1,'action'=>'assign'])->assertForbidden();
});

it('does not treat global campus access or a different campus role grant as action permission', function () {
    $user=g12CertificatePermissionUser([G12CertificatePermissions::ASSIGN_NUMBERS],'role');
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>1]);
    $user->is_global=true;$user->save();
    $this->request->setUserResolver(fn()=>$user);$this->request->query->set('campus_id',1);
    expect($this->service->canPerformAction($this->request,'assign'))->toBeFalse()
        ->and($this->service->canManageTemplate($this->request))->toBeFalse();
    expect(fn()=>$this->service->save($this->request,$this->data))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(G12CertificateYear::count())->toBe(0);
});

it('keeps G12 action grants explicit when the permission catalog seeder runs again', function () {
    g12CertificatePermissionUser([G12CertificatePermissions::EDIT_PREFIX],'role');
    (new \Database\Seeders\AccessFoundationSeeder)->run();
    $codes=array_keys(G12CertificatePermissions::catalog());
    expect(\App\Models\Role::where('code','registrar')->first()->permissions()->whereIn('code',$codes)->pluck('code')->all())->toBe([G12CertificatePermissions::EDIT_PREFIX])
        ->and(\App\Models\Role::where('code','teacher')->first()->permissions()->whereIn('code',$codes)->count())->toBe(0)
        ->and(\App\Models\Role::where('code','super-admin')->first()->permissions()->whereIn('code',$codes)->count())->toBe(4);
});

it('fits edited multiline content with the same font size on every line in a single PDF page', function () {
    $this->service->save($this->request,$this->data);
    $layout=\App\Support\G12CertificateLayout::defaults();
    $layout['fields']['completion']=array_replace($layout['fields']['completion'],[
        'text'=>"has successfully completed Kindergarten at Western International School\nSchool",
        'font'=>'maiandra','size'=>24,'x'=>35,'y'=>53,'width'=>25,
    ]);
    $this->service->saveTemplate($this->request,$layout,0);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1,'certificate_student_id'=>3]);
    $html=\App\Support\G12CertificatePdfLayout::prepareHtml(view('reports.g12-certificate-print',$payload+['pdfMode'=>true])->render());
    $temp=storage_path('framework/testing/g12-pdf');
    \Illuminate\Support\Facades\File::ensureDirectoryExists($temp);
    $pdf=new \Dompdf\Dompdf(['chroot'=>[public_path(),resource_path('css'),$temp],'tempDir'=>$temp,'fontDir'=>$temp,'fontCache'=>$temp]);
    $pdf->loadHtml($html);$pdf->setPaper('a4','landscape');\App\Support\G12CertificatePdfLayout::configure($pdf);
    $prepare=$pdf->getCallbacks()['begin_page_reflow'][0];$sizes=[];
    $pdf->setCallbacks([
        ['event'=>'begin_page_reflow','f'=>$prepare],
        ['event'=>'begin_frame','f'=>static function($frame) use (&$sizes) {
            if (!$frame->is_text_node() || trim($frame->get_node()->textContent)==='') return;
            $line=$frame->get_parent();$block=$line->get_parent()->get_node();
            if ($block instanceof DOMElement && $block->getAttribute('data-g12-layout-key')==='completion') $sizes[]=$line->get_style()->font_size;
        }],
    ]);
    $pdf->render();
    expect($pdf->getCanvas()->get_page_count())->toBe(1)->and($sizes)->toHaveCount(2)
        ->and($sizes[0])->toBeLessThan(24)->and(abs($sizes[0]-$sizes[1]))->toBeLessThan(.01);
});
