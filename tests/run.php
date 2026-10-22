<?php
declare(strict_types=1);
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
if (extension_loaded('pdo_sqlite')) {
    $db=db();
    check((int)$db->query('SELECT COUNT(*) FROM services')->fetchColumn()===9,'SQLite schema migrations and service seeding');
    check(configuredService('shahkar')['endpoint']==='','Fail-closed integration when route is unknown');
} else { echo "SKIP: SQLite integration (pdo_sqlite unavailable in this environment)\n"; }
echo "All available tests passed.\n";
