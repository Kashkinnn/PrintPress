<?php
// includes/header.php
// Requires: $pageTitle (string, optional), $showNav (bool, optional)
if (!isset($pageTitle)) {
    $pageTitle = "PrintPress";
}
if (!isset($showNav)) {
    $showNav = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The PrintPress — <?php echo htmlspecialchars($pageTitle); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/site.css">
</head>
<body>

<header class="pp-navbar">
  <a class="pp-navbar-brand" href="index.php">
    <img src="images/cit_logo.png" alt="CIT-U" onerror="this.style.display='none'">
    <div class="pp-brand-school">
      <span>Cebu Institute of Technology</span>
      <span>University</span>
    </div>
    <div class="pp-brand-divider"></div>
    <div class="pp-brand-name">
      <span class="pp-brand-the">The</span>
      <span class="pp-brand-title">PRINTPRESS</span>
    </div>
  </a>

  </div>

  <div class="pp-navbar-right">
    <?php if ($showNav && isset($_SESSION["user_id"])): ?>
      <span class="pp-role-badge pp-role-<?php echo $_SESSION["role"]; ?>">
        <?php echo ucfirst($_SESSION["role"]); ?>
      </span>
      <div class="pp-nav-user">
        <div class="pp-nav-avatar">
          <?php echo strtoupper(substr($_SESSION["username"], 0, 2)); ?>
        </div>
        <?php echo htmlspecialchars($_SESSION["username"]); ?>
      </div>
      <a href="logout.php" class="pp-logout-btn">Log out</a>
    <?php else: ?>
      <a href="login.php" class="pp-nav-link">Login</a>
    <?php endif; ?>
  </div>
</header>
