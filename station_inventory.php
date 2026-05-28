<?php
require_once "connect.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: dashboard.php");
    exit();
}

// Fetch all stations with their status
$stations = $connection
    ->query("SELECT * FROM tblstation ORDER BY Hardware_Status, Location")
    ->fetch_all(MYSQLI_ASSOC);

// Fetch all inventory
$inventory = $connection
    ->query("SELECT * FROM tblinventory ORDER BY Item_Name")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Station Inventory";
require_once "includes/header.php";
?>

<div class="pp-main">
  <div class="pp-breadcrumb">
    <a href="dashboard.php">Dashboard</a><span>›</span><span>Station Inventory</span>
  </div>

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Station Inventory</div>
      <div class="pp-page-sub">Available stocks and supplies at each print station</div>
    </div>
    <a href="dashboard.php" class="pp-btn pp-btn-secondary pp-btn-sm">← Back</a>
  </div>

  <!-- Inventory Table (global) -->
  <div class="pp-card mb-2">
    <div class="pp-card-header">
      <span class="pp-section-title" style="margin:0;border:none;padding:0;">
        Available Supplies &amp; Pricing
      </span>
    </div>
    <table class="pp-admin-table">
      <thead>
        <tr>
          <th>Item</th>
          <th style="text-align:center;">Stock</th>
          <th style="text-align:center;">Status</th>
          <th style="text-align:right;">Price</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($inventory)): ?>
          <tr><td colspan="4" class="pp-table-empty">No inventory data available.</td></tr>
        <?php else: ?>
          <?php foreach ($inventory as $item): ?>
          <?php $low = $item["Stock_Level"] <= $item["Reorder_Point"]; ?>
          <tr>
            <td>
              <div style="display:flex; align-items:center; gap:0.5rem;">
                <span class="pp-res-dot"></span>
                <strong><?php echo htmlspecialchars(
                    $item["Item_Name"],
                ); ?></strong>
              </div>
            </td>
            <td style="text-align:center;">
              <span style="font-weight:700; font-size:1rem; color:<?php echo $low
                  ? "var(--danger)"
                  : "var(--success)"; ?>">
                <?php echo $item["Stock_Level"]; ?>
              </span>
              <span style="font-size:0.72rem; color:var(--gray-400);"> units</span>
            </td>
            <td style="text-align:center;">
              <?php if ($item["Stock_Level"] === 0): ?>
                <span class="pp-status-badge pp-status-err">Out of Stock</span>
              <?php elseif ($low): ?>
                <span class="pp-status-badge pp-status-warn">Low Stock</span>
              <?php else: ?>
                <span class="pp-status-badge pp-status-ok">Available</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;" class="pp-price">
              <?php echo htmlspecialchars($item["price_display"]); ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Stations -->
  <div class="pp-card-header" style="margin-bottom:0.75rem;">
    <span class="pp-section-title" style="margin:0;border:none;padding:0;">
      Print Station Status
    </span>
  </div>

  <div class="pp-station-cards">
    <?php if (empty($stations)): ?>
      <p style="color:var(--gray-400); font-size:0.85rem;">No stations available.</p>
    <?php else: ?>
      <?php foreach ($stations as $s): ?>
      <?php
      $hw = $s["Hardware_Status"];
      $cls =
          $hw === "operational"
              ? "pp-status-ok"
              : ($hw === "maintenance"
                  ? "pp-status-warn"
                  : "pp-status-err");
      ?>
      <div class="pp-station-info-card">
        <div class="pp-station-info-header">
          <div class="pp-station-info-name">
            <?php echo htmlspecialchars($s["Location"]); ?>
          </div>
          <span class="pp-status-badge <?php echo $cls; ?>">
            <?php echo ucfirst($hw); ?>
          </span>
        </div>
        <div class="pp-station-info-payments">
          <span style="font-size:0.72rem; color:var(--gray-400); font-weight:600; text-transform:uppercase; letter-spacing:0.06em;">
            Accepts:
          </span>
          <?php if ($s["accepts_gcash"]): ?>
            <span class="pp-pay-badge pp-pay-gcash">GCash</span>
          <?php endif; ?>
          <?php if ($s["accepts_paymaya"]): ?>
            <span class="pp-pay-badge pp-pay-paymaya">PayMaya</span>
          <?php endif; ?>
          <?php if ($s["accepts_cash"]): ?>
            <span class="pp-pay-badge pp-pay-cash">Cash</span>
          <?php endif; ?>
          <?php if (
              !$s["accepts_gcash"] &&
              !$s["accepts_paymaya"] &&
              !$s["accepts_cash"]
          ): ?>
            <span style="font-size:0.78rem; color:var(--gray-400);">None listed</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php require_once "includes/footer.php"; ?>
