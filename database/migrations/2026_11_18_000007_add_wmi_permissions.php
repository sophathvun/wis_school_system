<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void { \App\Support\WmiPermissions::registerMissing(); }
    public function down(): void { /* Preserve granted permissions on rollback. */ }
};
