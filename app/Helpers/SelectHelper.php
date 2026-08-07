<?php

namespace App\Helpers;

use App\Models\Category;
use App\Models\Unit;

class SelectHelper
{
    public static function categories()
    {
        return Category::query()
            ->select('id', 'category_name')
            ->orderBy('id', 'asc')
            ->get();
    }

    public static function units()
    {
        return Unit::query()
            ->select('id', 'unit_name')
            ->orderBy('id', 'asc')
            ->get();
    }
}
