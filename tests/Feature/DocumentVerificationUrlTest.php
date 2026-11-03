<?php

use App\Support\{K3CertificateQr,G9CertificateQr,G12CertificateQr};

it('uses the separate verification domain for certificate QR codes',function(){
    config(['services.document_verification_base_url'=>'https://verify.western.edu.kh/']);
    $token=str_repeat('a',40);
    foreach(['k3'=>K3CertificateQr::class,'g9'=>G9CertificateQr::class,'g12'=>G12CertificateQr::class] as $grade=>$qr){
        expect($qr::url($token))->toBe("https://verify.western.edu.kh/certificates/{$grade}/{$token}");
    }
});

it('keeps the existing domain when no verification domain is configured',function(){
    config(['services.document_verification_base_url'=>null]);
    $token=str_repeat('b',40);
    expect(G12CertificateQr::url($token))->toBe(route('g12-certificate.verify',['token'=>$token]));
});
