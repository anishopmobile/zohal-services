<?php
declare(strict_types=1);
ob_start();
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
putenv('APP_KEY=1111111111111111111111111111111111111111111111111111111111111111');
require __DIR__.'/../src/app.php';
function check(bool $condition, string $name): void { if(!$condition) { fwrite(STDERR,"FAIL: $name\n"); exit(1); } echo "PASS: $name\n"; }
check(normalizeDigits('۱۲٣٤٥٦۷۸۹۰')==='1234567890','Persian/Arabic number normalization');
check(validNationalCode('0084575948'), 'Valid Iranian national code');
check(!validNationalCode('0000000000') && !validNationalCode('0084575949'), 'Reject invalid national codes');
check(validCard('6037997510593099'), 'Valid sample Luhn card');
check(!validCard('6037997510593098'), 'Reject invalid Luhn card');
check(validIban('IR820540102680020817909002'), 'Valid sample IBAN mod-97');
check(!validIban('IR820540102680020817909003'), 'Reject invalid IBAN checksum');
check(substr(jalali('2026-10-22T05:00:00Z'),0,10)==='1405/07/30','Jalali date conversion');
check(count(catalog())===9,'Exactly nine initial services');
foreach(catalog() as $slug=>$meta) {
    check(count($meta['fields'])>0,"Service fields: $slug");
}
[$values,$errors]=validateFields(catalog()['shahkar']['fields'],['national_code'=>'۰۰۸۴۵۷۵۹۴۸','mobile'=>'۰۹۱۲۱۲۳۴۵۶۷']);
check($errors===[] && $values['mobile']==='09121234567','Validated and normalized shahkar form');
[, $errors]=validateFields(catalog()['shahkar']['fields'],['national_code'=>'oops','mobile'=>'<script>']);
check(count($errors)===2,'Server rejects malformed and script values');
$secret=sealSecret(str_repeat('a',40));
check(revealSecret($secret)===str_repeat('a',40) && !str_contains($secret,str_repeat('a',40)),'Authenticated encryption roundtrip');
if (extension_loaded('pdo_mysql') && getenv('DB_NAME')) {
    $pdo=db();
    check((int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn()===9,'MySQL schema creation and nine service seeds');
    check(configuredService('shahkar')['endpoint']==='','Fail-closed integration when route is unknown');
    $name='unit-'.bin2hex(random_bytes(6));
    storeSetting($name,'initial');
    storeSetting($name,'updated');
    check(setting($name)==='updated','MySQL upsert for settings');
    $pdo->prepare('DELETE FROM settings WHERE `key`=?')->execute([$name]);
    $pdo->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES (?,?,?)')
        ->execute(['test_'.bin2hex(random_bytes(4)),password_hash('testing_password_123',PASSWORD_DEFAULT),isoNow()]);
    $adminId=(int)$pdo->lastInsertId();
    $_SERVER['HTTPS']='on';
    issueRememberToken($adminId);
    $cookie=$_COOKIE['zohal_remember'];
    check((bool)preg_match('/^[a-f0-9]{24}:[a-f0-9]{64}$/',$cookie),'Remember-me cookie has opaque random credentials');
    check((int)$pdo->query('SELECT COUNT(*) FROM remember_tokens')->fetchColumn()>=1,'Remember-me token persisted to MySQL');
    $_SESSION=[];
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    check(restoreRememberedAdmin() && (int)$_SESSION['admin_id']===$adminId,'Remember-me token restores a session');
    check($_COOKIE['zohal_remember']!==$cookie,'Remember-me token rotates on restoration');
    revokeRememberToken();
    check(!isset($_COOKIE['zohal_remember']),'Remember-me token cookie revoked');
    $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$adminId]);
} else { echo "SKIP: MySQL integration (set DB_* and install pdo_mysql)\n"; }
echo "All available tests passed.\n";
ob_end_flush();
