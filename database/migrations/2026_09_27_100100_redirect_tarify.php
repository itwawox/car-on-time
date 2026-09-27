<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Redirect::query()->updateOrCreate(['from_path' => '/tarify'], ['to_path' => '/usloviya', 'status' => 301]);
    }

    public function down(): void
    {
        Redirect::query()->where('from_path', '/tarify')->delete();
    }
};
