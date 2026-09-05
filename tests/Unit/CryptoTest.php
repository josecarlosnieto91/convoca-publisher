<?php

namespace ConvocaPublisher\Tests;

use ConvocaPublisher\Crypto;
use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase
{
    /**
     * @test
     * encrypt produces different, non-empty output.
     */
    public function testEncryptProducesDifferentNonEmptyOutput(): void
    {
        $plaintext = 'my_secret_token_12345';

        $encrypted = Crypto::encrypt($plaintext);

        $this->assertNotEmpty($encrypted, 'Encrypted output should not be empty.');
        $this->assertNotEquals($plaintext, $encrypted, 'Encrypted output must differ from plaintext.');
    }

    /**
     * @test
     * decrypt(encrypt(x)) === x (roundtrip).
     */
    public function testDecryptEncryptRoundtrip(): void
    {
        $testCases = [
            'simple_ascii',
            'UTF-8: áéíóú ñ € —',
            'mixed with numbers 12345',
            'special !@#$%^&*()_+{}|:"<>?',
            'long_string_' . str_repeat('a', 500),
            'a',           // single char
            '   spaced  ', // leading/trailing spaces
        ];

        foreach ($testCases as $original) {
            $encrypted = Crypto::encrypt($original);
            $decrypted = Crypto::decrypt($encrypted);

            $this->assertSame(
                $original,
                $decrypted,
                "Roundtrip failed for input: {$original}"
            );
        }
    }

    /**
     * @test
     * encrypt returns different ciphertexts for the same plaintext
     * due to random IV (non-deterministic encryption).
     */
    public function testEncryptIsNonDeterministic(): void
    {
        $plaintext = 'same_input_twice';

        $cipher1 = Crypto::encrypt($plaintext);
        $cipher2 = Crypto::encrypt($plaintext);

        $this->assertNotEquals(
            $cipher1,
            $cipher2,
            'Two encryptions of the same plaintext should produce different ciphertexts (random IV).'
        );
    }

    /**
     * @test
     * decrypt of plain text returns original (graceful degradation).
     */
    public function testDecryptPlainTextReturnsOriginal(): void
    {
        $plain = 'this is never encrypted, just plain text';

        $result = Crypto::decrypt($plain);

        $this->assertSame($plain, $result, 'Decrypting plain text should return the original string.');
    }

    /**
     * @test
     * encrypt / decrypt empty string.
     */
    public function testEncryptDecryptEmptyString(): void
    {
        // Encrypt empty string
        $encrypted = Crypto::encrypt('');
        $this->assertNotEmpty($encrypted, 'Encrypting empty string should still produce output (IV + tag + no ciphertext).');

        // Roundtrip
        $decrypted = Crypto::decrypt($encrypted);
        $this->assertSame('', $decrypted, 'Decrypting encrypted empty string should return empty string.');

        // Decrypt plain empty string
        $this->assertSame('', Crypto::decrypt(''), 'Decrypting plain empty string should return empty string.');
    }

    /**
     * @test
     * decrypt invalid base64 returns original.
     */
    public function testDecryptInvalidBase64ReturnsOriginal(): void
    {
        $invalidBase64Cases = [
            '!!!not-valid-base64!!!',
            '{}[]',
            '====',
            "with\nnewline",
            'convoca_publisher_enc:' . base64_encode('real-looking-but-not-encrypted'),
        ];

        foreach ($invalidBase64Cases as $input) {
            $result = Crypto::decrypt($input);
            $this->assertSame(
                $input,
                $result,
                "Decrypting invalid base64 '{$input}' should return the original string."
            );
        }
    }

    /**
     * @test
     * decrypt too-short data returns original.
     * Minimum valid encrypted payload = IV(12) + tag(16) = 28 bytes.
     * So anything less than 28 bytes after base64 decode should be returned as-is.
     */
    public function testDecryptTooShortDataReturnsOriginal(): void
    {
        // Base64 of 1, 10, and 27 bytes (all less than 28)
        $tooShortCases = [
            base64_encode('x'),                          // 1 byte
            base64_encode(str_repeat('a', 10)),          // 10 bytes
            base64_encode(str_repeat('b', 27)),          // 27 bytes (just under the 28-byte threshold)
        ];

        foreach ($tooShortCases as $input) {
            $result = Crypto::decrypt($input);
            $this->assertSame(
                $input,
                $result,
                "Decrypting too-short data should return the original string: {$input}"
            );
        }
    }

    /**
     * @test
     * decrypt valid base64 with correct minimum length but garbage content
     * should return the original (openssl_decrypt fails).
     */
    public function testDecryptGarbageWithMinimumLengthReturnsOriginal(): void
    {
        // Exactly 28 bytes of garbage — valid base64, meets length requirement,
        // but openssl_decrypt will fail because it's not real encrypted data.
        $garbage = base64_encode(str_repeat("\x00", 28));

        $result = Crypto::decrypt($garbage);

        $this->assertSame(
            $garbage,
            $result,
            'Decrypting minimum-length garbage data should return the original string.'
        );
    }

    /**
     * @test
     * encrypt_on_save only encrypts token options.
     */
    public function testEncryptOnSaveOnlyEncryptsTokenOptions(): void
    {
        $value = 'sensitive-data-here';

        // Plugin token options ending in _token should be prefixed.
        $result = Crypto::encrypt_on_save($value, 'convoca_publisher_facebook_token');
        $this->assertStringStartsWith('convoca_publisher_enc:', $result);
        $this->assertNotEquals($value, $result);

        // Plugin options ending in _bearer_token should be prefixed.
        $result2 = Crypto::encrypt_on_save($value, 'convoca_publisher_api_bearer_token');
        $this->assertStringStartsWith('convoca_publisher_enc:', $result2);

        // Non-token options should NOT be modified
        $result3 = Crypto::encrypt_on_save($value, 'some_other_option');
        $this->assertSame($value, $result3);
    }

    /**
     * @test
     * encrypt_on_save gracefully handles non-strings.
     */
    public function testEncryptOnSaveHandlesNonStrings(): void
    {
        $this->assertSame(42, Crypto::encrypt_on_save(42, 'my_token'));
        $this->assertSame([], Crypto::encrypt_on_save([], 'my_token'));
        $this->assertNull(Crypto::encrypt_on_save(null, 'my_token'));
    }

    /**
     * @test
     * encrypt_on_save skips empty values and already-prefixed values.
     */
    public function testEncryptOnSaveSkipsEmptyAndAlreadyPrefixed(): void
    {
        // Empty value
        $this->assertSame('', Crypto::encrypt_on_save('', 'my_token'));

        // Already prefixed
        $already = 'convoca_publisher_enc:some_already_encrypted_data';
        $this->assertSame($already, Crypto::encrypt_on_save($already, 'my_token'));
    }

    /**
     * @test
     * decrypt_on_load only decrypts convoca_publisher_enc: prefixed values.
     */
    public function testDecryptOnLoadRoundtrip(): void
    {
        $original = 'my_sensitive_bearer_token_value';

        // Simulate save -> load roundtrip
        $saved = Crypto::encrypt_on_save($original, 'facebook_token');
        $loaded = Crypto::decrypt_on_load($saved, 'facebook_token');

        $this->assertSame($original, $loaded);

        // Non-prefixed values pass through unchanged
        $this->assertSame('plain_text', Crypto::decrypt_on_load('plain_text', 'some_option'));

        // Non-string pass through
        $this->assertSame(123, Crypto::decrypt_on_load(123, 'facebook_token'));
    }
}
