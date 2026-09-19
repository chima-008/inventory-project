<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
            $table->dropUnique('categories_slug_unique');

            $table->unique(
                ['business_id', 'name'],
                'categories_business_id_name_unique'
            );

            $table->unique(
                ['business_id', 'slug'],
                'categories_business_id_slug_unique'
            );
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_slug_unique');
            $table->dropUnique('products_sku_unique');

            $table->unique(
                ['business_id', 'slug'],
                'products_business_id_slug_unique'
            );

            $table->unique(
                ['business_id', 'sku'],
                'products_business_id_sku_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_business_id_slug_unique');
            $table->dropUnique('products_business_id_sku_unique');

            $table->unique('slug', 'products_slug_unique');
            $table->unique('sku', 'products_sku_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_business_id_name_unique');
            $table->dropUnique('categories_business_id_slug_unique');

            $table->unique('name', 'categories_name_unique');
            $table->unique('slug', 'categories_slug_unique');
        });
    }
};