<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'client_type')) {
                $table->string('client_type')->default('company')->after('name')->index();
            }

            if (! Schema::hasColumn('clients', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('client_type');
            }

            if (! Schema::hasColumn('clients', 'billing_email')) {
                $table->string('billing_email')->nullable()->after('email');
            }

            if (! Schema::hasColumn('clients', 'website')) {
                $table->string('website')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('clients', 'id_type')) {
                $table->string('id_type')->nullable()->after('tin_number')->index();
            }

            if (! Schema::hasColumn('clients', 'id_number')) {
                $table->string('id_number')->nullable()->after('id_type')->index();
            }

            if (! Schema::hasColumn('clients', 'sst_registration_number')) {
                $table->string('sst_registration_number')->nullable()->after('id_number');
            }

            if (! Schema::hasColumn('clients', 'address_line_1')) {
                $table->string('address_line_1')->nullable()->after('address');
            }

            if (! Schema::hasColumn('clients', 'address_line_2')) {
                $table->string('address_line_2')->nullable()->after('address_line_1');
            }

            if (! Schema::hasColumn('clients', 'city')) {
                $table->string('city')->nullable()->after('address_line_2');
            }

            if (! Schema::hasColumn('clients', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (! Schema::hasColumn('clients', 'postcode')) {
                $table->string('postcode', 20)->nullable()->after('state');
            }

            if (! Schema::hasColumn('clients', 'country_code')) {
                $table->string('country_code', 2)->default('MY')->after('postcode')->index();
            }

            if (! Schema::hasColumn('clients', 'payment_terms_days')) {
                $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('country_code');
            }

            if (! Schema::hasColumn('clients', 'notes')) {
                $table->text('notes')->nullable()->after('payment_terms_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'notes',
                'payment_terms_days',
                'country_code',
                'postcode',
                'state',
                'city',
                'address_line_2',
                'address_line_1',
                'sst_registration_number',
                'id_number',
                'id_type',
                'website',
                'billing_email',
                'contact_person',
                'client_type',
            ] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
