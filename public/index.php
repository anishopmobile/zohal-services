<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/app.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('Cache-Control: no-store');
startSecureSession();

function navigate(string $url): never { header('Location: '.$url, true, 303); exit; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function message(): string { $m=(string)($_SESSION['flash']??''); unset($_SESSION['flash']); return $m; }
function hasAdmin(): bool { return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn()>0; }
function layoutStart(string $title, bool $admin=false): void { ?>
<!doctype html>
<html lang="fa" dir="rtl" data-bs-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#12254b"><title><?=e($title)?> | مرکز استعلام آنلاین</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler.rtl.min.css">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <header class="navbar navbar-expand-md site-navbar"><div class="container-xl">
    <a class="navbar-brand logo" href="/"><span class="logo-mark">✦</span><span>استعلام<span class="logo-accent">‌یار</span></span></a>
    <div class="d-flex align-items-center gap-3">
      <span class="header-note d-none d-md-inline">درگاه یکپارچه استعلام‌های آنلاین</span>
      <?php if ($admin && isAdmin()): ?><a class="btn btn-sm btn-ghost-secondary" href="/admin">داشبورد</a><form method="post" action="/admin/logout"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><button class="btn btn-sm btn-outline-secondary" type="submit">خروج</button></form>
      <?php else: ?><a class="btn btn-sm btn-outline-primary" href="/admin/login">مدیریت</a><?php endif; ?>
    </div>
  </div></header>
<?php }
function layoutEnd(): void { ?>
  <footer class="site-footer"><div class="container-xl d-flex flex-wrap justify-content-between gap-2"><span>© <?=e(date('Y'))?> مرکز خدمات استعلامی</span><span>اتصال امن به سرویس‌دهنده · حریم خصوصی کاربران</span></div></footer>
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/js/tabler.min.js" defer></script><script src="/assets/app.js" defer></script>
</body></html>
<?php }
function errorPage(string $title,string $detail,int $status=400): never { http_response_code($status); layoutStart($title); ?>
 <main class="container-xl py-5 text-center"><div class="card error-card p-5"><div class="display-5 mb-3">⚠️</div><h1><?=e($title)?></h1><p class="text-secondary"><?=e($detail)?></p><a href="/" class="btn btn-primary mx-auto">بازگشت به صفحه اصلی</a></div></main>
<?php layoutEnd(); exit; }

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    if ($path==='/api/inquiry' && $method==='POST') {
        verifyCsrf();
        $slug=$_POST['service']??null;
        if (!is_string($slug)||!array_key_exists($slug,catalog())) sendJson(['ok'=>false,'message'=>'سرویس موردنظر یافت نشد.'],404);
        $service=configuredService($slug);
        if (!$service || !(int)$service['enabled'] || $service['endpoint']==='' || !setting('zohal_token')) sendJson(['ok'=>false,'message'=>'این سرویس هنوز فعال یا آماده استفاده نیست.'],503);
        if (($_POST['consent']??'')!=='yes') sendJson(['ok'=>false,'message'=>'برای استعلام باید رضایت و مجوز استفاده از اطلاعات را تأیید کنید.'],422);
        [$values,$errors]=validateFields(catalog()[$slug]['fields'],$_POST);
        if ($errors) sendJson(['ok'=>false,'message'=>'لطفاً اطلاعات را بررسی کنید.','errors'=>$errors],422);
        if (!rateLimit($slug)) sendJson(['ok'=>false,'message'=>'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'],429);
        $started=microtime(true); $http=null;
        try {
            $response=makeZohalRequest($service,$values); $http=$response['status'];
            $businessError=isset($response['data']['success']) && $response['data']['success']===false;
            $ok=$http>=200 && $http<300 && !$businessError;
            finishInquiry($ok?'success':'provider_error',$http,(int)((microtime(true)-$started)*1000));
            if (!$ok) sendJson(['ok'=>false,'message'=>'سرویس‌دهنده نتیجه موفقی بازنگرداند. لطفاً بعداً دوباره تلاش کنید.'],502);
            sendJson(['ok'=>true,'message'=>'پاسخ استعلام دریافت شد.','result'=>$response['data']]);
        } catch (Throwable $error) {
            finishInquiry('error',$http,(int)((microtime(true)-$started)*1000));
            error_log('[provider] '.get_class($error));
            sendJson(['ok'=>false,'message'=>'ارتباط با سرویس‌دهنده ممکن نشد. بعداً تلاش کنید.'],502);
        }
    }
    if ($path==='/admin/setup' && $method==='POST') {
        verifyCsrf();
        if (hasAdmin()) navigate('/admin/login');
        $setupKey=getenv('APP_SETUP_KEY') ?: '';
        $user=trim((string)($_POST['username']??'')); $pass=$_POST['password']??'';
        if (strlen($setupKey)<16 || !is_string($pass) || !hash_equals($setupKey,(string)($_POST['setup_key']??'')) || !preg_match('/^[a-zA-Z0-9_]{4,32}$/D',$user) || strlen($pass)<12 || $pass!==($_POST['confirm_password']??null)) {
            authFailed('کد راه‌اندازی یا اطلاعات حساب معتبر نیست. نام کاربری لاتین و رمز حداقل ۱۲ کاراکتری الزامی است.','/admin/setup');
        }
        $pdo=db();
        $setupLock=$pdo->query("SELECT GET_LOCK('zohal_first_admin', 5)")->fetchColumn();
        if ((int)$setupLock!==1) authFailed('ترافیک راه‌اندازی زیاد است. دوباره امتحان کنید.','/admin/setup');
        try {
            $pdo->beginTransaction();
            if ((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn()!==0) { $pdo->rollBack(); authFailed('حساب مدیر قبلاً ساخته شده است.','/admin/login'); }
            $pdo->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES (?,?,?)')->execute([$user,password_hash($pass,PASSWORD_DEFAULT),isoNow()]);
            $adminId=(int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $x) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $x; }
        finally { $pdo->query("SELECT RELEASE_LOCK('zohal_first_admin')")->fetchColumn(); }
        session_regenerate_id(true); $_SESSION['admin_id']=$adminId; $_SESSION['created']=time();
        flash('حساب مدیر ساخته شد. ابتدا توکن و مسیرهای API را تنظیم کنید.'); authSucceeded('/admin');
    }
    if ($path==='/admin/login' && $method==='POST') {
        verifyCsrf();
        $user=substr(trim((string)($_POST['username']??'')),0,100); $pass=$_POST['password']??'';
        if (!is_string($pass)) $pass='';
        if (loginRateLimited($user)) authFailed('تلاش‌های ورود بیش از حد مجاز است. ۱۵ دقیقه بعد امتحان کنید.','/admin/login');
        $q=db()->prepare('SELECT * FROM admins WHERE username=?'); $q->execute([$user]); $admin=$q->fetch();
        if (!$admin || !password_verify($pass,$admin['password_hash'])) {
            db()->prepare('INSERT INTO login_attempts(username,ip_hash,created_at) VALUES (?,?,?)')->execute([$user,ipHash(),isoNow()]);
            authFailed('نام کاربری یا رمز عبور اشتباه است.','/admin/login');
        }
        db()->prepare('DELETE FROM login_attempts WHERE username=? OR ip_hash=?')->execute([$user,ipHash()]);
        session_regenerate_id(true); $_SESSION['admin_id']=(int)$admin['id']; $_SESSION['created']=time();
        if (isset($_POST['remember_me'])) { revokeRememberToken(); issueRememberToken((int)$admin['id']); }
        else { revokeRememberToken(); }
        authSucceeded('/admin');
    }
    if ($path==='/admin/logout' && $method==='POST') {
        verifyCsrf(); revokeRememberToken(); $_SESSION=[]; session_destroy(); navigate('/admin/login');
    }
    if ($path==='/admin/settings' && $method==='POST') {
        requireAdmin(); verifyCsrf();
        $token=trim((string)($_POST['token']??''));
        if ($token!=='' && !preg_match('/^[A-Za-z0-9]{40}$/D',$token)) { flash('توکن باید دقیقاً ۴۰ کاراکتر حرف یا عدد باشد.'); navigate('/admin'); }
        if ($token!=='') storeSetting('zohal_token',sealSecret($token));
        flash($token===''?'توکن قبلی بدون تغییر باقی ماند.':'توکن با رمزنگاری ذخیره شد.'); navigate('/admin');
    }
    if ($path==='/admin/service' && $method==='POST') {
        requireAdmin(); verifyCsrf();
        $slug=$_POST['slug']??''; $endpoint=$_POST['endpoint']??''; $type=$_POST['method']??''; $mapText=$_POST['field_map']??'';
        if (!is_string($slug)||!isset(catalog()[$slug])||!is_string($endpoint)||!is_string($type)||!is_string($mapText) || !in_array($type,['GET','POST'],true) || ($endpoint!=='' && !preg_match('~^/[a-zA-Z0-9/_-]{1,190}$~D',$endpoint))) {
            flash('مسیر یا روش ارتباط با API نامعتبر است.'); navigate('/admin');
        }
        $map=json_decode($mapText,true);
        if (!is_array($map) || array_is_list($map) && $map!==[]) { flash('نگاشت فیلدها باید شیء JSON باشد.'); navigate('/admin'); }
        foreach($map as $from=>$to) {
            if (!array_key_exists($from,catalog()[$slug]['fields'])||!is_string($to)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D',$to)) {
                flash('نام پارامترهای نگاشت‌شده نامعتبر است.'); navigate('/admin');
            }
        }
        db()->prepare('UPDATE services SET enabled=?,endpoint=?,method=?,field_map=? WHERE slug=?')->execute([isset($_POST['enabled'])?1:0,$endpoint,$type,json_encode($map,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$slug]);
        flash('تنظیمات سرویس ذخیره شد.'); navigate('/admin#services');
    }
    if ($path==='/admin' && $method==='GET') {
        if (!isAdmin()) navigate('/admin/login');
        $services=db()->query('SELECT * FROM services ORDER BY slug')->fetchAll();
        $counts=[];
        foreach(db()->query("SELECT service_slug,COUNT(*) AS total,SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) AS succeeded FROM inquiries GROUP BY service_slug") as $r) $counts[$r['service_slug']]=$r;
        $recent=db()->query('SELECT service_slug,created_at,status,upstream_status,duration_ms FROM inquiries ORDER BY id DESC LIMIT 60')->fetchAll();
        $daily=[];
        foreach(db()->query("SELECT created_at FROM inquiries ORDER BY id DESC") as $row) {
            $day=substr(jalali($row['created_at']),0,10); $daily[$day]=($daily[$day]??0)+1;
        }
        $total=(int)db()->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();
        $success=(int)db()->query("SELECT COUNT(*) FROM inquiries WHERE status='success'")->fetchColumn();
        layoutStart('داشبورد مدیریت',true); ?>
        <main class="container-xl py-4 py-md-5">
          <div class="page-heading"><div><div class="eyebrow">پنل مدیریت</div><h1>داشبورد سرویس‌ها</h1><p>وضعیت درخواست‌ها، تنظیمات و دسترسی به درگاه زحل</p></div><span class="pill"><span class="pulse"></span> سامانه فعال</span></div>
          <?php if($m=message()): ?><div class="alert alert-info" role="status"><?=e($m)?></div><?php endif; ?>
          <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3"><div class="metric"><span>کل استعلام‌ها</span><strong><?=e(number_format($total))?></strong><small>از آغاز فعالیت</small></div></div>
            <div class="col-sm-6 col-xl-3"><div class="metric"><span>استعلام موفق</span><strong><?=e(number_format($success))?></strong><small>بر اساس پاسخ سرویس‌دهنده</small></div></div>
            <div class="col-sm-6 col-xl-3"><div class="metric"><span>سرویس‌های فعال</span><strong><?=e(count(array_filter($services,fn($s)=>(int)$s['enabled']===1 && $s['endpoint']!=='')))?></strong><small>مسیر ثبت‌شده و فعال</small></div></div>
            <div class="col-sm-6 col-xl-3"><div class="metric"><span>وضعیت توکن</span><strong class="status-label"><?=setting('zohal_token')?'ثبت‌شده':'تنظیم‌نشده'?></strong><small>توکن هیچ‌گاه نمایش داده نمی‌شود</small></div></div>
          </div>
          <div class="row g-4">
            <div class="col-lg-5"><section class="card shadow-sm h-100"><div class="card-header"><h2 class="card-title">اتصال به زحل</h2></div><div class="card-body"><p class="text-secondary">توکن ۴۰ کاراکتری را وارد کنید. فیلد خالی، توکن کنونی را تغییر نمی‌دهد.</p><form method="post" action="/admin/settings" autocomplete="off">
              <input type="hidden" name="_csrf" value="<?=e(csrf())?>"><label class="form-label" for="token">توکن API</label><input class="form-control" name="token" id="token" type="password" pattern="[A-Za-z0-9]{40}" minlength="40" maxlength="40" placeholder="••••••••••••••••••••" autocomplete="new-password"><button type="submit" class="btn btn-primary mt-3">ذخیره امن توکن</button></form><div class="subnote">رمزنگاری در سرور با Sodium · پشتیبانی از بروزرسانی توکن</div></div></section></div>
            <div class="col-lg-7"><section class="card shadow-sm h-100"><div class="card-header"><h2 class="card-title">آمار سرویس‌ها</h2></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>سرویس</th><th>کل</th><th>موفق</th></tr></thead><tbody>
            <?php foreach(catalog() as $slug=>$def): $c=$counts[$slug]??['total'=>0,'succeeded'=>0]; ?><tr><td><?=e($def['title'])?></td><td><?=e((string)$c['total'])?></td><td><?=e((string)$c['succeeded'])?></td></tr><?php endforeach; ?>
            </tbody></table></div></section></div>
          </div>
          <section class="card shadow-sm mt-4" id="services"><div class="card-header"><div><h2 class="card-title">مدیریت سرویس‌ها</h2><small class="text-secondary">مسیر و نگاشت پارامترها باید دقیقاً با مستندات رسمی زحل تنظیم شوند.</small></div></div><div class="card-body"><div class="row g-3">
          <?php foreach($services as $s): $d=catalog()[$s['slug']]; ?><div class="col-lg-6"><form method="post" action="/admin/service" class="service-setting">
            <input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="slug" value="<?=e($s['slug'])?>">
            <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h4 m-0"><?=e($d['title'])?></h3><label class="form-check form-switch"><input type="checkbox" name="enabled" class="form-check-input" value="1" <?=(int)$s['enabled']?'checked':''?>><span class="form-check-label">فعال</span></label></div>
            <label class="form-label">مسیر API (نسبت به آدرس پایه)</label><input class="form-control ltr" name="endpoint" dir="ltr" maxlength="191" value="<?=e($s['endpoint'])?>" placeholder="/path/from/provider/docs">
            <div class="row g-2 mt-1"><div class="col-4"><label class="form-label">متد</label><select class="form-select" name="method"><option value="POST" <?=$s['method']==='POST'?'selected':''?>>POST</option><option value="GET" <?=$s['method']==='GET'?'selected':''?>>GET</option></select></div><div class="col-8"><label class="form-label">نگاشت فیلدها (JSON)</label><input class="form-control ltr" dir="ltr" name="field_map" value="<?=e($s['field_map'])?>" aria-label="JSON parameter map"></div></div>
            <button class="btn btn-outline-primary mt-3" type="submit">ذخیره سرویس</button>
          </form></div><?php endforeach; ?>
          </div></div></section>
          <div class="row g-4 mt-0"><div class="col-lg-5"><section class="card shadow-sm"><div class="card-header"><h2 class="card-title">تعداد استعلام بر اساس تاریخ شمسی</h2></div><div class="table-responsive"><table class="table card-table"><thead><tr><th>تاریخ</th><th>تعداد</th></tr></thead><tbody>
            <?php foreach(array_slice($daily,0,30,true) as $date=>$count): ?><tr><td dir="ltr"><?=e($date)?></td><td><?=e($count)?></td></tr><?php endforeach; ?>
            <?php if(!$daily): ?><tr><td colspan="2">هنوز استعلامی ثبت نشده است.</td></tr><?php endif; ?>
          </tbody></table></div></section></div>
          <div class="col-lg-7"><section class="card shadow-sm"><div class="card-header"><h2 class="card-title">آخرین درخواست‌ها (زمان شمسی ایران)</h2></div><div class="table-responsive"><table class="table card-table"><thead><tr><th>زمان</th><th>سرویس</th><th>وضعیت</th></tr></thead><tbody>
          <?php foreach($recent as $row): ?><tr><td class="text-nowrap" dir="ltr"><?=e(jalali($row['created_at']))?></td><td><?=e(catalog()[$row['service_slug']]['title']??$row['service_slug'])?></td><td><span class="badge <?=$row['status']==='success'?'bg-success-lt':'bg-warning-lt'?>"><?=e(['success'=>'موفق','pending'=>'در انتظار','error'=>'خطای ارتباط','provider_error'=>'پاسخ ناموفق'][$row['status']]??$row['status'])?></span></td></tr><?php endforeach; ?>
          <?php if(!$recent): ?><tr><td colspan="3">درخواستی ثبت نشده است.</td></tr><?php endif; ?>
          </tbody></table></div></section></div></div>
        </main>
        <?php layoutEnd(); exit;
    }
    if (($path==='/admin/login'||$path==='/admin/setup') && $method==='GET') {
        if ($path==='/admin/setup' && hasAdmin()) navigate('/admin/login');
        if ($path==='/admin/login' && isAdmin()) navigate('/admin');
        $setup=$path==='/admin/setup';
        layoutStart($setup?'ساخت حساب مدیر':'ورود به مدیریت'); ?>
        <main class="auth-wrap"><div class="auth-card card shadow-lg"><div class="card-body p-4 p-md-5"><div class="auth-icon">🔐</div><div class="eyebrow">دسترسی ویژه مدیریت</div><h1 class="h2 mt-2 mb-2"><?=$setup?'راه‌اندازی مدیر اصلی':'خوش آمدید'?></h1><p class="text-secondary mb-4"><?=$setup?'این فرم تنها برای ساخت اولین مدیر در دسترس است.':'برای ورود، اطلاعات حساب مدیریت را وارد کنید.'?></p>
        <?php if($m=message()): ?><div class="alert alert-warning"><?=e($m)?></div><?php endif; ?>
        <?php $oldUsername=(string)($_SESSION['old_username']??''); unset($_SESSION['old_username']); $oldRemember=(bool)($_SESSION['old_remember']??false); unset($_SESSION['old_remember']); ?>
        <form method="post" id="auth-form" data-auth-form action="<?=$setup?'/admin/setup':'/admin/login'?>" novalidate>
          <input type="hidden" name="_csrf" value="<?=e(csrf())?>">
          <div class="alert alert-danger" id="auth-error" role="alert" aria-live="polite" hidden></div>
          <div class="mb-3"><label class="form-label" for="auth-username">نام کاربری</label><input id="auth-username" class="form-control ltr" name="username" dir="ltr" required minlength="4" maxlength="32" autocomplete="username" value="<?=e($oldUsername)?>"></div>
          <div class="mb-3"><label class="form-label" for="auth-password">رمز عبور</label><input id="auth-password" class="form-control ltr" name="password" type="password" required minlength="12" autocomplete="<?=$setup?'new-password':'current-password'?>" aria-describedby="password-feedback"><div id="password-feedback" class="form-text" aria-live="polite">رمز عبور باید حداقل ۱۲ کاراکتر باشد.</div></div>
          <?php if ($setup): ?>
          <div class="mb-3"><label class="form-label" for="auth-confirm">تکرار رمز عبور</label><input id="auth-confirm" class="form-control ltr" name="confirm_password" type="password" required minlength="12" autocomplete="new-password" aria-describedby="confirm-feedback"><div id="confirm-feedback" class="form-text" aria-live="polite"></div></div>
          <div class="mb-3"><label class="form-label" for="auth-setup-key">کلید راه‌اندازی (APP_SETUP_KEY)</label><input id="auth-setup-key" class="form-control ltr" name="setup_key" type="password" required autocomplete="off"></div>
          <?php else: ?>
          <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember_me" value="1" <?=$oldRemember?'checked':''?>><span class="form-check-label">به خاطر سپردن ورود (۳۰ روز)</span></label>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary w-100 btn-lg" id="auth-submit"><?=$setup?'ایجاد حساب مدیریت':'ورود امن'?></button>
        </form>
        <div class="mt-4 text-center"><a href="/">بازگشت به سایت</a><?php if (!$setup&&!hasAdmin()): ?> · <a href="/admin/setup">ساخت اولین مدیر</a><?php endif; ?></div></div></div></main>
        <?php layoutEnd(); exit;
    }
    if ($path!=='/' || $method!=='GET') errorPage('صفحه پیدا نشد','آدرس موردنظر وجود ندارد.',404);
    $rows=db()->query('SELECT * FROM services')->fetchAll(); $settings=[];
    foreach($rows as $row) $settings[$row['slug']]=$row;
    $tokenReady=(bool)setting('zohal_token');
    layoutStart('خدمات استعلامی'); ?>
    <main>
      <section class="hero"><div class="container-xl hero-inner"><div class="row align-items-center g-4"><div class="col-lg-7"><div class="hero-kicker">✦ درگاه جامع خدمات الکترونیک</div><h1>استعلام‌های ضروری،<br><span>سریع‌تر از همیشه.</span></h1><p>از احراز هویت و خدمات بانکی تا استعلام‌های خودرو و کد پستی؛ همه خدمات در یک مسیر ساده و امن.</p><a href="#services" class="btn btn-primary btn-lg hero-cta">مشاهده خدمات <span aria-hidden="true">←</span></a><div class="hero-trust"><span>✓ بدون نیاز به ثبت‌نام</span><span>✓ نمایش شفاف نتیجه</span><span>✓ سازگار با موبایل</span></div></div>
      <div class="col-lg-5 d-none d-lg-block"><div class="hero-visual"><div class="visual-orbit"></div><div class="visual-core">✦<small>استعلام‌یار</small></div><div class="floating-chip chip-top">🛡️ <span>استعلام هویت</span><b>امن</b></div><div class="floating-chip chip-bottom">💳 <span>خدمات بانکی</span><b>آنلاین</b></div><div class="visual-dot dot-one"></div><div class="visual-dot dot-two"></div></div></div></div></div></section>
      <section class="container-xl services-section" id="services"><div class="section-top"><div><div class="eyebrow">خدمات در دسترس</div><h2>چه استعلامی نیاز دارید؟</h2><p>سرویس موردنظر را انتخاب کرده و اطلاعات لازم را وارد کنید.</p></div><div class="search-wrap"><input id="service-search" type="search" class="form-control" placeholder="جستجو در خدمات..." aria-label="جستجوی خدمات"><span>⌕</span></div></div>
      <div class="filter-tabs" role="group" aria-label="دسته‌بندی خدمات"><button class="filter-tab active" data-category="all">همه خدمات <span><?=e(count(catalog()))?></span></button><button class="filter-tab" data-category="هویتی">هویتی</button><button class="filter-tab" data-category="بانکی">بانکی</button><button class="filter-tab" data-category="خدماتی">خدماتی</button></div>
      <div class="row g-3" id="service-grid">
      <?php foreach(catalog() as $slug=>$service): $configured=$tokenReady && !empty($settings[$slug]['endpoint']) && (int)($settings[$slug]['enabled']??0)===1; ?><div class="col-md-6 col-xl-4 service-item" data-category="<?=e($service['category'])?>" data-title="<?=e($service['title'])?>"><article class="service-card"><div class="service-card-top"><span class="service-icon"><?=e($service['icon'])?></span><span class="service-state <?=$configured?'ready':'soon'?>"><?=$configured?'آماده استعلام':'در حال راه‌اندازی'?></span></div><h3><?=e($service['title'])?></h3><p><?=e($service['subtitle'])?></p><div class="service-card-bottom"><span><?=e($service['category'])?></span><button class="service-action" type="button" data-open="<?=e($slug)?>" <?=$configured?'':'disabled'?> aria-label="استعلام <?=e($service['title'])?>"><?=$configured?'شروع استعلام':'فعلاً غیرفعال'?> <span>←</span></button></div></article></div><?php endforeach; ?>
      </div><div class="empty-state" id="no-results" hidden>خدمتی با این عنوان پیدا نشد.</div></section>
      <section class="container-xl"><div class="assurance"><div class="assurance-symbol">🔒</div><div><h3>حریم خصوصی، اولویت ماست</h3><p>اطلاعات ورودی در سابقه درخواست‌های سایت ذخیره نمی‌شوند و فقط برای پردازش به ارائه‌دهنده سرویس منتقل می‌گردند. برای هر استعلام، رضایت شما لازم است.</p></div></div></section>
    </main>
    <div class="modal fade" id="inquiry-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow">فرم درخواست استعلام</div><h2 class="modal-title" id="inquiry-title">استعلام</h2></div><button class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button></div><div class="modal-body"><p class="text-secondary" id="inquiry-subtitle"></p><form id="inquiry-form" novalidate><input type="hidden" name="service" id="service-slug"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><div id="inquiry-fields"></div><label class="form-check mt-3"><input type="checkbox" class="form-check-input" name="consent" value="yes" required><span class="form-check-label">تأیید می‌کنم مجاز به استعلام این اطلاعات هستم و با ارسال آن به سرویس‌دهنده موافقم.</span></label><button type="submit" class="btn btn-primary w-100 mt-4" id="submit-inquiry">دریافت نتیجه استعلام</button></form><div id="inquiry-result" class="inquiry-result" role="status" aria-live="polite" hidden></div></div></div></div></div>
    <script type="application/json" id="catalog-json"><?=json_encode(catalog(),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
    <?php layoutEnd(); exit;
} catch (Throwable $error) {
    error_log('[app] '.get_class($error).' in '.$error->getFile().':'.$error->getLine().' '.$error->getMessage());
    if (str_starts_with($path,'/api/')) sendJson(['ok'=>false,'message'=>'خطای داخلی سرویس.'],500);
    errorPage('خطای پیکربندی','در اجرای سامانه مشکلی رخ داده است. تنظیمات سرور و پایگاه داده را بررسی کنید.',500);
}
