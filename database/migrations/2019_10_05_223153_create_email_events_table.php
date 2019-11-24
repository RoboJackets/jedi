<?php

declare(strict_types=1);

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch
// phpcs:disable SlevomatCodingStandard.Namespaces.UseSpacing.IncorrectLinesCountBeforeFirstUse

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('email_events', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('last_attendance_id');
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
    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
}
