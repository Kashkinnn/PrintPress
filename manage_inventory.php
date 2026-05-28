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
        "SELECT COUNT(*) AS c FROM tblprint_job WHERE Item_ID = ?",
    );
    $check->bind_param("i", $del_id);
    $check->execute();
    $count = $check->get_result()->fetch_assoc()["c"];
    $check->close();

    if ($count > 0) {
        $error = "Cannot delete — this item is used in {$count} print job(s).";
    } else {
        $stmt = $connection->prepare(
            "DELETE FROM tblinventory WHERE Item_ID = ?",
        );
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
        $success = "Item deleted.";
    }
}

// ── ADD ───────────────────────────────────────────────────
if (isset($_POST["btnAdd"])) {
    $name = trim($_POST["item_name"]);
    $stock = intval($_POST["stock_level"]);
    $reorder = intval($_POST["reorder_point"]);
    $price = trim($_POST["price_display"]);

    if ($name === "") {
        $error = "Item name is required.";
    } else {
        $stmt = $connection->prepare(
            "INSERT INTO tblinventory (Item_Name, Stock_Level, Reorder_Point, price_display) VALUES (?, ?, ?, ?)",
        );
        $stmt->bind_param("siis", $name, $stock, $reorder, $price);
        $stmt->execute();
        $stmt->close();
        $success = "Item \"{$name}\" added.";
    }
}

// ── LOAD FOR EDIT ─────────────────────────────────────────
$editRow = null;
if (isset($_GET["edit"])) {
    $stmt = $connection->prepare(
        "SELECT * FROM tblinventory WHERE Item_ID = ?",
    );
    $edit_id = intval($_GET["edit"]);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── UPDATE ────────────────────────────────────────────────
if (isset($_POST["btnUpdate"])) {
    $upd_id = intval($_POST["item_id"]);
    $name = trim($_POST["item_name"]);
    $stock = intval($_POST["stock_level"]);
    $reorder = intval($_POST["reorder_point"]);
    $price = trim($_POST["price_display"]);

    if ($name === "") {
        $error = "Item name is required.";
    } else {
        $stmt = $connection->prepare(
            "UPDATE tblinventory SET Item_Name=?, Stock_Level=?, Reorder_Point=?, price_display=? WHERE Item_ID=?",
        );
        $stmt->bind_param("siisi", $name, $stock, $reorder, $price, $upd_id);
        $stmt->execute();
        $stmt->close();
        $success = "Item updated.";
        $editRow = null;
    }
}

// ── FETCH ALL ─────────────────────────────────────────────
$items = $connection
    ->query("SELECT * FROM tblinventory ORDER BY Item_ID")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Manage Inventory";
require_once "includes/header.php";
?>

<div class="pp-main">
  <div class="pp-breadcrumb">
    <a href="dashboard.php">Dashboard</a><span>›</span><span>Manage Inventory</span>
  </div>

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Manage Inventory</div>
      <div class="pp-page-sub"><?php echo count(
          $items,
      ); ?> item(s) in stock</div>
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
          <?php echo $editRow ? "Edit Item" : "Add Item"; ?>
        </span>
        <?php if ($editRow): ?>
          <a href="manage_inventory.php" class="pp-btn pp-btn-secondary pp-btn-sm">Cancel</a>
        <?php endif; ?>
      </div>

      <form method="post">
        <?php if ($editRow): ?>
          <input type="hidden" name="item_id" value="<?php echo $editRow[
              "Item_ID"
          ]; ?>">
        <?php endif; ?>

        <div class="pp-form-group">
          <label>Item Name</label>
          <input class="pp-input" type="text" name="item_name"
            value="<?php echo htmlspecialchars($editRow["Item_Name"] ?? ""); ?>"
            placeholder="e.g. A4 Bondpaper">
        </div>

        <div class="pp-form-group">
          <label>Stock Level</label>
          <input class="pp-input" type="number" name="stock_level" min="0"
            value="<?php echo $editRow["Stock_Level"] ?? 0; ?>"
            placeholder="0">
        </div>

        <div class="pp-form-group">
          <label>Reorder Point</label>
          <input class="pp-input" type="number" name="reorder_point" min="0"
            value="<?php echo $editRow["Reorder_Point"] ?? 10; ?>"
            placeholder="10">
          <span style="font-size:0.75rem; color:var(--gray-400); margin-top:0.25rem; display:block;">
            Alert shows when stock falls at or below this number.
          </span>
        </div>

        <div class="pp-form-group">
          <label>Price Display</label>
          <input class="pp-input" type="text" name="price_display"
            value="<?php echo htmlspecialchars(
                $editRow["price_display"] ?? "",
            ); ?>"
            placeholder="e.g. 2php/pc or 10php">
        </div>

        <button class="pp-btn pp-btn-primary" type="submit"
          name="<?php echo $editRow ? "btnUpdate" : "btnAdd"; ?>"
          style="width:auto; padding:0.6rem 1.5rem;">
          <?php echo $editRow ? "Save Changes" : "Add Item"; ?>
        </button>
      </form>
    </div>

    <!-- Table -->
    <div class="pp-card" style="overflow-x:auto;">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">All Inventory</span>
      </div>
      <table class="pp-admin-table">
        <thead>
          <tr>
            <th>#</th><th>Item</th><th>Stock</th><th>Reorder At</th><th>Price</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="6" class="pp-table-empty">No items yet.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
            <?php $low = $item["Stock_Level"] <= $item["Reorder_Point"]; ?>
            <tr <?php echo $low ? 'class="pp-row-warn"' : ""; ?>>
              <td class="pp-td-id"><?php echo $item["Item_ID"]; ?></td>
              <td><?php echo htmlspecialchars($item["Item_Name"]); ?></td>
              <td>
                <span style="font-weight:600; color:<?php echo $low
                    ? "var(--danger)"
                    : "var(--success)"; ?>">
                  <?php echo $item["Stock_Level"]; ?>
                  <?php if (
                      $low
                  ): ?><span style="font-size:0.7rem;"> ⚠</span><?php endif; ?>
                </span>
              </td>
              <td><?php echo $item["Reorder_Point"]; ?></td>
              <td class="pp-price"><?php echo htmlspecialchars(
                  $item["price_display"],
              ); ?></td>
              <td>
                <div class="pp-action-btns">
                  <a href="?edit=<?php echo $item[
                      "Item_ID"
                  ]; ?>" class="pp-btn pp-btn-secondary pp-btn-sm">Edit</a>
                  <a href="?delete=<?php echo $item[
                      "Item_ID"
                  ]; ?>" class="pp-btn pp-btn-danger pp-btn-sm"
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
