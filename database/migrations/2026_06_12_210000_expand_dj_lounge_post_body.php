<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_lounge_posts', function (Blueprint $table): void {
            $table->text('body')->change();
        });
    }

    public function down(): void
    {
        Schema::table('dj_lounge_posts', function (Blueprint $table): void {
            $table->string('body', 500)->change();
        });
    }
};
