<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmailEventTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_event', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('last_attendance_id')
            $table->string('uid', 100);
            $table->timestamps();

            $table->index(['last_attendance_id', 'uid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_event');
    }
}
