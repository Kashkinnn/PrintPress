<?php
require_once "connect.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "staff") {
    header("Location: dashboard.php");
    exit();
}

$staff_id = $_SESSION["user_id"];
$error = "";
$success = isset($_GET["switched"]) ? "Station updated successfully." : "";

// ── Switch station ────────────────────────────────
if (isset($_POST["btnSwitchStation"])) {
    $new_station = intval($_POST["new_station_id"]);
    if ($new_station > 0) {
        $stmt = $connection->prepare(
            "UPDATE tbluseraccount SET station_id = ? WHERE id = ?",
        );
        $stmt->bind_param("ii", $new_station, $staff_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION["station_id"] = $new_station;
    }
    header("Location: staff_dashboard.php?switched=1");
    exit();
}

// ── Fulfill job ───────────────────────────────────
if (isset($_POST["btnFulfill"])) {
    $job_id = intval($_POST["job_id"]);
    $stmt = $connection->prepare(
        "UPDATE tblprint_job SET Status = 'completed' WHERE Job_ID = ? AND Station_ID = ?",
    );
    $station_id = intval($_SESSION["station_id"] ?? 0);
    $stmt->bind_param("ii", $job_id, $station_id);
    $stmt->execute();
    $stmt->close();
    $success = "Job marked as completed.";
}

// ── Mark printing ─────────────────────────────────
if (isset($_POST["btnPrinting"])) {
    $job_id = intval($_POST["job_id"]);
    $stmt = $connection->prepare(
        "UPDATE tblprint_job SET Status = 'printing' WHERE Job_ID = ? AND Station_ID = ?",
    );
    $station_id = intval($_SESSION["station_id"] ?? 0);
    $stmt->bind_param("ii", $job_id, $station_id);
    $stmt->execute();
    $stmt->close();
    $success = "Job marked as printing.";
}

// ── Cancel job ────────────────────────────────────
if (isset($_POST["btnCancel"])) {
    $job_id = intval($_POST["job_id"]);
    $stmt = $connection->prepare(
        "UPDATE tblprint_job SET Status = 'cancelled' WHERE Job_ID = ? AND Station_ID = ?",
    );
    $station_id = intval($_SESSION["station_id"] ?? 0);
    $stmt->bind_param("ii", $job_id, $station_id);
    $stmt->execute();
    $stmt->close();
    $success = "Job cancelled.";
}

// ── Get staff's current station ───────────────────
$stmt = $connection->prepare(
    "SELECT u.station_id, s.Location, s.Hardware_Status
     FROM tbluseraccount u
     LEFT JOIN tblstation s ON u.station_id = s.Station_ID
     WHERE u.id = ?",
);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staffInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Sync session station_id
if (!$staffInfo) {
    $staffInfo = [
        "station_id" => null,
        "Location" => null,
        "Hardware_Status" => null,
    ];
}
$_SESSION["station_id"] = $staffInfo["station_id"];
$current_station_id = intval($staffInfo["station_id"] ?? 0);

// ── All stations (for switcher) ───────────────────
$allStations = $connection
    ->query(
        "SELECT Station_ID, Location, Hardware_Status FROM tblstation ORDER BY Location",
    )
    ->fetch_all(MYSQLI_ASSOC);

// ── Jobs at this station ──────────────────────────
$jobs = [];
if ($current_station_id) {
    $stmt = $connection->prepare(
        "SELECT j.*, st.Name as StudentName
         FROM tblprint_job j
         JOIN tblstudent st ON j.Student_ID = st.Student_ID
         WHERE j.Station_ID = ? AND j.Status IN ('pending','printing')
         ORDER BY j.created_at ASC",
    );
    $stmt->bind_param("i", $current_station_id);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ── Inventory at this station (global for now) ────
$inventory = $connection
    ->query("SELECT * FROM tblinventory ORDER BY Item_ID")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Staff Dashboard";
require_once "includes/header.php";
?>

<div class="pp-main">

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Staff Dashboard</div>
      <div class="pp-page-sub">
        Logged in as <strong><?php echo htmlspecialchars(
            $_SESSION["username"],
        ); ?></strong>
        &nbsp;·&nbsp;
        <?php if ($staffInfo["Location"]): ?>
          Station: <strong><?php echo htmlspecialchars(
              $staffInfo["Location"],
          ); ?></strong>
        <?php else: ?>
          <span style="color:var(--danger);">No station assigned</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (
      $error
  ): ?><div class="pp-alert pp-alert-danger"><?php echo htmlspecialchars(
    $error,
); ?></div><?php endif; ?>
  <?php if (
      $success
  ): ?><div class="pp-alert pp-alert-success"><?php echo htmlspecialchars(
    $success,
); ?></div><?php endif; ?>

  <!-- ── Station Switcher ── -->
  <div class="pp-card mb-2">
    <div class="pp-card-header">
      <span class="pp-section-title" style="margin:0;border:none;padding:0;">My Station</span>
    </div>
    <form method="post" style="display:flex; gap:0.75rem; align-items:flex-end; flex-wrap:wrap;">
      <div class="pp-form-group" style="margin:0; flex:1; min-width:200px;">
        <label>Switch to a different station</label>
        <select class="pp-input" name="new_station_id">
          <option value="">-- Select station --</option>
          <?php foreach ($allStations as $st): ?>
            <option value="<?php echo $st["Station_ID"]; ?>"
              <?php echo $st["Station_ID"] == $current_station_id
                  ? "selected"
                  : ""; ?>>
              <?php echo htmlspecialchars($st["Location"]); ?>
              (<?php echo ucfirst($st["Hardware_Status"]); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="pp-btn pp-btn-secondary" type="submit" name="btnSwitchStation"
              style="width:auto; padding:0.6rem 1.25rem;">
        Switch Station
      </button>
    </form>
  </div>

  <?php if (!$current_station_id): ?>
    <div class="pp-alert pp-alert-info">
      You are not assigned to a station yet. Select one above to view print jobs.
    </div>
  <?php else: ?>

  <div class="pp-dashboard-grid">

    <!-- LEFT — Print Job Queue -->
    <div class="pp-card" style="overflow-x:auto;">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">
          Print Queue &mdash; <?php echo htmlspecialchars(
              $staffInfo["Location"] ?? "",
          ); ?>
        </span>
        <span style="font-size:0.78rem; color:var(--gray-400);">
          <?php echo count($jobs); ?> active
        </span>
      </div>

      <?php if (empty($jobs)): ?>
        <p style="color:var(--gray-400); font-size:0.85rem; padding:1rem 0;">No pending jobs at this station.</p>
      <?php else: ?>
        <?php foreach ($jobs as $j): ?>
        <div class="pp-job-card">
          <div class="pp-job-header">
            <div>
              <span class="pp-job-id">#<?php echo $j["Job_ID"]; ?></span>
              <span class="pp-status-badge <?php echo $j["Status"] ===
              "printing"
                  ? "pp-status-info"
                  : "pp-status-warn"; ?>">
                <?php echo ucfirst($j["Status"]); ?>
              </span>
            </div>
            <span style="font-size:0.75rem; color:var(--gray-400);">
              <?php echo date("M j, g:i A", strtotime($j["created_at"])); ?>
            </span>
          </div>
          <div class="pp-job-details">
            <div><span>Student</span><strong><?php echo htmlspecialchars(
                $j["StudentName"],
            ); ?></strong></div>
            <div><span>File</span><strong><?php echo htmlspecialchars(
                $j["File_Name"],
            ); ?></strong></div>
            <div><span>Mode</span><strong><?php echo $j["Color_Mode"] ===
            "colored"
                ? "Colored"
                : "B&W"; ?></strong></div>
            <div><span>Size</span><strong><?php echo strtoupper(
                $j["Paper_Size"],
            ); ?></strong></div>
            <div><span>Copies</span><strong><?php echo $j[
                "Copies"
            ]; ?></strong></div>
            <div><span>Amount</span><strong style="color:var(--maroon);">₱<?php echo number_format(
                $j["Amount"],
                2,
            ); ?></strong></div>
          </div>
          <div class="pp-job-actions">
            <?php $job_id = $j["Job_ID"]; ?>
            <?php if ($j["Status"] === "pending"): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
              <button class="pp-btn pp-btn-secondary pp-btn-sm" type="submit" name="btnPrinting">
                Mark Printing
              </button>
            </form>
            <?php endif; ?>
            <?php if (in_array($j["Status"], ["pending", "printing"])): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
              <button class="pp-btn pp-btn-success pp-btn-sm" type="submit" name="btnFulfill">
                Complete
              </button>
            </form>
            <form method="post" style="display:inline;"
                  onsubmit="return confirm('Cancel this print job?')">
              <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
              <button class="pp-btn pp-btn-danger pp-btn-sm" type="submit" name="btnCancel">
                Cancel
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- RIGHT — Inventory -->
    <div class="pp-card">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">Inventory</span>
      </div>
      <table class="pp-resources-table">
        <thead>
          <tr>
            <th>Item</th>
            <th style="text-align:right;">Stock</th>
            <th style="text-align:right;">Price</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inventory as $item): ?>
          <?php $low = $item["Stock_Level"] <= $item["Reorder_Point"]; ?>
          <tr>
            <td>
              <span class="pp-res-dot"></span>
              <?php echo htmlspecialchars($item["Item_Name"]); ?>
            </td>
            <td style="text-align:right;">
              <span style="font-weight:600; color:<?php echo $low
                  ? "var(--danger)"
                  : "var(--success)"; ?>">
                <?php echo $item["Stock_Level"]; ?>
                <?php if (
                    $low
                ): ?><span style="font-size:0.7rem;"> ⚠</span><?php endif; ?>
              </span>
            </td>
            <td class="pp-price"><?php echo htmlspecialchars(
                $item["price_display"],
            ); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="mt-2">
        <a href="staff_inventory.php" class="pp-btn pp-btn-secondary pp-btn-sm">
          Update Stock Levels
        </a>
      </div>
    </div>

  </div>
  <?php endif; ?>

</div>

<?php require_once "includes/footer.php"; ?>
