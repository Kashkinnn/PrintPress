<?php
require_once "connect.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Route each role to their own dashboard
switch ($_SESSION["role"]) {
    case "admin":
        // Admin stays here — full overview
        break;
    case "staff":
        header("Location: staff_dashboard.php");
        exit();
    case "student":
        // Student sees their jobs + create button
        break;
}

$role = $_SESSION["role"];
$username = $_SESSION["username"];

// ── Student view ──────────────────────────────────
if ($role === "student") {

    $stmt = $connection->prepare(
        "SELECT Student_ID, Name FROM tblstudent WHERE account_id = ?",
    );
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $jobs = [];
    if ($student) {
        $sid = $student["Student_ID"];
        $stmt = $connection->prepare(
            "SELECT j.*, st.Location FROM tblprint_job j
             JOIN tblstation st ON j.Station_ID = st.Station_ID
             WHERE j.Student_ID = ? ORDER BY j.created_at DESC LIMIT 5",
        );
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // NEW: Fetch all stations for the inventory view
    $stations = $connection
        ->query("SELECT * FROM tblstation ORDER BY Station_ID")
        ->fetch_all(MYSQLI_ASSOC);

    $pageTitle = "Dashboard";
    require_once "includes/header.php";
    ?>
    <div class="pp-main">
      <div class="pp-action-bar">
        <div>
          <div class="pp-page-title">Welcome, <?php echo htmlspecialchars(
              $student["Name"] ?? $username,
          ); ?></div>
          <div class="pp-page-sub">Student Print Portal</div>
        </div>
        <div class="flex flex-gap items-center">
          <a href="create_job.php" class="pp-btn pp-btn-primary" style="width:auto; padding:0.65rem 1.5rem;">+ Create Print Job</a>
        </div>
      </div>

      <div class="pp-dashboard-grid">

        <div class="pp-card">
          <div class="pp-card-header">
            <span class="pp-section-title" style="margin:0;border:none;padding:0;">Recent Print Jobs</span>
            <a href="my_jobs.php" class="pp-btn pp-btn-secondary pp-btn-sm">View All</a>
          </div>
          <table class="pp-admin-table">
            <thead>
              <tr><th>#</th><th>File</th><th>Station</th><th>Mode</th><th>Copies</th><th>Amount</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php if (empty($jobs)): ?>
                <tr><td colspan="8" class="pp-table-empty">No jobs yet. <a href="create_job.php">Create your first print job</a>.</td></tr>
              <?php else: ?>
                <?php foreach ($jobs as $j): ?>
                <tr>
                  <td class="pp-td-id"><?php echo $j["Job_ID"]; ?></td>
                  <td><?php echo htmlspecialchars($j["File_Name"]); ?></td>
                  <td><?php echo htmlspecialchars($j["Location"]); ?></td>
                  <td><?php echo $j["Color_Mode"] === "colored"
                      ? "Colored"
                      : "B&W"; ?></td>
                  <td style="text-align:center;"><?php echo $j[
                      "Copies"
                  ]; ?></td>
                  <td class="pp-price">₱<?php echo number_format(
                      $j["Amount"],
                      2,
                  ); ?></td>
                  <td>
                    <?php $sc = [
                        "pending" => "pp-status-warn",
                        "printing" => "pp-status-info",
                        "completed" => "pp-status-ok",
                        "cancelled" => "pp-status-err",
                    ]; ?>
                    <span class="pp-status-badge <?php echo $sc[$j["Status"]] ??
                        ""; ?>"><?php echo ucfirst($j["Status"]); ?></span>
                  </td>
                  <td style="font-size:0.78rem;color:var(--gray-400);"><?php echo date(
                      "M j, Y",
                      strtotime($j["created_at"]),
                  ); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="pp-card">
          <div class="pp-card-header">
            <span class="pp-section-title" style="margin:0;border:none;padding:0;">Print Station Status</span>
          </div>

          <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php foreach ($stations as $s): ?>
              <?php
              $hw = $s["Hardware_Status"];
              $statusClass =
                  $hw === "operational"
                      ? "pp-status-ok"
                      : ($hw === "maintenance"
                          ? "pp-status-warn"
                          : "pp-status-err");
              ?>
              <div>
                <div style="font-size: 1rem; color: var(--gray-800); margin-bottom: 0.35rem;">
                  <?php echo htmlspecialchars($s["Location"]); ?>
                </div>

                <div style="margin-bottom: 0.4rem;">
                  <span class="pp-status-badge <?php echo $statusClass; ?>">
                    <?php echo ucfirst($hw); ?>
                  </span>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.72rem; font-weight: 700; color: var(--gray-400); letter-spacing: 0.05em;">
                  ACCEPTS:
                  <?php if (
                      $s["accepts_gcash"]
                  ): ?>   <span class="pp-pay-badge pp-pay-gcash">GCash</span>   <?php endif; ?>
                  <?php if (
                      $s["accepts_paymaya"]
                  ): ?> <span class="pp-pay-badge pp-pay-paymaya">PayMaya</span><?php endif; ?>
                  <?php if (
                      $s["accepts_cash"]
                  ): ?>    <span class="pp-pay-badge pp-pay-cash">Cash</span>    <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>
    <?php
    require_once "includes/footer.php";
    exit();

}

// ── Admin view ────────────────────────────────────
$totalStations = $connection
    ->query("SELECT COUNT(*) AS c FROM tblstation")
    ->fetch_assoc()["c"];
$availStations = $connection
    ->query(
        "SELECT COUNT(*) AS c FROM tblstation WHERE Hardware_Status = 'operational'",
    )
    ->fetch_assoc()["c"];
$totalUsers = $connection
    ->query("SELECT COUNT(*) AS c FROM tbluseraccount")
    ->fetch_assoc()["c"];
$pendingJobs = $connection
    ->query("SELECT COUNT(*) AS c FROM tblprint_job WHERE Status = 'pending'")
    ->fetch_assoc()["c"];
$totalRevenue = $connection
    ->query(
        "SELECT COALESCE(SUM(Amount),0) AS t FROM tblprint_job WHERE Status = 'completed'",
    )
    ->fetch_assoc()["t"];

$stations = $connection
    ->query("SELECT * FROM tblstation ORDER BY Station_ID")
    ->fetch_all(MYSQLI_ASSOC);
$inventory = $connection
    ->query("SELECT * FROM tblinventory ORDER BY Item_ID")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Admin Dashboard";
require_once "includes/header.php";
?>

<div class="pp-main">
  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Admin Dashboard</div>
      <div class="pp-page-sub">Welcome back, <strong><?php echo htmlspecialchars(
          $username,
      ); ?></strong></div>
    </div>
    <div class="flex flex-gap items-center">
      <a href="manage_stations.php"  class="pp-btn pp-btn-secondary pp-btn-sm">Stations</a>
      <a href="manage_inventory.php" class="pp-btn pp-btn-secondary pp-btn-sm">Inventory</a>
      <a href="manage_users.php"     class="pp-btn pp-btn-secondary pp-btn-sm">Users</a>
    </div>
  </div>

  <div class="pp-stats-row" style="grid-template-columns:repeat(5,1fr);">
    <div class="pp-stat-card">
      <span class="pp-stat-label">Stations</span>
      <span class="pp-stat-value"><?php echo $totalStations; ?></span>
    </div>
    <div class="pp-stat-card">
      <span class="pp-stat-label">Operational</span>
      <span class="pp-stat-value"><?php echo $availStations; ?></span>
      <span class="pp-stat-note">&#10003; Online</span>
    </div>
    <div class="pp-stat-card">
      <span class="pp-stat-label">Pending Jobs</span>
      <span class="pp-stat-value"><?php echo $pendingJobs; ?></span>
    </div>
    <div class="pp-stat-card">
      <span class="pp-stat-label">Users</span>
      <span class="pp-stat-value"><?php echo $totalUsers; ?></span>
    </div>
    <div class="pp-stat-card">
      <span class="pp-stat-label">Revenue</span>
      <span class="pp-stat-value" style="font-size:1.4rem;">₱<?php echo number_format(
          $totalRevenue,
          0,
      ); ?></span>
      <span class="pp-stat-note">Completed jobs</span>
    </div>
  </div>

  <div class="pp-dashboard-grid">
    <div class="pp-card">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">Print Stations</span>
        <a href="manage_stations.php" class="pp-btn pp-btn-secondary pp-btn-sm">Manage</a>
      </div>
      <table class="pp-station-table">
        <thead><tr><th>Location</th><th style="text-align:center;">Status</th><th style="text-align:center;">Payment</th></tr></thead>
        <tbody>
          <?php foreach ($stations as $s): ?>
          <tr>
            <td class="pp-station-name"><?php echo htmlspecialchars(
                $s["Location"],
            ); ?></td>
            <td style="text-align:center;">
              <?php
              $hw = $s["Hardware_Status"];
              $color =
                  $hw === "operational"
                      ? "var(--success)"
                      : ($hw === "maintenance"
                          ? "#e67e00"
                          : "var(--danger)");
              ?>
              <span style="color:<?php echo $color; ?>;font-size:0.78rem;font-weight:600;"><?php echo ucfirst(
    $hw,
); ?></span>
            </td>
            <td>
              <div class="pp-avail-cell">
                <?php if (
                    $s["accepts_gcash"]
                ): ?>   <span class="pp-pay-badge pp-pay-gcash">GCash</span>   <?php endif; ?>
                <?php if (
                    $s["accepts_paymaya"]
                ): ?> <span class="pp-pay-badge pp-pay-paymaya">PayMaya</span><?php endif; ?>
                <?php if (
                    $s["accepts_cash"]
                ): ?>    <span class="pp-pay-badge pp-pay-cash">Cash</span>    <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pp-card">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">Inventory</span>
        <a href="manage_inventory.php" class="pp-btn pp-btn-secondary pp-btn-sm">Manage</a>
      </div>
      <table class="pp-resources-table">
        <thead><tr><th>Item</th><th style="text-align:right;">Stock</th><th style="text-align:right;">Price</th></tr></thead>
        <tbody>
          <?php foreach ($inventory as $item): ?>
          <?php $low = $item["Stock_Level"] <= $item["Reorder_Point"]; ?>
          <tr>
            <td><span class="pp-res-dot"></span><?php echo htmlspecialchars(
                $item["Item_Name"],
            ); ?></td>
            <td style="text-align:right;">
              <span style="font-weight:600;color:<?php echo $low
                  ? "var(--danger)"
                  : "var(--success)"; ?>">
                <?php
                echo $item["Stock_Level"];
                if ($low): ?> ⚠<?php endif;
                ?>
              </span>
            </td>
            <td class="pp-price"><?php echo htmlspecialchars(
                $item["price_display"],
            ); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once "includes/footer.php"; ?>
