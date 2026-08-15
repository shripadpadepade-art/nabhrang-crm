<?php
require __DIR__ . '/../config/bootstrap.php';
if (!empty($_SESSION['admin_id'])) { header('Location: /admin/index.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf(); $now=time();
  $_SESSION['login_attempts']=array_values(array_filter($_SESSION['login_attempts']??[],fn($at)=>$at>$now-600));
  if(count($_SESSION['login_attempts'])>=5){ $error='कृपया काही वेळाने पुन्हा प्रयत्न करा.'; }
  else {
    $stmt=$pdo->prepare('SELECT id,username,password_hash,full_name_mr,role FROM admin_users WHERE username=? AND status="active" LIMIT 1'); $stmt->execute([trim($_POST['username']??'')]); $admin=$stmt->fetch();
    if($admin && password_verify($_POST['password']??'', $admin['password_hash'])) { session_regenerate_id(true); $_SESSION['admin_id']=$admin['id']; $_SESSION['admin_name']=$admin['full_name_mr']; $_SESSION['admin_role']=$admin['role']; $pdo->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?')->execute([$admin['id']]); audit($pdo,'login','admin_user',(int)$admin['id']); header('Location: /admin/index.php'); exit; }
    $_SESSION['login_attempts'][]=$now; $error='वापरकर्तानाव किंवा पासवर्ड चुकीचा आहे.';
  }
}
?>
<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title><link rel="stylesheet" href="/assets/css/nabhrang.css"></head><body class="login-page"><main class="login-card"><div class="brand" data-testid="login-brand"><?=e(setting($pdo,'organization_name'))?><small>Admin Studio</small></div><h1>स्वागत आहे</h1><p class="subtitle">तुमच्या सांस्कृतिक व्यासपीठाचे नियंत्रण केंद्र</p><?php if($error): ?><div class="error" data-testid="login-error"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="field"><label for="username">वापरकर्तानाव</label><input id="username" name="username" autocomplete="username" required data-testid="login-username-input"></div><div class="field"><label for="password">पासवर्ड</label><input id="password" type="password" name="password" autocomplete="current-password" required data-testid="login-password-input"></div><button class="btn btn-gold" style="width:100%" type="submit" data-testid="login-submit-button">सुरक्षित प्रवेश</button></form><a href="/" style="display:block;text-align:center;margin-top:22px;color:var(--muted)" data-testid="back-home-link">← वेबसाइटवर परत जा</a></main></body></html>