<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
</head>
<body style="background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px">
<div style="width:100%;max-width:420px">

    <!-- Logo -->
    <div style="text-align:center;margin-bottom:32px">
        <div style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:inline-flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:#0a0c10;margin-bottom:14px">Φ</div>
        <h1 style="font-size:22px;font-weight:700;color:var(--text)">Linda Chronicles</h1>
        <p style="font-size:13px;color:var(--text3);margin-top:4px">Pharmacy Management System</p>
    </div>

    <!-- Card -->
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:32px;box-shadow:0 8px 32px rgba(0,0,0,.4)">
        <h2 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:6px">Sign in</h2>
        <p style="font-size:13px;color:var(--text3);margin-bottom:24px">Enter your credentials to continue</p>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" style="margin-bottom:20px">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" style="margin-bottom:20px">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/?action=login">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-group">
                <label for="email" style="font-size:13px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="you@example.com" autocomplete="email"
                    style="width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:14px;font-family:inherit;outline:none;transition:border-color .2s"
                    onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border2)'">
            </div>

            <div class="form-group">
                <label for="password" style="font-size:13px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password"
                    style="width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:14px;font-family:inherit;outline:none;transition:border-color .2s"
                    onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border2)'">
            </div>

            <button type="submit"
                style="width:100%;padding:11px;background:var(--accent);color:#0a0c10;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-top:8px;transition:all .2s;font-family:inherit"
                onmouseover="this.style.background='#2dd68a'" onmouseout="this.style.background='var(--accent)'">
                <i class="fas fa-sign-in-alt" style="margin-right:6px"></i>Sign In
            </button>
        </form>

        <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);font-size:13px;color:var(--text3)">
            Don't have an account?
            <a href="<?php echo APP_URL; ?>/?action=register" style="color:var(--accent2);font-weight:600;text-decoration:none;margin-left:4px">Create one</a>
        </div>
    </div>

    <!-- Test accounts hint -->
    <div style="margin-top:20px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:8px">Test Accounts (password: password123)</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
            <?php
            $accounts = [
                ['intern@pharmacy.local','Intern'],
                ['hr@pharmacy.local','HR Personnel'],
                ['technician@pharmacy.local','Technician'],
                ['pharmacist@pharmacy.local','Pharmacist'],
                ['assistant@pharmacy.local','Pharm. Asst.'],
                ['customer@pharmacy.local','Customer'],
            ];
            foreach ($accounts as $a) {
                echo '<div style="font-size:11px;color:var(--text2);padding:2px 0">';
                echo '<span style="color:var(--accent2)">' . $a[1] . ':</span> ' . $a[0];
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
