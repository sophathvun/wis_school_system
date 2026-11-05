<?php

use App\Support\G9NationalExamPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {G9NationalExamPermissions::register();}
    public function down(): void {} // Retain permission assignments.
};
