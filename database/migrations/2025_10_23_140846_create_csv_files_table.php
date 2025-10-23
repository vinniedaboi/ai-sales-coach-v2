<?php

// database/migrations/xxxx_xx_xx_create_csv_files_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name'); // The file name the user uploaded
            $table->string('stored_path')->unique(); // The unique path in Laravel storage
            $table->unsignedBigInteger('user_id')->nullable(); // Optional: Link to a user
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_files');
    }
};
