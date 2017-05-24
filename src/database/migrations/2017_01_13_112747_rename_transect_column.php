<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameTransectColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('copria_color_sort_sequence', function (Blueprint $table) {
            $table->renameColumn('transect_id', 'volume_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('copria_color_sort_sequence', function (Blueprint $table) {
            $table->renameColumn('volume_id', 'transect_id');
        });
    }
}
