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
        if (Schema::hasColumn('banner_value', 'media_type')) {
            return;
        }
        Schema::table('banner_value', function (Blueprint $table) {
            // image (mặc định, giữ hành vi cũ) | video.
            $table->string('media_type', 20)->default('image')->after('image');
            // youtube | r2 — chỉ có ý nghĩa khi media_type = video.
            $table->string('video_provider', 20)->nullable()->after('media_type');
            // youtube: URL video dán trực tiếp. r2: path trả về từ upload (giống cột image).
            $table->string('video_url', 500)->nullable()->after('video_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('banner_value', 'media_type')) {
            return;
        }
        Schema::table('banner_value', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'video_provider', 'video_url']);
        });
    }
};
