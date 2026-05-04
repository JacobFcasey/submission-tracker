<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Added user_id based on your SQL error
            $table->enum('status', ['Pending', 'Processing', 'Completed', 'Rejected'])->default('Pending');
            $table->text('original_file_path');
            $table->text('original_file_names')->nullable();
            $table->string('workings_file_path')->nullable();
            $table->string('workings_file_name')->nullable();
            $table->string('systems_import_file_path')->nullable();
            $table->string('systems_import_file_name')->nullable();
            $table->text('converted_eml_paths')->nullable();
            $table->text('extracted_dates')->nullable();
            $table->timestamp('system_import_date')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            // Optional: Add re-upload reason columns based on your SQL error
            $table->string('reupload_reason_type')->nullable();
            $table->text('reupload_reason_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
