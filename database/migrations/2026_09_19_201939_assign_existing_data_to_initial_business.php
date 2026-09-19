<?php

use App\Models\Business;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $business = Business::create([
                'name' => 'Existing Inventory Business',
            ]);

            DB::table('users')->update([
                'business_id' => $business->id,
            ]);

            DB::table('categories')->update([
                'business_id' => $business->id,
            ]);

            DB::table('products')->update([
                'business_id' => $business->id,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            $business = Business::where(
                'name',
                'Existing Inventory Business'
            )->first();

            if (! $business) {
                return;
            }

            DB::table('products')
                ->where('business_id', $business->id)
                ->update(['business_id' => null]);

            DB::table('categories')
                ->where('business_id', $business->id)
                ->update(['business_id' => null]);

            DB::table('users')
                ->where('business_id', $business->id)
                ->update(['business_id' => null]);

            $business->delete();
        });
    }
};