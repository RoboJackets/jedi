<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameUserActiveColumnToAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('active', 'admin');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('admin')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('admin', 'active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->change();
        });
    }
}
