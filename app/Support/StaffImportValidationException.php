<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StaffImportValidationException extends ValidationException
{
    public function __construct(array $messages,public readonly array $suggestions)
    {
        $validator=Validator::make([],[]);
        foreach($messages as $field=>$items) foreach($items as $message) $validator->errors()->add($field,$message);
        parent::__construct($validator);
    }
}
