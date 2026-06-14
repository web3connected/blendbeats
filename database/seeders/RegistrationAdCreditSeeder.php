<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserAdCreditService;
use Illuminate\Database\Seeder;

class RegistrationAdCreditSeeder extends Seeder
{
    public function run(): void
    {
        $credits = app(UserAdCreditService::class);

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($credits): void {
                foreach ($users as $user) {
                    $credit = $credits->grantRegistrationAdCredit($user);

                    if ($credit) {
                        $credits->notifyRegistrationAdCredit($credit);
                    }
                }
            });
    }
}
