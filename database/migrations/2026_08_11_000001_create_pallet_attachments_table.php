<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pallet_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')
                ->constrained('pallets')
                ->onDelete('cascade');
            $table->enum('type', ['photo', 'document', 'signature', 'receipt', 'other']);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('pallet_id');
            $table->index('type');
            $table->index('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_attachments');
    }
};
