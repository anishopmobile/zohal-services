<?php
declare(strict_types=1);

function loadEnv(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m)) continue;
        if (getenv($m[1]) !== false) continue;
        $value = trim($m[2]);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) $value = substr($value, 1, -1);
        putenv($m[1].'='.$value);
    }
}
loadEnv(dirname(__DIR__).'/.env');
const DB_PATH = __DIR__.'/../storage/app.sqlite';

function appKey(): string {
    $key = getenv('APP_KEY') ?: '';
    if (!preg_match('/^[a-fA-F0-9]{64}$/D', $key)) throw new RuntimeException('APP_KEY must be a random 64-character hex string.');
    return hex2bin($key);
}
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    appKey();
    if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite extension is required.');
    if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0700, true);
    $pdo = new PDO('sqlite:'.DB_PATH, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $pdo->exec('CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS services (slug TEXT PRIMARY KEY, enabled INTEGER NOT NULL DEFAULT 1, endpoint TEXT NOT NULL DEFAULT \'\', method TEXT NOT NULL DEFAULT \'POST\', field_map TEXT NOT NULL DEFAULT \'{}\')');
    $pdo->exec('CREATE TABLE IF NOT EXISTS inquiries (id INTEGER PRIMARY KEY AUTOINCREMENT, service_slug TEXT NOT NULL, created_at TEXT NOT NULL, status TEXT NOT NULL, upstream_status INTEGER, duration_ms INTEGER, ip_hash TEXT NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inquiries_created ON inquiries(created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inquiries_rate ON inquiries(ip_hash, created_at)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY, username TEXT NOT NULL, ip_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_created ON login_attempts(created_at)');
    foreach (array_keys(catalog()) as $slug) {
        $pdo->prepare('INSERT OR IGNORE INTO services (slug) VALUES (?)')->execute([$slug]);
    }
    return $pdo;
}
function catalog(): array { static $items; return $items ??= require __DIR__.'/catalog.php'; }
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sendJson(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    exit;
}
function startSecureSession(): void {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('zohal_admin');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
    if (!isset($_SESSION['created'])) $_SESSION['created'] = time();
    if (time()-$_SESSION['created'] > 1800) { $_SESSION = []; session_regenerate_id(true); $_SESSION['created'] = time(); }
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verifyCsrf(): void {
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf(), $provided)) sendJson(['ok'=>false,'message'=>'نشست یا توکن امنیتی نامعتبر است؛ صفحه را تازه کنید.'], 419);
}
function isAdmin(): bool { return !empty($_SESSION['admin_id']); }
function requireAdmin(): void { if (!isAdmin()) sendJson(['ok'=>false,'message'=>'دسترسی غیرمجاز'], 401); }
function setting(string $key): ?string {
    $s=db()->prepare('SELECT value FROM settings WHERE key=?'); $s->execute([$key]); $v=$s->fetchColumn(); return $v === false ? null : (string)$v;
}
function storeSetting(string $key,string $value): void { db()->prepare('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$key,$value]); }
function sealSecret(string $value): string {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($value,$nonce,appKey()));
}
function revealSecret(string $packed): string {
    $raw = base64_decode($packed,true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new RuntimeException('Invalid encrypted setting');
    $nonce=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $opened=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,appKey());
    if ($opened === false) throw new RuntimeException('Cannot decrypt setting');
    return $opened;
}
function normalizeDigits(string $value): string {
    return strtr(trim($value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
}
function validNationalCode(string $v): bool {
    if (!preg_match('/^\d{10}$/D',$v) || preg_match('/^(\d)\1{9}$/D',$v)) return false;
    $sum=0;
    for ($i=0;$i<9;$i++) $sum+=(int)$v[$i]*(10-$i);
    $remainder=$sum%11;
    return (int)$v[9] === ($remainder < 2 ? $remainder : 11-$remainder);
}
function validIban(string $v): bool {
    $v=strtoupper($v);
    if (!preg_match('/^IR[0-9]{24}$/D',$v)) return false;
    $arr=substr($v,4).substr($v,0,4); $mod=0;
    foreach (str_split($arr) as $char) {
        $chunk = ctype_alpha($char) ? (string)(ord($char)-55) : $char;
        foreach (str_split($chunk) as $digit) $mod = ($mod*10+(int)$digit)%97;
    }
    return $mod===1;
}
function validCard(string $v): bool {
    if (!preg_match('/^\d{16}$/D',$v) || preg_match('/^(\d)\1{15}$/D',$v)) return false;
    $sum=0;
    for($i=0;$i<16;$i++) { $n=(int)$v[$i]*($i%2===0?2:1); $sum+=$n>9?$n-9:$n; }
    return $sum%10===0;
}
function validateFields(array $fields, array $input): array {
    $out=[]; $errors=[];
    foreach($fields as $name=>$spec) {
        $raw=$input[$name] ?? null;
        if (!is_string($raw)) { $errors[$name]='این فیلد الزامی است.'; continue; }
        $value=normalizeDigits($raw);
        if (strlen($value)>120) { $errors[$name]='طول ورودی نامعتبر است.'; continue; }
        $rule=$spec['rule'];
        if ($rule==='iban') $value=strtoupper(str_replace(' ','',$value));
        if ($rule==='card'||$rule==='mobile') $value=str_replace([' ','-'],'',$value);
        $ok=match($rule) {
            'national_code'=>validNationalCode($value),
            'mobile'=>(bool)preg_match('/^09\d{9}$/D',$value),
            'card'=>validCard($value),
            'iban'=>validIban($value),
            'postal_code'=>(bool)preg_match('/^\d{10}$/D',$value),
            'account'=>(bool)preg_match('/^[0-9-]{4,30}$/D',$value),
            'bank'=>(bool)preg_match('/^[A-Za-z0-9_-]{2,20}$/D',$value),
            'plate'=>(bool)preg_match('/^[\p{L}\p{N}\s-]{5,32}$/uD',$value),
            default=>false,
        };
        if (!$ok) { $errors[$name]='مقدار واردشده معتبر نیست.'; continue; }
        $out[$name]=$value;
    }
    return [$out,$errors];
}
function ipHash(): string { return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', appKey()); }
function isoNow(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
function rateLimit(string $slug): bool {
    $pdo=db(); $now=time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $p=$pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE ip_hash=? AND created_at>=?');
        $p->execute([ipHash(),gmdate('Y-m-d\TH:i:s\Z',$now-60)]);
        $minute=(int)$p->fetchColumn();
        $p->execute([ipHash(),gmdate('Y-m-d\TH:i:s\Z',$now-3600)]);
        $hour=(int)$p->fetchColumn();
        if ($minute>=5 || $hour>=30) { $pdo->commit(); return false; }
        $pdo->prepare('INSERT INTO inquiries(service_slug,created_at,status,ip_hash) VALUES (?,?,?,?)')->execute([$slug,isoNow(),'pending',ipHash()]);
        $id=(int)$pdo->lastInsertId();
        $pdo->commit();
        $_SERVER['INQUIRY_ID']=$id;
        return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function finishInquiry(string $status, ?int $upstreamStatus, int $duration): void {
    db()->prepare('UPDATE inquiries SET status=?, upstream_status=?, duration_ms=? WHERE id=?')->execute([$status,$upstreamStatus,$duration,(int)($_SERVER['INQUIRY_ID']??0)]);
}
function configuredService(string $slug): ?array {
    $stmt=db()->prepare('SELECT * FROM services WHERE slug=?'); $stmt->execute([$slug]); $r=$stmt->fetch(); return $r?:null;
}
function makeZohalRequest(array $service, array $values): array {
    if (!extension_loaded('curl')) throw new RuntimeException('cURL extension is required');
    $apiBase=getenv('ZOHAL_API_BASE_URL') ?: 'https://api.zohal.io';
    $parts=parse_url($apiBase);
    $host=strtolower((string)($parts['host']??''));
    if (($parts['scheme']??'')!=='https' || (isset($parts['port'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])) || !($host==='zohal.io'||str_ends_with($host,'.zohal.io'))) {
        throw new RuntimeException('Invalid provider base URL');
    }
    $path=(string)$service['endpoint'];
    if (!preg_match('~^/[a-zA-Z0-9/_-]{1,190}$~D',$path)) throw new RuntimeException('Invalid provider endpoint');
    $map=json_decode($service['field_map'],true,16,JSON_THROW_ON_ERROR);
    if (!is_array($map)) throw new RuntimeException('Invalid parameter map');
    $body=[];
    foreach ($values as $field=>$value) {
        $mapped=$map[$field]??$field;
        if (!is_string($mapped)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D',$mapped)) throw new RuntimeException('Invalid parameter name');
        $body[$mapped]=$value;
    }
    $tokenEncrypted=setting('zohal_token');
    if (!$tokenEncrypted) throw new RuntimeException('Provider token not configured');
    $token=revealSecret($tokenEncrypted);
    $headerName=getenv('ZOHAL_AUTH_HEADER') ?: 'Authorization';
    if (!in_array(strtolower($headerName),['authorization','x-api-key','token','x-token'],true)) throw new RuntimeException('Invalid auth header');
    $prefix=getenv('ZOHAL_AUTH_PREFIX');
    if ($prefix===false) $prefix='Bearer ';
    if (!preg_match('/^[a-zA-Z ]{0,24}$/D',$prefix)) throw new RuntimeException('Invalid auth prefix');
    $method=$service['method'];
    if (!in_array($method,['POST','GET'],true)) throw new RuntimeException('Invalid method');
    $url=rtrim($apiBase,'/').$path;
    if ($method==='GET') $url.='?'.http_build_query($body,'','&',PHP_QUERY_RFC3986);
    $c=curl_init($url);
    $options=[CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>[$headerName.': '.$prefix.$token,'Accept: application/json','Content-Type: application/json'],
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_MAXREDIRS=>0];
    if ($method==='POST') { $options[CURLOPT_POST]=true; $options[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    curl_setopt_array($c,$options);
    try {
        $raw=curl_exec($c);
        $http=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
        if ($raw===false) throw new RuntimeException('Connection to provider failed');
        if (strlen($raw)>262144) throw new RuntimeException('Provider response too large');
        $data=json_decode($raw,true,24,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Unexpected provider response');
        return ['status'=>$http,'data'=>$data];
    } finally { curl_close($c); }
}
/** Persian/Jalali date; UTC persisted in DB, converted to Tehran before formatting. */
function jalali(string $utc): string {
    $dt=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'));
    $gy=(int)$dt->format('Y'); $gm=(int)$dt->format('n'); $gd=(int)$dt->format('j');
    $g_days=[0,31,($gy%4===0 && ($gy%100!==0||$gy%400===0))?29:28,31,30,31,30,31,31,30,31,30,31];
    $gy2=$gm>2?$gy+1:$gy;
    $days=355666+365*$gy+intdiv($gy2+3,4)-intdiv($gy2+99,100)+intdiv($gy2+399,400)+$gd;
    for($i=1;$i<$gm;$i++) $days+=$g_days[$i];
    $jy=-1595+33*intdiv($days,12053); $days%=12053;
    $jy+=4*intdiv($days,1461); $days%=1461;
    if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}
    if($days<186){$jm=1+intdiv($days,31);$jd=1+$days%31;}
    else {$jm=7+intdiv($days-186,30);$jd=1+($days-186)%30;}
    return sprintf('%04d/%02d/%02d %s',$jy,$jm,$jd,$dt->format('H:i'));
}
function loginRateLimited(string $username): bool {
    db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([gmdate('Y-m-d\TH:i:s\Z',time()-86400)]);
    $p=db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE created_at >= ? AND (ip_hash = ? OR username=?)');
    $p->execute([gmdate('Y-m-d\TH:i:s\Z',time()-900),ipHash(),$username]);
    return (int)$p->fetchColumn() >= 5;
}
