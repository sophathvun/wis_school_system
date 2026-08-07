<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 80)->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('phone')->constrained('access_positions')->nullOnDelete();
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
            $table->string('position', 150)->nullable()->after('phone');
        });

        Schema::dropIfExists('access_positions');
    }
};
