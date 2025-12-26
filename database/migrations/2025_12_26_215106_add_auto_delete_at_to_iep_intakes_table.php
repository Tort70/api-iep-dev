<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('iep_intakes', function (Blueprint $table) {
            $table->timestamp('auto_delete_at')->nullable()->after('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('iep_intakes', function (Blueprint $table) {
            $table->dropColumn('auto_delete_at');
        });
    }
};
