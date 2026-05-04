<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ExternalSystemUsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $users = [
            ['username' => 'LIU001', 'email' => 'dav3liu@gmail.com', 'name' => 'David Liu', 'password' => '$2a$10$eXa6bLGD/UJCkP285KaSNO4GcHa88MTmmJ1W89O0aomzI6a0SzMVC', 'role' => 'super-admin'],
            ['username' => 'MAK001', 'email' => 'jacob@fcasey.co.za', 'name' => 'Jacob Makopo', 'password' => '$2a$10$aSOO5cBguArKUXFkyiiKd.Co.OgTTPxzTiFIi4wDI3hJG9rSiEcFK', 'role' => 'super-admin'],
            ['username' => 'BOT001', 'email' => 'yvonne@fcasey.co.za', 'name' => 'Yvonne Botha', 'password' => '$2a$10$RZzRIX5qUxlVEvZN.0pSPezgAte9XTVKu2Tqyhj4/5YfbrJ7fWdAO', 'role' => 'super-admin'],
            ['username' => 'MOR001', 'email' => 'tiro@fcasey.co.za', 'name' => 'Tiro More', 'password' => '$2a$10$nhU16eE51f/HnSsyv3tMe.LQLbSDsuHp3yXi0zya823m6k.iqJne2', 'role' => 'super-admin'],
            ['username' => 'MAJ001', 'email' => 'katlego@fcasey.co.za', 'name' => 'Katlego Majatladi', 'password' => '$2a$10$n32POcwe9RaTy4.ZZIkG/uNg8FRZmull5hMtPT16YJQGYnjtF1JbC', 'role' => 'super-admin'],
            ['username' => 'NKO002', 'email' => 'nombuso@fcasey.co.za', 'name' => 'Nombuso Nkosi', 'password' => '$2a$10$Zmq7IGpoAcbn1UHeGTzr..mSayMGQaj1U1jNAeNsjRKZC0TF2w5uO', 'role' => 'super-admin'],
            ['username' => 'rajagali', 'email' => 'rajagali@fcasey.co.za', 'name' => 'Raja Gali', 'password' => '$2a$10$DpbSIGMdvliDoHmsxzmx2.k5qKChtik9IQfeH5T6BWvcnCXCkhVWO', 'role' => 'super-admin'],
            ['username' => 'PEL001', 'email' => 'Tumi@fcasey.co.za', 'name' => 'Itumeleng Pelesa', 'password' => '$2a$10$WMWx8v.tXn1lKAdRebUQ3uocGPpY2T/RtKzeGHOz.m6Dk6wMzan0S', 'role' => 'admin'],
        ];

        foreach ($users as $payload) {
            DB::table('users')->updateOrInsert(
                ['email' => $payload['email']],
                [
                    'employee_number' => $payload['username'],
                    'name' => $payload['name'],
                    'password' => Hash::make($payload['username']),
                    'external_password_hash' => $payload['password'],
                    'is_active' => '1',
                    'email_verified_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $user = User::where('email', $payload['email'])->first();
            if ($user) {
                $user->syncRoles([$payload['role']]);
            }
        }
    }
}
