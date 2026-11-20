<?php

/*
 * First-time administrator setup and forgot-password by email.
 */

use App\Enums\UserRole;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.turnstile.enabled' => false, 'obims.setup_key' => '']);
});

function authAdmin(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'OldPass123',
        'role' => UserRole::Admin, 'is_active' => true,
    ], $overrides));
}

function sentCode(): array
{
    $found = null;
    Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$found) {
        $found = ['code' => $mail->code, 'link' => $mail->link, 'to' => $mail->to[0]['address'] ?? null];

        return true;
    });

    return $found;
}

// ------------------------------------------------------------------ first-time setup

test('with no administrator every page redirects to setup', function () {
    $this->get('/')->assertRedirect('/setup');
    $this->get('/items')->assertRedirect('/setup');
    $this->getJson('/user')->assertStatus(409)->assertJsonPath('setup_url', url('/setup'));
    $this->postJson('/login', ['email' => 'x@test.local', 'password' => 'x'])->assertStatus(409);
    $this->get('/setup')->assertOk()->assertSee('"mode":"setup"', false);
});

test('setup creates the first administrator, signs in, then closes', function () {
    $this->postJson('/setup', [
        'name' => 'Maria Santos', 'email' => 'maria@test.local',
        'password' => 'short', 'password_confirmation' => 'short',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    $this->postJson('/setup', [
        'name' => 'Maria Santos', 'email' => 'maria@test.local',
        'password' => 'Secure123', 'password_confirmation' => 'Different123',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    $this->postJson('/setup', [
        'name' => '  Maria Santos ', 'email' => 'maria@test.local',
        'password' => 'Secure123', 'password_confirmation' => 'Secure123',
    ])->assertCreated();

    $admin = User::where('email', 'maria@test.local')->firstOrFail();
    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->name)->toBe('Maria Santos')
        ->and(Hash::check('Secure123', $admin->password))->toBeTrue()
        ->and(Auth::id())->toBe($admin->id)
        ->and(DB::table('activity_logs')->where('user_id', $admin->id)->where('description', 'like', '%first administrator%')->exists())->toBeTrue();

    // Setup is closed once an administrator exists.
    $this->get('/setup')->assertRedirect('/');
    Auth::logout();
    $this->postJson('/setup', [
        'name' => 'Intruder', 'email' => 'intruder@test.local',
        'password' => 'Secure123', 'password_confirmation' => 'Secure123',
    ])->assertStatus(409);
    expect(User::where('role', UserRole::Admin)->count())->toBe(1);
});

test('an inactive administrator still counts, so setup stays closed', function () {
    authAdmin(['is_active' => false]);
    $this->get('/setup')->assertRedirect('/');
    $this->get('/')->assertOk();
});

test('a non-admin account alone does not count as an administrator', function () {
    User::create(['name' => 'SO', 'email' => 'so@test.local', 'password' => 'Secure123', 'role' => UserRole::SupplyOfficer, 'is_active' => true]);
    $this->get('/')->assertRedirect('/setup');
});

test('the optional setup key is required and checked when configured', function () {
    config(['obims.setup_key' => 'sdo-iriga-2026']);
    $payload = ['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'Secure123', 'password_confirmation' => 'Secure123'];

    $this->postJson('/setup', $payload)->assertStatus(422)->assertJsonValidationErrors('setup_key');
    $this->postJson('/setup', $payload + ['setup_key' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('setup_key');
    $this->postJson('/setup', $payload + ['setup_key' => 'sdo-iriga-2026'])->assertCreated();
});

// ------------------------------------------------------------------ forgot password

test('a code and a link are emailed; unknown emails get the same answer and no email', function () {
    Mail::fake();
    $user = authAdmin();

    $known = $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk()->json('message');
    $sent = sentCode();
    expect($sent['to'])->toBe('admin@test.local')
        ->and($sent['code'])->toMatch('/^\d{6}$/')
        ->and($sent['link'])->toContain('/reset-password?token=');

    // Only hashes are stored.
    $record = PasswordResetCode::where('user_id', $user->id)->firstOrFail();
    expect($record->code_hash)->not->toBe($sent['code'])
        ->and(DB::table('password_reset_codes')->where('code_hash', $sent['code'])->exists())->toBeFalse();

    Mail::fake();
    $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@test.local'])->assertOk()->json('message');
    expect($unknown)->toBe($known);
    Mail::assertNothingSent();
});

test('requesting again within a minute does not send another code', function () {
    Mail::fake();
    authAdmin();
    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    Mail::assertSentCount(1);

    $this->travel(61)->seconds();
    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    Mail::assertSentCount(2);
    // Only the newest code is kept.
    expect(PasswordResetCode::whereNull('used_at')->count())->toBe(1);
});

test('reset with the emailed code changes the password once and signs out other sessions', function () {
    Mail::fake();
    config(['session.driver' => 'database']);
    $user = authAdmin();
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    $code = sentCode()['code'];
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->postJson('/forgot-password/verify', ['email' => 'admin@test.local', 'code' => $wrong])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'The code is incorrect. 4 attempt(s) left.');
    $this->postJson('/forgot-password/verify', ['email' => 'admin@test.local', 'code' => $code])->assertOk()->assertJsonPath('valid', true);

    $this->postJson('/reset-password', [
        'email' => 'admin@test.local', 'code' => $code, 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456',
    ])->assertOk()->assertJsonPath('email', 'admin@test.local');

    $user->refresh();
    expect(Hash::check('NewPass456', $user->password))->toBeTrue()
        ->and(Hash::check('OldPass123', $user->password))->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    // Sign in with the new password works.
    $this->postJson('/login', ['email' => 'admin@test.local', 'password' => 'NewPass456'])->assertOk();
    Auth::logout();

    // The code cannot be used again.
    $this->postJson('/reset-password', [
        'email' => 'admin@test.local', 'code' => $code, 'password' => 'Again789x', 'password_confirmation' => 'Again789x',
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

test('the emailed link resets the password without the code', function () {
    Mail::fake();
    $user = authAdmin();
    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    parse_str(parse_url(sentCode()['link'], PHP_URL_QUERY), $query);

    $this->get('/reset-password?token='.$query['token'].'&email=admin%40test.local')
        ->assertOk()->assertSee('"resetLinkValid":true', false);

    $this->postJson('/reset-password', ['token' => $query['token'], 'password' => 'LinkPass123', 'password_confirmation' => 'LinkPass123'])->assertOk();
    expect(Hash::check('LinkPass123', $user->fresh()->password))->toBeTrue();

    // Used link: the page says so and the token is refused.
    $this->get('/reset-password?token='.$query['token'])->assertOk()->assertSee('"resetLinkValid":false', false);
    $this->postJson('/reset-password', ['token' => $query['token'], 'password' => 'LinkPass999', 'password_confirmation' => 'LinkPass999'])
        ->assertStatus(422)->assertJsonValidationErrors('token');
});

test('codes expire, and too many wrong codes cancel the code', function () {
    Mail::fake();
    authAdmin();
    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    $code = sentCode()['code'];
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/forgot-password/verify', ['email' => 'admin@test.local', 'code' => $wrong])->assertStatus(422);
    }
    // Even the right code is refused now.
    $this->postJson('/forgot-password/verify', ['email' => 'admin@test.local', 'code' => $code])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'This code has expired or is no longer valid. Request a new code.');

    // A new code works but expires after 15 minutes.
    $this->travel(2)->minutes();
    Mail::fake();
    $this->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertOk();
    $fresh = sentCode()['code'];
    $this->travel(16)->minutes();
    $this->postJson('/forgot-password/verify', ['email' => 'admin@test.local', 'code' => $fresh])->assertStatus(422);
});

test('inactive accounts get no email and new passwords must be strong', function () {
    Mail::fake();
    authAdmin(['email' => 'off@test.local', 'is_active' => false]);
    authAdmin(['email' => 'on@test.local']);

    $this->postJson('/forgot-password', ['email' => 'off@test.local'])->assertOk();
    Mail::assertNothingSent();

    $this->postJson('/forgot-password', ['email' => 'on@test.local'])->assertOk();
    $code = sentCode()['code'];
    $this->postJson('/reset-password', ['email' => 'on@test.local', 'code' => $code, 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertStatus(422)->assertJsonValidationErrors('password');
});

test('signed-in users cannot use the forgot-password endpoints', function () {
    $user = authAdmin();
    $this->actingAs($user)->postJson('/forgot-password', ['email' => 'admin@test.local'])->assertStatus(302);
});
