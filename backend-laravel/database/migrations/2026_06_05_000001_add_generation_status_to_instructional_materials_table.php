<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructional_materials', function (Blueprint $table) {
            $table->string('generation_status', 20)->default('completed')->after('deactivated_at');
            $table->text('generation_error')->nullable()->after('generation_status');
        });

        // Make content nullable so pending records can be created without content
        Schema::table('instructional_materials', function (Blueprint $table) {
            $table->json('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('instructional_materials', function (Blueprint $table) {
            $table->dropColumn(['generation_status', 'generation_error']);
            $table->json('content')->nullable(false)->change();
        });
    }
};
