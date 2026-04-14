<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Register — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.field{width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:14px;font-family:inherit;outline:none;transition:border-color .2s}
.field:focus{border-color:var(--accent)}
.lbl{font-size:13px;font-weight:500;color:var(--text2);display:block;margin-bottom:6px}
.fg{margin-bottom:16px}
</style>
</head>
<body style="background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px">
<div style="width:100%;max-width:500px">

    <div style="text-align:center;margin-bottom:28px">
        <div style="width:52px;height:52px;border-radius:13px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:inline-flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#0a0c10;margin-bottom:12px">Φ</div>
        <h1 style="font-size:20px;font-weight:700;color:var(--text)">Create Account</h1>
        <p style="font-size:13px;color:var(--text3);margin-top:4px">Join the pharmacy management system</p>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:32px;box-shadow:0 8px 32px rgba(0,0,0,.4)">

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" style="margin-bottom:20px">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo APP_URL; ?>/?action=register">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="fg">
                    <label class="lbl" for="first_name">First Name <span style="color:var(--danger)">*</span></label>
                    <input class="field" type="text" id="first_name" name="first_name" required placeholder="First name">
                </div>
                <div class="fg">
                    <label class="lbl" for="last_name">Last Name <span style="color:var(--danger)">*</span></label>
                    <input class="field" type="text" id="last_name" name="last_name" required placeholder="Last name">
                </div>
            </div>

            <div class="fg">
                <label class="lbl" for="middle_name">Middle Name <span style="color:var(--text3);font-weight:400">(optional)</span></label>
                <input class="field" type="text" id="middle_name" name="middle_name" placeholder="Middle name">
            </div>

            <div class="fg">
                <label class="lbl" for="email">Email Address <span style="color:var(--danger)">*</span></label>
                <input class="field" type="email" id="email" name="email" required placeholder="you@example.com">
            </div>

            <div class="fg">
                <label class="lbl" for="role_id">Role <span style="color:var(--danger)">*</span></label>
                <select class="field" id="role_id" name="role_id" required>
                    <option value="">Select your role</option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="fg">
                    <label class="lbl" for="password">Password <span style="color:var(--danger)">*</span></label>
                    <input class="field" type="password" id="password" name="password" required placeholder="Min 6 characters">
                </div>
                <div class="fg">
                    <label class="lbl" for="confirm_password">Confirm Password <span style="color:var(--danger)">*</span></label>
                    <input class="field" type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password">
                </div>
            </div>

            <button type="submit"
                style="width:100%;padding:11px;background:var(--accent);color:#0a0c10;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-top:4px;transition:all .2s;font-family:inherit"
                onmouseover="this.style.background='#2dd68a'" onmouseout="this.style.background='var(--accent)'">
                <i class="fas fa-user-plus" style="margin-right:6px"></i>Create Account
            </button>
        </form>

        <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);font-size:13px;color:var(--text3)">
            Already have an account?
            <a href="<?php echo APP_URL; ?>/?action=login" style="color:var(--accent2);font-weight:600;text-decoration:none;margin-left:4px">Sign in</a>
        </div>
    </div>
</div>
</body>
</html>
