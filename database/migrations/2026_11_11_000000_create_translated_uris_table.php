<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The URI of an entry in every language other than the default one (the default language's URI
 * is Statamic's own). Rebuilt on save and with `php please translations:refresh-uris`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translated_uris', function (Blueprint $table) {
            $table->id();
            $table->string('site');
            $table->string('language', 16);
            $table->string('uri', 1024);
            $table->string('entry_id');
            $table->index(['site', 'uri']);
            $table->index('entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translated_uris');
    }
};
