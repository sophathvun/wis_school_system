<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_branding_setting', function (Blueprint $table) {
            $table->id();
            $table->string('sidebar_logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('footer_logo_path')->nullable();
            $table->string('footer_text', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_branding_setting');
    }
};
