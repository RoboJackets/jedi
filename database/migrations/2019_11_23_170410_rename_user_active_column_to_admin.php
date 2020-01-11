<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameUserActiveColumnToAdmin extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->renameColumn('active', 'admin');
        });

        Schema::table('users', static function (Blueprint $table): void {
            $table->boolean('admin')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->renameColumn('admin', 'active');
        });

        Schema::table('users', static function (Blueprint $table): void {
            $table->boolean('active')->default(true)->change();
        });
    }
}
