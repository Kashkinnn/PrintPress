<?php
require_once 'connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (isset($_POST['btnLogin'])) {
    $uname = trim($_POST['txtusername']);
    $pwd   = $_POST['txtpassword'];

    if ($uname === '' || $pwd === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $connection->prepare(
            "SELECT id, username, password, role FROM tbluseraccount WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param('s', $uname);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = 'Username does not exist.';
        } else {
            $row = $result->fetch_assoc();
            if (!password_verify($pwd, $row['password'])) {
                $error = 'Incorrect password.';
            } else {
                $_SESSION['user_id']  = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'];
                header('Location: dashboard.php');
                exit;
            }
        }
        $stmt->close();
    }
}

$pageTitle = 'Login';
$showNav   = false;
require_once 'includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:center; min-height:calc(100vh - 70px - 53px); padding:2rem 1rem;">
  <div class="pp-login-card">

    <div class="pp-login-heading">
      <h2>Need Papers Printed?</h2>
      <p>Login with your CIT-U Account</p>
    </div>

    <?php if ($error): ?>
      <div class="pp-alert pp-alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="pp-form-group">
        <label for="txtusername">Username</label>
        <input class="pp-input" type="text" id="txtusername" name="txtusername"
               placeholder="Enter your username"
               value="<?php echo isset($_POST['txtusername']) ? htmlspecialchars($_POST['txtusername']) : ''; ?>"
               autocomplete="username">
      </div>
      <div class="pp-form-group">
        <label for="txtpassword">Password</label>
        <input class="pp-input" type="password" id="txtpassword" name="txtpassword"
               placeholder="Enter your password"
               autocomplete="current-password">
      </div>
      <div class="mt-2">
        <button class="pp-btn pp-btn-primary" type="submit" name="btnLogin">Login</button>
      </div>
    </form>

    <p class="pp-login-alt mt-2">
      No account yet? <a href="register.php">Register here</a>
    </p>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
