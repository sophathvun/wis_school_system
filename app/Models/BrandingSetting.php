<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class BrandingSetting extends Model
{
    protected $table = 'tb_branding_setting';

    protected $fillable = ['sidebar_logo_path', 'login_logo_path', 'favicon_path', 'footer_logo_path', 'footer_text'];

    public static function current(): self
    {
        try {
            return static::query()->first() ?? static::create(['footer_text' => 'All rights reserved.']);
        } catch (QueryException) {
            // Keep layouts renderable in isolated test/install environments before migrations run.
            return new static(['footer_text' => 'All rights reserved.']);
        }
    }
}
