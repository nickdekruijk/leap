<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Laravel\Passkeys\Passkey;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;
use OpenSSLAsymmetricKey;

/**
 * Registers and signs in with a passkey through the routes Leap loads, with a
 * software authenticator in place of the browser. laravel/passkeys 0.3 changed
 * the user handle of new passkeys; these tests keep the ones registered before
 * that working.
 */
class PasskeyCeremonyTest extends TestCase
{
    private const CREDENTIAL_ID = 'leap-test-credential-id';

    private OpenSSLAsymmetricKey $key;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'https://leap.test');
        $app['config']->set('passkeys.relying_party_id', 'leap.test');
        $app['config']->set('passkeys.allowed_origins', ['https://leap.test']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            // Not used for EC, but PHP 8.3 refuses the call without it
            'private_key_bits' => 2048,
        ]);
    }

    private function createUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));

        return $user;
    }

    private function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => 'https://leap.test',
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }

    private function cborBytes(string $bytes): string
    {
        return strlen($bytes) < 24
            ? chr(0x40 | strlen($bytes)).$bytes
            : (strlen($bytes) < 256 ? "\x58".chr(strlen($bytes)).$bytes : "\x59".pack('n', strlen($bytes)).$bytes);
    }

    private function cborText(string $text): string
    {
        return chr(0x60 | strlen($text)).$text;
    }

    /**
     * The attestation ("none") a browser posts after navigator.credentials.create().
     */
    private function attestation(string $challenge): array
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];
        $coseKey = "\xa5\x01\x02\x03\x26\x20\x01\x21".$this->cborBytes(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT))
            ."\x22".$this->cborBytes(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));

        // User present, user verified, attested credential data included
        $authData = hash('sha256', 'leap.test', true)."\x45".pack('N', 0).str_repeat("\0", 16)
            .pack('n', strlen(self::CREDENTIAL_ID)).self::CREDENTIAL_ID.$coseKey;

        return [
            'id' => $this->base64url(self::CREDENTIAL_ID),
            'rawId' => $this->base64url(self::CREDENTIAL_ID),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->base64url($this->clientData('webauthn.create', $challenge)),
                'attestationObject' => $this->base64url(
                    "\xa3".$this->cborText('fmt').$this->cborText('none')
                    .$this->cborText('attStmt')."\xa0"
                    .$this->cborText('authData').$this->cborBytes($authData)
                ),
            ],
        ];
    }

    /**
     * The assertion a browser posts after navigator.credentials.get().
     */
    private function assertion(string $challenge, string $userHandle): array
    {
        $clientData = $this->clientData('webauthn.get', $challenge);

        // User present, user verified
        $authData = hash('sha256', 'leap.test', true)."\x05".pack('N', 1);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->base64url(self::CREDENTIAL_ID),
            'rawId' => $this->base64url(self::CREDENTIAL_ID),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->base64url($clientData),
                'authenticatorData' => $this->base64url($authData),
                'signature' => $this->base64url($signature),
                'userHandle' => $this->base64url($userHandle),
            ],
        ];
    }

    /**
     * Register a passkey for the user and return the handle the server asked
     * the authenticator to store.
     */
    private function register(User $user): string
    {
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $options = $this->getJson('https://leap.test/user/passkeys/options')->assertOk()->json('options');

        $this->withSession(['passkey.registration_options' => session('passkey.registration_options')])
            ->postJson('https://leap.test/user/passkeys', [
                'name' => 'Test device',
                'credential' => $this->attestation($options['challenge']),
            ])->assertSuccessful();

        auth(config('leap.guard'))->logout();
        $this->app['auth']->forgetGuards();

        return $this->base64urlDecode($options['user']['id']);
    }

    private function login(string $userHandle): TestResponse
    {
        $challenge = $this->getJson('https://leap.test/passkeys/login/options')->assertOk()->json('options.challenge');

        return $this->withSession(['passkey.verification_options' => session('passkey.verification_options')])
            ->postJson('https://leap.test/passkeys/login', [
                'credential' => $this->assertion($challenge, $userHandle),
            ]);
    }

    /**
     * The handle laravel/passkeys gave out up to 0.2: the raw bytes of the hash.
     */
    private function binaryHandle(User $user): string
    {
        return hash_hmac('sha256', $user->getTable().'|'.$user->getKey(), config('passkeys.user_handle_secret'), true);
    }

    /**
     * Turn the registered passkey into one registered before 0.3.
     */
    private function storeHandle(string $userHandle): void
    {
        $passkey = Passkey::sole();
        $passkey->credential = ['userHandle' => $this->base64url($userHandle)] + $passkey->credential;
        $passkey->save();
    }

    public function test_a_new_passkey_gets_a_text_handle_and_signs_in(): void
    {
        $user = $this->createUser();

        $handle = $this->register($user);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $handle);
        $this->assertGuest(config('leap.guard'));

        $this->login($handle)->assertOk();

        $this->assertAuthenticatedAs($user, config('leap.guard'));
    }

    public function test_a_passkey_with_a_binary_handle_from_before_still_signs_in(): void
    {
        $user = $this->createUser();
        $this->register($user);
        $this->storeHandle($this->binaryHandle($user));

        $this->login($this->binaryHandle($user))->assertOk();

        $this->assertAuthenticatedAs($user, config('leap.guard'));
    }

    public function test_a_handle_the_device_corrupted_is_refused_with_a_message(): void
    {
        $user = $this->createUser();
        $this->register($user);
        $this->storeHandle($this->binaryHandle($user));

        // What iOS Safari made of the raw bytes: read as UTF-8 text
        $corrupted = mb_convert_encoding($this->binaryHandle($user), 'UTF-8', 'ISO-8859-1');

        $this->login($corrupted)->assertUnprocessable()->assertJsonValidationErrorFor('credential');

        $this->assertGuest(config('leap.guard'));
    }
}
