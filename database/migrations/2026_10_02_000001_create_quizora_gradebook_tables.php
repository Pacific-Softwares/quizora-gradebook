<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shareable report cards: one private link per creator and student. Grades themselves are
// never copied: they're read live from Quizora's attempts, so they're always current.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizora_gradebook_report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->text('note')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['creator_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizora_gradebook_report_cards');
    }
};
