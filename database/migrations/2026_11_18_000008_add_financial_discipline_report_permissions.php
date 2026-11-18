<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        \App\Support\AdditionalMenuPermissions::registerMissing();
    }

    public function down(): void
    {
        // Preserve permission assignments on rollback.
    }
};
