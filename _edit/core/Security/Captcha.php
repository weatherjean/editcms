<?php

declare(strict_types=1);

namespace Edit\Core\Security;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Edit\Core\Database\Database;

final class Captcha
{
    public const LIFETIME_SECONDS = 600;
    private const COST = 1000;
    private Altcha $altcha;

    public function __construct(private Database $db, string $secret)
    {
        // Separate CAPTCHA signatures from other uses of the installation key.
        $this->altcha = new Altcha(
            hmacSignatureSecret: hash_hmac('sha256', 'edit:contact:altcha:v2', $secret),
        );
    }

    public static function isEnabled(Database $db): bool
    {
        $setting = $db->table('settings')->where('key', 'captcha_enabled')->first();
        // New installations are protected; an explicit existing opt-out is kept.
        return ($setting['value'] ?? '1') !== '0';
    }

    public function issue(): array
    {
        $now = time();
        $this->db->execute('DELETE FROM captcha_challenges WHERE expires_at <= ?', [$now]);
        $challenge = $this->altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: self::COST,
            counter: random_int(500, 1500),
            expiresAt: $now + self::LIFETIME_SECONDS,
            data: ['purpose' => 'contact'],
        ));
        $this->db->table('captcha_challenges')->insert([
            'signature_hash' => hash('sha256', $challenge->signature),
            'expires_at' => $challenge->parameters->expiresAt,
        ]);
        return $challenge->toArray();
    }

    public function verifyAndConsume(mixed $encoded): bool
    {
        if (!is_string($encoded) || $encoded === '' || strlen($encoded) > 8192) {
            return false;
        }

        try {
            $json = base64_decode($encoded, true);
            if ($json === false) {
                return false;
            }
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return false;
            }
            $payload = Payload::fromArray($data);
            $params = $payload->challenge->parameters;
            $signature = $payload->challenge->signature;

            // Bound the work and reject foreign/legacy protocols before hashing.
            if (!is_string($signature) || !preg_match('/^[a-f0-9]{64}$/D', $signature)
                || $params->algorithm !== 'PBKDF2/SHA-256'
                || $params->cost !== self::COST || $params->keyLength !== 32
                || $params->expiresAt === null || $params->expiresAt <= time()
                || $params->expiresAt > time() + self::LIFETIME_SECONDS
                || $params->data !== ['purpose' => 'contact']
                || !preg_match('/^[a-f0-9]{32}$/D', $params->nonce)
                || !preg_match('/^[a-f0-9]{32}$/D', $params->salt)
                || !preg_match('/^[a-f0-9]{32}$/D', $params->keyPrefix)
                || !preg_match('/^[a-f0-9]{64}$/D', $payload->solution->derivedKey)
                || $payload->solution->counter < 0 || $payload->solution->counter > 1500) {
                return false;
            }

            $hash = hash('sha256', $signature);
            $issued = $this->db->query(
                'SELECT signature_hash FROM captcha_challenges WHERE signature_hash = ? AND expires_at > ?',
                [$hash, time()]
            );
            if (!$issued || !$this->altcha->verifySolution(new VerifySolutionOptions(
                payload: $payload,
                algorithm: new Pbkdf2(),
            ))->verified) {
                return false;
            }
        } catch (\Throwable $e) {
            // Invalid client payloads must fail closed, including type errors.
            return false;
        }

        // The affected-row check, not the earlier SELECT, establishes ownership.
        // Concurrent requests can verify the same proof, but only one can use it.
        return $this->db->execute(
            'DELETE FROM captcha_challenges WHERE signature_hash = ? AND expires_at > ?',
            [$hash, time()]
        ) === 1;
    }
}
