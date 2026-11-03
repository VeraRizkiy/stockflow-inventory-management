<?php
session_start();
require 'config/database.php';
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        header('Location: index.php'); exit;
    }
    $error = 'Username atau password tidak sesuai.';
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login | StockFlow</title><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-body"><div class="login-card"><a class="brand login-brand"><span class="brand-mark">S</span><span>Stock<span class="brand-light">Flow</span><small>OPERATIONS SYSTEM</small></span></a><p class="eyebrow">WELCOME BACK</p><h1>Sign in to your workspace.</h1><p class="muted">Kelola persediaan dan aktivitas produksi dalam satu tempat.</p>
<?php if($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="form-stack"><label>Username<input name="username" required placeholder="Masukkan username"></label><label>Password<input type="password" name="password" required placeholder="Masukkan password"></label><button class="btn primary full">Masuk ke Dashboard →</button></form><div class="demo-login"><b>Demo account</b><span>Username: <code>admin</code></span><span>Password: <code>admin123</code></span></div></div></body></html>
