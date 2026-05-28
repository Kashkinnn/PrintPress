<?php
require_once 'connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Home';
$showNav   = false;
require_once 'includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:center; min-height:calc(100vh - 70px - 53px); padding:2rem;">
  <div style="text-align:center; max-width:480px;">
    <div style="font-family:'Playfair Display',serif; font-size:2.8rem; font-weight:900; color:var(--gray-800); line-height:1.1; margin-bottom:0.75rem;">
      The <span style="color:var(--maroon);">PrintPress</span>
    </div>
    <p style="color:var(--gray-400); font-size:0.95rem; margin-bottom:2rem; line-height:1.6;">
      Your campus printing hub. Find available stations, check supplies, and manage your print jobs — all in one place.
    </p>
    <div style="display:flex; gap:0.75rem; justify-content:center;">
      <a href="login.php"    class="pp-btn pp-btn-primary"    style="width:auto; padding:0.7rem 2rem;">Log In</a>
      <a href="register.php" class="pp-btn pp-btn-secondary"  style="padding:0.7rem 2rem;">Register</a>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
