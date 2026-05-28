<?php
require_once 'connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $success = '';

if (isset($_POST['btnRegister'])) {
    $username  = trim($_POST['txtusername']);
    $password  = $_POST['txtpassword'];
    $confirm   = $_POST['txtconfirm'];
    $full_name = trim($_POST['txtfullname']);
    $email     = trim($_POST['txtemail']);

    if ($username === '' || $password === '' || $full_name === '') {
        $error = 'Full name, username, and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $connection->prepare("SELECT id FROM tbluseraccount WHERE username = ? LIMIT 1");
        $chk->bind_param('s', $username);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = 'That username is already taken. Please choose another.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $connection->prepare(
                "INSERT INTO tbluseraccount (username, password, role, full_name, email) VALUES (?, ?, 'student', ?, ?)"
            );
            $stmt->bind_param('ssss', $username, $hashed, $full_name, $email);

            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                // Create matching tblstudent row
                $s = $connection->prepare(
                    "INSERT INTO tblstudent (account_id, Name, Email) VALUES (?, ?, ?)"
                );
                $s->bind_param('iss', $new_id, $full_name, $email);
                $s->execute();
                $s->close();
                $success = 'Account created! You can now log in.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
        $chk->close();
    }
}

$pageTitle = 'Register';
$showNav   = false;
require_once 'includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:center; min-height:calc(100vh - 70px - 53px); padding:2rem 1rem;">
  <div class="pp-login-card" style="max-width:430px;">

    <div class="pp-login-heading">
      <h2>Create an Account</h2>
      <p>Register as a student to use PrintPress</p>
    </div>

    <?php if ($error):   ?><div class="pp-alert pp-alert-danger"><?php echo htmlspecialchars($error);   ?></div><?php endif; ?>
    <?php if ($success): ?><div class="pp-alert pp-alert-success"><?php echo $success; ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" novalidate>
      <div class="pp-form-group">
        <label for="txtfullname">Full Name</label>
        <input class="pp-input" type="text" id="txtfullname" name="txtfullname"
               placeholder="e.g. Juan Dela Cruz"
               value="<?php echo htmlspecialchars($_POST['txtfullname'] ?? ''); ?>">
      </div>
      <div class="pp-form-group">
        <label for="txtemail">Email <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
        <input class="pp-input" type="email" id="txtemail" name="txtemail"
               placeholder="you@cit.edu"
               value="<?php echo htmlspecialchars($_POST['txtemail'] ?? ''); ?>">
      </div>
      <div class="pp-form-group">
        <label for="txtusername">Username</label>
        <input class="pp-input" type="text" id="txtusername" name="txtusername"
               placeholder="Choose a username"
               value="<?php echo htmlspecialchars($_POST['txtusername'] ?? ''); ?>">
      </div>
      <div class="pp-form-group">
        <label for="txtpassword">Password</label>
        <input class="pp-input" type="password" id="txtpassword" name="txtpassword"
               placeholder="Minimum 6 characters">
      </div>
      <div class="pp-form-group">
        <label for="txtconfirm">Confirm Password</label>
        <input class="pp-input" type="password" id="txtconfirm" name="txtconfirm"
               placeholder="Repeat your password">
      </div>
      <div class="mt-2">
        <button class="pp-btn pp-btn-primary" type="submit" name="btnRegister">Create Account</button>
      </div>
    </form>
    <?php else: ?>
      <div class="mt-2" style="text-align:center;">
        <a href="login.php" class="pp-btn pp-btn-primary" style="width:auto; padding:0.7rem 2rem;">Go to Login</a>
      </div>
    <?php endif; ?>

    <p class="pp-login-alt mt-2">
      Already have an account? <a href="login.php">Log in</a>
    </p>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
