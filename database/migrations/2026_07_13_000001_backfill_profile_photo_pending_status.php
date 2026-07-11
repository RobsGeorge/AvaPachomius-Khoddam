<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user') || ! Schema::hasColumn('user', 'profile_photo_status')) {
            return;
        }

        $query = DB::table('user')
            ->where('profile_photo', '!=', '')
            ->where(function ($builder) {
                $builder->whereNull('profile_photo_status')
                    ->orWhere('profile_photo_status', '');
            });

        $payload = ['profile_photo_status' => 'pending'];

        if (Schema::hasColumn('user', 'profile_photo_uploaded_at')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::table('user')
                    ->where('profile_photo', '!=', '')
                    ->where(function ($builder) {
                        $builder->whereNull('profile_photo_status')
                            ->orWhere('profile_photo_status', '');
                    })
                    ->update([
                        'profile_photo_status' => 'pending',
                        'profile_photo_uploaded_at' => DB::raw('COALESCE(profile_photo_uploaded_at, created_at, NOW())'),
                    ]);

                return;
            }

            foreach ($query->get(['user_id', 'created_at', 'profile_photo_uploaded_at']) as $user) {
                $payload['profile_photo_uploaded_at'] = $user->profile_photo_uploaded_at ?? $user->created_at ?? now();
                DB::table('user')->where('user_id', $user->user_id)->update($payload);
            }

            return;
        }

        $query->update($payload);
    }

    public function down(): void
    {
        // Data backfill only.
    }
};
