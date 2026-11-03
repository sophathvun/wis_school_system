<?php

use App\Support\DocumentVerificationUrl;
use Illuminate\Support\Facades\Route;

beforeEach(function(){
    config(['app.url'=>'https://registrar.western.edu.kh','services.verification_url'=>'https://verify.western.edu.kh']);
});

it('blocks SIS pages and file routes on the verification hostname',function(string $path){
    $this->get('https://verify.western.edu.kh'.$path)->assertNotFound()->assertHeaderMissing('Location');
    $this->post('https://verify.western.edu.kh'.$path)->assertNotFound();
})->with(['/','/login','/setup/admin','/dashboard','/api/students','/student-enrollments','/storage/private.pdf','/up','/staff-card/example','/placement-test/example']);

it('redirects existing SIS certificate and student card links without altering their tokens',function(string $path){
    $this->get('https://registrar.western.edu.kh'.$path)->assertRedirect('https://verify.western.edu.kh'.$path);
    $this->get('https://unrecognized.example'.$path)->assertNotFound();
})->with(['/certificates/k3/'.str_repeat('a',40),'/certificates/g9/'.str_repeat('b',40),'/certificates/g12/'.str_repeat('c',40),'/student-card/SID-LEGACY123','/student-card/SID-LEGACY123/qr.svg']);

it('rejects malformed tokens and non-read methods on verification endpoints',function(){
    $this->get('https://verify.western.edu.kh/certificates/g12/123')->assertNotFound();
    $this->get('https://verify.western.edu.kh/student-card/invalid.token')->assertNotFound();
    $this->post('https://verify.western.edu.kh/certificates/g12/'.str_repeat('a',40))->assertNotFound();
});

it('prefers the canonical verification setting for all new document links',function(){
    config(['services.document_verification_base_url'=>'https://legacy.example']);
    expect(DocumentVerificationUrl::route('student-id-card.public',['qr'=>'SID-EXAMPLE']))->toBe('https://verify.western.edu.kh/student-card/SID-EXAMPLE');
});

it('blocks verification-host internal routes even with an authenticated session',function(){
    $user=new \App\Models\User();$user->id=42;$user->status=1;
    $this->actingAs($user)->get('https://verify.western.edu.kh/login')->assertNotFound();
    $this->get('https://verify.western.edu.kh/settings/users')->assertNotFound();
});

it('leaves the main application reachable and does not trust a forwarded hostname by default',function(){
    $this->get('https://registrar.western.edu.kh/up')->assertOk();
    $this->withHeaders(['X-Forwarded-Host'=>'registrar.western.edu.kh'])->get('https://verify.western.edu.kh/login')->assertNotFound();
});

it('upgrades permitted verification links to HTTPS without opening other paths',function(){
    $path='/certificates/g12/'.str_repeat('a',40);
    $this->get('http://verify.western.edu.kh'.$path)->assertRedirect('https://verify.western.edu.kh'.$path);
    $this->get('http://verify.western.edu.kh/login')->assertNotFound();
});
