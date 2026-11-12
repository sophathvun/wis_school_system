<?php
namespace App\Models\Concerns;
use Illuminate\Validation\ValidationException;
trait PreservesIssuedWisCertificate
{
    protected static function bootPreservesIssuedWisCertificate(): void
    {
        static::updating(function($certificate){
            if($certificate->getRawOriginal('historical_import')!==null && ($certificate->isDirty('certificate_number')||$certificate->isDirty('historical_import')))
                throw ValidationException::withMessages(['certificate_number'=>'Imported issued certificate numbers and issue dates are permanent.']);
        });
    }
}
