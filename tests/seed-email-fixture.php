<?php

// Called only by the isolated integration runner with a temporary application root.
require $argv[1] . '/_edit/core/bootstrap.php';
require $argv[1] . '/_edit/api/helpers.php';
$db = new Edit\Core\Database\Database(EDIT_DATABASE_PATH);
foreach ([
    'email_from_address' => 'sender@example.invalid',
    'email_from_name' => 'Fixture CMS',
    'contact_recipient' => 'inquiries@example.invalid',
    'smtp_host' => '127.0.0.1',
    'smtp_port' => $argv[2],
    'smtp_username' => 'fixture',
    'smtp_password' => Edit\Core\Security\Security::encrypt('fixture-password'),
    'smtp_encryption' => '',
] as $key => $value) {
    saveSetting($db, $key, $value);
}
(new Edit\Core\Auth\Auth($db))->register('admin@example.invalid', 'FixturePassword789!', 'Fixture Admin');
