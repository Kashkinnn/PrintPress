<?php
require_once "connect.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit();
}

$error = $success = "";

// ── DELETE ────────────────────────────────────────────────
if (isset($_GET["delete"])) {
    $del_id = intval($_GET["delete"]);
    $check = $connection->prepare(
        "SELECT COUNT(*) AS c FROM tblprint_job WHERE Station_ID = ?",
    );
    $check->bind_param("i", $del_id);
    $check->execute();
    $count = $check->get_result()->fetch_assoc()["c"];
    $check->close();

    if ($count > 0) {
        $error = "Cannot delete — this station has {$count} print job(s) linked to it.";
    } else {
        $stmt = $connection->prepare(
            "DELETE FROM tblstation WHERE Station_ID = ?",
        );
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
        $success = "Station deleted.";
    }
}

// ── ADD ───────────────────────────────────────────────────
if (isset($_POST["btnAdd"])) {
    $loc = trim($_POST["location"]);
    $hw = $_POST["hardware_status"];
    $gcash = isset($_POST["accepts_gcash"]) ? 1 : 0;
    $paymaya = isset($_POST["accepts_paymaya"]) ? 1 : 0;
    $cash = isset($_POST["accepts_cash"]) ? 1 : 0;

    if ($loc === "") {
        $error = "Location is required.";
    } else {
        $stmt = $connection->prepare(
            "INSERT INTO tblstation (Location, Hardware_Status, accepts_gcash, accepts_paymaya, accepts_cash)
             VALUES (?, ?, ?, ?, ?)",
        );
        $stmt->bind_param("ssiii", $loc, $hw, $gcash, $paymaya, $cash);
        $stmt->execute();
        $stmt->close();
        $success = "Station \"{$loc}\" added.";
    }
}

// ── LOAD FOR EDIT ─────────────────────────────────────────
$editRow = null;
if (isset($_GET["edit"])) {
    $stmt = $connection->prepare(
        "SELECT * FROM tblstation WHERE Station_ID = ?",
    );
    $edit_id = intval($_GET["edit"]);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── UPDATE ────────────────────────────────────────────────
if (isset($_POST["btnUpdate"])) {
    $upd_id = intval($_POST["station_id"]);
    $loc = trim($_POST["location"]);
    $hw = $_POST["hardware_status"];
    $gcash = isset($_POST["accepts_gcash"]) ? 1 : 0;
    $paymaya = isset($_POST["accepts_paymaya"]) ? 1 : 0;
    $cash = isset($_POST["accepts_cash"]) ? 1 : 0;

    if ($loc === "") {
        $error = "Location is required.";
    } else {
        $stmt = $connection->prepare(
            "UPDATE tblstation SET Location=?, Hardware_Status=?, accepts_gcash=?, accepts_paymaya=?, accepts_cash=?
             WHERE Station_ID=?",
        );
        $stmt->bind_param(
            "ssiiii",
            $loc,
            $hw,
            $gcash,
            $paymaya,
            $cash,
            $upd_id,
        );
        $stmt->execute();
        $stmt->close();
        $success = "Station updated.";
        $editRow = null;
    }
}

// ── FETCH ALL ─────────────────────────────────────────────
$stations = $connection
    ->query("SELECT * FROM tblstation ORDER BY Station_ID")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Manage Stations";
require_once "includes/header.php";
?>

<div class="pp-main">
  <div class="pp-breadcrumb">
    <a href="dashboard.php">Dashboard</a><span>›</span><span>Manage Stations</span>
  </div>

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Manage Stations</div>
      <div class="pp-page-sub"><?php echo count(
          $stations,
      ); ?> station(s) registered</div>
    </div>
    <a href="dashboard.php" class="pp-btn pp-btn-secondary pp-btn-sm">← Dashboard</a>
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

  <div class="pp-admin-grid">

    <!-- Form -->
    <div class="pp-card">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">
          <?php echo $editRow ? "Edit Station" : "Add Station"; ?>
        </span>
        <?php if ($editRow): ?>
          <a href="manage_stations.php" class="pp-btn pp-btn-secondary pp-btn-sm">Cancel</a>
        <?php endif; ?>
      </div>

      <form method="post">
        <?php if ($editRow): ?>
          <input type="hidden" name="station_id" value="<?php echo $editRow[
              "Station_ID"
          ]; ?>">
        <?php endif; ?>

        <div class="pp-form-group">
          <label>Location / Name</label>
          <input class="pp-input" type="text" name="location"
            value="<?php echo htmlspecialchars($editRow["Location"] ?? ""); ?>"
            placeholder="e.g. Main Canteen">
        </div>

        <div class="pp-form-group">
          <label>Hardware Status</label>
          <select class="pp-input" name="hardware_status">
            <?php foreach (
                ["operational", "maintenance", "offline"]
                as $opt
            ): ?>
              <option value="<?php echo $opt; ?>"
                <?php echo ($editRow["Hardware_Status"] ?? "operational") ===
                $opt
                    ? "selected"
                    : ""; ?>>
                <?php echo ucfirst($opt); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="pp-form-group">
          <label>Accepted Payments</label>
          <div class="pp-checkbox-group">
            <label class="pp-checkbox-label">
              <input type="checkbox" name="accepts_gcash"
                <?php echo $editRow["accepts_gcash"] ?? 0
                    ? "checked"
                    : ""; ?>> GCash
            </label>
            <label class="pp-checkbox-label">
              <input type="checkbox" name="accepts_paymaya"
                <?php echo $editRow["accepts_paymaya"] ?? 0
                    ? "checked"
                    : ""; ?>> PayMaya
            </label>
            <label class="pp-checkbox-label">
              <input type="checkbox" name="accepts_cash"
                <?php echo $editRow["accepts_cash"] ?? 1
                    ? "checked"
                    : ""; ?>> Cash
            </label>
          </div>
        </div>

        <button class="pp-btn pp-btn-primary" type="submit"
          name="<?php echo $editRow ? "btnUpdate" : "btnAdd"; ?>"
          style="width:auto; padding:0.6rem 1.5rem;">
          <?php echo $editRow ? "Save Changes" : "Add Station"; ?>
        </button>
      </form>
    </div>

    <!-- Table -->
    <div class="pp-card" style="overflow-x:auto;">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">All Stations</span>
      </div>
      <table class="pp-admin-table">
        <thead>
          <tr>
            <th>#</th><th>Location</th><th>Status</th><th>Payments</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($stations)): ?>
            <tr><td colspan="5" class="pp-table-empty">No stations yet.</td></tr>
          <?php else: ?>
            <?php foreach ($stations as $s): ?>
            <tr>
              <td class="pp-td-id"><?php echo $s["Station_ID"]; ?></td>
              <td><?php echo htmlspecialchars($s["Location"]); ?></td>
              <td>
                <?php $hw = $s["Hardware_Status"]; ?>
                <span class="pp-status-badge <?php echo $hw === "operational"
                    ? "pp-status-ok"
                    : ($hw === "maintenance"
                        ? "pp-status-warn"
                        : "pp-status-err"); ?>"><?php echo ucfirst(
    $hw,
); ?></span>
              </td>
              <td>
                <?php if (
                    $s["accepts_gcash"]
                ): ?>   <span class="pp-pay-badge pp-pay-gcash">GCash</span>   <?php endif; ?>
                <?php if (
                    $s["accepts_paymaya"]
                ): ?> <span class="pp-pay-badge pp-pay-paymaya">PayMaya</span><?php endif; ?>
                <?php if (
                    $s["accepts_cash"]
                ): ?>    <span class="pp-pay-badge pp-pay-cash">Cash</span>    <?php endif; ?>
              </td>
              <td>
                <div class="pp-action-btns">
                  <a href="?edit=<?php echo $s[
                      "Station_ID"
                  ]; ?>" class="pp-btn pp-btn-secondary pp-btn-sm">Edit</a>
                  <a href="?delete=<?php echo $s[
                      "Station_ID"
                  ]; ?>" class="pp-btn pp-btn-danger pp-btn-sm"
                     onclick="return confirm('Delete this station?')">Delete</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<?php require_once "includes/footer.php"; ?>
