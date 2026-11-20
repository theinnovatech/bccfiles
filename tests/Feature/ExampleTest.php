<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    User::create([
        'name' => 'Admin', 'email' => 'example-admin@test.local', 'password' => 'password1',
        'role' => UserRole::Admin, 'is_active' => true,
    ]);

    $this->get('/')->assertStatus(200);
});
