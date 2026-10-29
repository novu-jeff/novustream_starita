<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('application_no')->nullable();

            $table->string('cellphone');
            $table->string('applicant_name');
            $table->text('service_address');

            $table->enum('application_type',[
                'Water Service Connection',
                'Relocation of Service Line',
                'Replacement of Pipe',
                'Others'
            ]);

            $table->string('application_type_other')->nullable();

            $table->string('connection_size')->nullable();

            $table->text('installation_location');

            $table->text('agreement')->nullable();

            $table->string('property_owner');

            $table->boolean('promissory_note')->default(false);

            $table->decimal('promissory_amount',10,2)->nullable();

            $table->enum('status',[
                'Pending',
                'For Inspection',
                'Approved',
                'Rejected',
                'Installed'
            ])->default('Pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_applications');
    }
};
