<?php
require_once "connect.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

// ── DELETE ────────────────────────────────────────────────
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];
    $stmt = $connection->prepare("DELETE FROM tblresource WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $success = "Item deleted.";
}

// ── ADD ───────────────────────────────────────────────────
if (isset($_POST["btnAdd"])) {
    $name = trim($_POST["item_name"]);
    $price = trim($_POST["price_display"]);

    if ($name === "") {
        $error = "Item name is required.";
    } else {
        $stmt = $connection->prepare(
            "INSERT INTO tblresource (name, price_display)
             VALUES (?, ?)",
        );
        // "ss" means two Strings ($name, $price)
        $stmt->bind_param("ss", $name, $price);
        $stmt->execute();
        $stmt->close();
        $success = "Item added successfully.";
    }
}

// ── EDIT FETCH ────────────────────────────────────────────
$editRow = null;
if (isset($_GET["edit"])) {
    $id = (int) $_GET["edit"];
    $stmt = $connection->prepare("SELECT * FROM tblresource WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── UPDATE ────────────────────────────────────────────────
if (isset($_POST["btnUpdate"])) {
    $id = (int) $_POST["id"];
    $name = trim($_POST["item_name"]);
    $price = trim($_POST["price_display"]);

    if ($name === "") {
        $error = "Item name is required.";
    } else {
        $stmt = $connection->prepare(
            "UPDATE tblresource
             SET name=?, price_display=?
             WHERE id=?",
        );
        // "ssi" means String, String, Integer ($name, $price, $id)
        $stmt->bind_param("ssi", $name, $price, $id);
        $stmt->execute();
        $stmt->close();
        $success = "Item updated.";
        $editRow = null;
    }
}

// ── READ ──────────────────────────────────────────────────
$items = $connection
    ->query("SELECT * FROM tblresource ORDER BY id")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Manage Inventory";
require_once "includes/header.php";
?>

<div class="pp-main">

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Manage Inventory</div>
      <div class="pp-page-sub">Admin &rsaquo; Inventory</div>
    </div>
    <a href="dashboard.php" class="pp-btn pp-btn-secondary pp-btn-sm">← Back to Dashboard</a>
  </div>

  <?php if ($error): ?>
    <div class="pp-alert pp-alert-danger"><?php echo htmlspecialchars(
        $error,
    ); ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="pp-alert pp-alert-success"><?php echo htmlspecialchars(
        $success,
    ); ?></div>
  <?php endif; ?>

  <div class="pp-dashboard-grid" style="grid-template-columns: 1fr 1.6fr;">

    <div class="pp-card">
      <div class="pp-section-title"><?php echo $editRow
          ? "Edit Item"
          : "Add New Item"; ?></div>

      <form method="post">
        <?php if ($editRow): ?>
          <input type="hidden" name="id" value="<?php echo $editRow["id"]; ?>">
        <?php endif; ?>

        <div class="pp-form-group">
          <label>Item Name</label>
          <input class="pp-input" type="text" name="item_name"
                 value="<?php echo htmlspecialchars($editRow["name"] ?? ""); ?>"
                 placeholder="e.g. A4 Bondpaper" required>
        </div>

        <div class="pp-form-group">
          <label>Price Display</label>
          <input class="pp-input" type="text" name="price_display"
                 value="<?php echo htmlspecialchars(
                     $editRow["price_display"] ?? "",
                 ); ?>"
                 placeholder="e.g. 2php/pc or 10php">
        </div>

        <div class="flex flex-gap mt-2">
          <?php if ($editRow): ?>
            <button class="pp-btn pp-btn-primary" type="submit" name="btnUpdate">Update Item</button>
            <a href="manage_resources.php" class="pp-btn pp-btn-secondary">Cancel</a>
          <?php else: ?>
            <button class="pp-btn pp-btn-primary" type="submit" name="btnAdd">Add Item</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="pp-card">
      <div class="pp-section-title">All Items (<?php echo count(
          $items,
      ); ?>)</div>

      <table class="pp-station-table" style="font-size:0.83rem;">
        <thead>
          <tr>
            <th>ID</th>
            <th>Item Name</th>
            <th style="text-align:right;">Price</th>
            <th style="text-align:center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="4" style="color:var(--gray-400); padding:1rem 0;">No items yet.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
            <tr>
              <td style="color:var(--gray-400);">#<?php echo $item[
                  "id"
              ]; ?></td>

              <td class="pp-station-name">
                <?php echo htmlspecialchars($item["name"]); ?>
              </td>

              <td style="text-align:right;" class="pp-price">
                <?php echo htmlspecialchars($item["price_display"] ?? ""); ?>
              </td>

              <td style="text-align:center;">
                <div class="flex flex-gap" style="justify-content:center;">
                  <a href="?edit=<?php echo $item[
                      "id"
                  ]; ?>" class="pp-btn pp-btn-secondary pp-btn-sm">Edit</a>
                  <a href="?delete=<?php echo $item["id"]; ?>"
                     class="pp-btn pp-btn-danger pp-btn-sm"
                     onclick="return confirm('Delete this item?')">Delete</a>
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
