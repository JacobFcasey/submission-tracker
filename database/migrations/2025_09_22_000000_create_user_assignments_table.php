<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('deadline_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Use a shorter index name
            $table->unique(
                ['user_id', 'municipality_id', 'company_id', 'deadline_date'],
                'user_assignments_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
    }
};
