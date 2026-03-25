<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id('attendeeId');
            $table->unsignedBigInteger('eventId');
            $table->string('uniqueId')->nullable()->index();
            $table->string('fullName');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('organization')->nullable();
            $table->string('gender')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            $table->string('ward')->nullable();
            $table->string('community')->nullable();
            $table->string('religion')->nullable();
            $table->string('bank')->nullable();
            $table->string('accountName')->nullable();
            $table->string('accountNumber')->nullable();
            $table->string('photoUrl')->nullable();
            $table->string('accomodation')->nullable();
            $table->string('color')->nullable();
            $table->boolean('isRegistered')->default(false);
            $table->timestamp('registeredAt')->nullable();
            $table->unsignedBigInteger('registeredBy')->nullable();
            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};