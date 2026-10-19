<?php

use App\Support\PhoneNumber;

it('formats Cambodian contact numbers for international display',function($input,$expected){
    expect(PhoneNumber::formatCambodian($input,international:true))->toBe($expected);
})->with([
    ['012898898','+855 12 898 898'],
    ['0978988987','+855 97 898 8987'],
    ['+85512898898','+855 12 898 898'],
    ['855978988987','+855 97 898 8987'],
    ['0085512898898','+855 12 898 898'],
    ['+855 97 898 8987','+855 97 898 8987'],
    ['012-898-898','+855 12 898 898'],
    ['+1 202 555 0199','+1 202 555 0199'],
    ['', ''],
    [null, ''],
]);

it('preserves local display for existing callers',function(){
    expect(PhoneNumber::formatCambodian('012898898'))->toBe('012 898 898')
        ->and(PhoneNumber::formatCambodian('0978988987'))->toBe('097 898 8987');
});
