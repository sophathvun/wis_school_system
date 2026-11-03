<?php

namespace App\Support;

class DocumentVerificationUrl
{
    public static function route(string $name, array $parameters): string
    {
        $base=trim((string)(config('services.verification_url') ?: config('services.document_verification_base_url')));
        return $base!==''
            ? rtrim($base,'/').'/'.ltrim(route($name,$parameters,false),'/')
            : route($name,$parameters);
    }
}
