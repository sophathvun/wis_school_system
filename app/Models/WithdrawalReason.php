<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalReason extends Model
{
    protected $table = 'tb_withdrawal_reason';
    protected $fillable = ['reason_key', 'name_en', 'name_kh', 'sort_order', 'status'];
    protected $casts = ['status' => 'boolean'];
}
