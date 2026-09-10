<?php

declare(strict_types=1);

// Never load the installation's config.php or database.
$core = dirname(__DIR__) . '/_edit/core';
spl_autoload_register(function (string $class) use ($core): void {
    foreach (['Edit\\Core\\' => $core . '/', 'AltchaOrg\\Altcha\\' => $core . '/ThirdParty/Altcha/src/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            require $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            return;
        }
    }
});

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Edit\Core\Database\Database;
use Edit\Core\Security\Captcha;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function solve(array $challenge): array
{
    $challenge = Challenge::fromArray($challenge);
    $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(
        algorithm: new Pbkdf2(),
        challenge: $challenge,
    ));
    if (!$solution) {
        throw new RuntimeException('Fixture could not solve challenge.');
    }
    return (new Payload($challenge, $solution))->toArray();
}
function encode(array $payload): string
{
    return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
}

$db = new Database(':memory:');
$captcha = new Captcha($db, 'fixture-secret-not-an-installation-key');
check(Captcha::isEnabled($db), 'Fresh installation must enable CAPTCHA.');
$db->table('settings')->insert(['key' => 'captcha_enabled', 'value' => '0']);
check(!Captcha::isEnabled($db), 'Explicit opt-out must be preserved.');
$db->table('settings')->where('key', 'captcha_enabled')->update(['value' => '1']);
check(Captcha::isEnabled($db), 'Existing enabled setting must enable ALTCHA.');
check(in_array('message', array_column($db->query('PRAGMA table_info(email_logs)'), 'name')), 'Fresh email-log schema must support delivery.');

$first = $captcha->issue();
$second = $captcha->issue();
check($first['signature'] !== $second['signature'], 'Challenges must be unique.');
$proof = solve($first);
$bad = $proof;
$bad['solution']['derivedKey'] = str_repeat('0', 64);
check(!$captcha->verifyAndConsume(encode($bad)), 'Invalid solution must fail.');
$bad = $proof;
$bad['challenge']['parameters']['expiresAt'] += 1;
check(!$captcha->verifyAndConsume(encode($bad)), 'Modified signed expiry must fail.');
$bad = $proof;
$bad['challenge']['parameters']['cost'] = PHP_INT_MAX;
check(!$captcha->verifyAndConsume(encode($bad)), 'Attacker-selected work cost must fail.');
$bad = $proof;
$bad['challenge']['parameters']['data']['purpose'] = 'login';
check(!$captcha->verifyAndConsume(encode($bad)), 'Foreign purpose must fail.');
check(!(new Captcha($db, 'different-installation'))->verifyAndConsume(encode($proof)), 'Wrong installation key must fail.');
check($captcha->verifyAndConsume(encode($proof)), 'Valid proof must work after invalid attempts.');
check(!$captcha->verifyAndConsume(encode($proof)), 'Proof must be single-use.');
check(!$captcha->verifyAndConsume(encode(array_reverse($proof, true))), 'Different JSON ordering must not bypass replay checks.');

$expired = solve($second);
$db->execute('UPDATE captcha_challenges SET expires_at = ?', [time() - 1]);
check(!$captcha->verifyAndConsume(encode($expired)), 'Expired issued challenge must fail.');
$expired['challenge']['parameters']['expiresAt'] = time() - 1;
check(!$captcha->verifyAndConsume(encode($expired)), 'Expired payload must fail.');
foreach ([null, [], '', 'not base64!', base64_encode('null'), base64_encode('{}'), str_repeat('a', 8193), base64_encode('{broken')] as $input) {
    check(!$captcha->verifyAndConsume($input), 'Malformed input must fail closed.');
}
$captcha->issue();
check($db->table('captcha_challenges')->count() === 1, 'Issuance must clean expired records.');
$smtp = new Edit\Core\Email\SMTP('127.0.0.1', 1, 'fixture', 'fixture');
check(!$smtp->send('sender@example.invalid', 'Fixture', 'to@example.invalid', "Subject\r\nBcc: injected@example.invalid", 'body'), 'SMTP must reject injected headers before connecting.');
echo "CAPTCHA checks passed: {$checks}\n";
