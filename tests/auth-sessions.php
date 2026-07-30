<?php
declare(strict_types=1);
$core=dirname(__DIR__).'/_edit/core/';
spl_autoload_register(function($class)use($core){$prefix='Edit\\Core\\';if(str_starts_with($class,$prefix))require $core.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
define('EDIT_SESSION_EXPIRY_HOURS',24);
function dateTime($relative){return gmdate('Y-m-d H:i:s',strtotime($relative));}
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
class InterleavingDatabase extends Edit\Core\Database\Database {
 public bool $resetOnInsert=false;
 public function execute(string $sql,array $params=[]):int {
  if($this->resetOnInsert && str_starts_with($sql,'INSERT INTO sessions')) {
   $this->resetOnInsert=false;
   parent::execute("UPDATE users SET password = 'changed-by-concurrent-reset' WHERE id = ?",[$params[2]]);
  }
  return parent::execute($sql,$params);
 }
}
$db=new InterleavingDatabase(':memory:');$auth=new Edit\Core\Auth\Auth($db);
$id=$auth->register('one@example.invalid','FixturePassword789!','One');
$other=$auth->register('two@example.invalid','FixturePassword789!','Two');
$a=$auth->login('one@example.invalid','FixturePassword789!')['token'];$b=$auth->login('one@example.invalid','FixturePassword789!')['token'];
$c=$auth->login('two@example.invalid','FixturePassword789!')['token'];
check($auth->verifyToken($a)===$id,'Login failed');
try{$auth->changePassword($id,'weak');throw new RuntimeException('Weak password accepted');}catch(InvalidArgumentException $e){}
check($auth->verifyToken($a)===$id,'Invalid reset revoked session');
// Force revocation failure to prove the password change also rolls back.
$db->execute("CREATE TRIGGER fail_revoke BEFORE DELETE ON sessions BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
try{$auth->changePassword($id,'ReplacementPassword456!');throw new RuntimeException('Failure missing');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'fixture failure'),'Unexpected error');}
check($auth->login('one@example.invalid','FixturePassword789!')!==null,'Password was not rolled back');
$db->execute('DROP TRIGGER fail_revoke');
$auth->changePassword($id,'ReplacementPassword456!');
check($auth->verifyToken($a)===null && $auth->verifyToken($b)===null,'Old sessions survived');
check(!$auth->isAuthenticated(),'Cached identity survived failed verification');
check($auth->verifyToken($c)===$other,'Other user was logged out');
check($auth->login('one@example.invalid','FixturePassword789!')===null,'Old password works');
check($auth->login('one@example.invalid','ReplacementPassword456!')!==null,'New password fails');
try{$auth->changePassword(999,'ReplacementPassword456!');throw new RuntimeException('Missing user accepted');}catch(OutOfBoundsException $e){}
// Simulate a reset occurring exactly between password verification and session insertion.
$db->resetOnInsert=true;
check($auth->login('one@example.invalid','ReplacementPassword456!')===null,'Failed insert returned usable token');
echo "PASS: session revocation, user isolation, invalid resets, rollback, stale identity, login/reset race guard\n";
