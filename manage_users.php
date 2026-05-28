<?php
require_once 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$error = $success = '';

// ── DELETE ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id === (int) $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $stmt = $connection->prepare("DELETE FROM tbluseraccount WHERE id = ?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $stmt->close();
        $success = 'User deleted.';
    }
}

// ── ADD ───────────────────────────────────────────────────
if (isset($_POST['btnAdd'])) {
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $role      = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check duplicate username
        $chk = $connection->prepare("SELECT id FROM tbluseraccount WHERE username = ?");
        $chk->bind_param('s', $username);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Username \"{$username}\" is already taken.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $station_id_val = intval($_POST['station_id'] ?? 0) ?: null;
            $stmt   = $connection->prepare(
                "INSERT INTO tbluseraccount (username, password, role, full_name, email, station_id) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssssi', $username, $hashed, $role, $full_name, $email, $station_id_val);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();

            // If student role — also create tblstudent row
            if ($role === 'student' && $full_name !== '') {
                $s = $connection->prepare(
                    "INSERT INTO tblstudent (account_id, Name, Email) VALUES (?, ?, ?)"
                );
                $s->bind_param('iss', $new_id, $full_name, $email);
                $s->execute();
                $s->close();
            }

            $success = "User \"{$username}\" created.";
        }
        $chk->close();
    }
}

// ── LOAD FOR EDIT ─────────────────────────────────────────
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $connection->prepare("SELECT * FROM tbluseraccount WHERE id = ?");
    $edit_id = intval($_GET['edit']);
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── UPDATE ────────────────────────────────────────────────
if (isset($_POST['btnUpdate'])) {
    $upd_id    = intval($_POST['user_id']);
    $username  = trim($_POST['username']);
    $role      = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];

    if ($username === '') {
        $error = 'Username is required.';
    } else {
        // Check duplicate (excluding self)
        $chk = $connection->prepare("SELECT id FROM tbluseraccount WHERE username = ? AND id != ?");
        $chk->bind_param('si', $username, $upd_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Username \"{$username}\" is already taken.";
        } else {
            if ($password !== '') {
                // Update with new password
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $station_upd = intval($_POST['station_id'] ?? 0) ?: null;
                $stmt   = $connection->prepare(
                    "UPDATE tbluseraccount SET username=?, password=?, role=?, full_name=?, email=?, station_id=? WHERE id=?"
                );
                $stmt->bind_param('sssssii', $username, $hashed, $role, $full_name, $email, $station_upd, $upd_id);
            } else {
                // Keep old password
                $station_upd = intval($_POST['station_id'] ?? 0) ?: null;
                $stmt = $connection->prepare(
                    "UPDATE tbluseraccount SET username=?, role=?, full_name=?, email=?, station_id=? WHERE id=?"
                );
                $stmt->bind_param('ssssii', $username, $role, $full_name, $email, $station_upd, $upd_id);
            }
            $stmt->execute();
            $stmt->close();
            $success = 'User updated.';
            $editRow  = null;
        }
        $chk->close();
    }
}

// ── FETCH ALL ─────────────────────────────────────────────
$users = $connection->query(
    "SELECT id, username, role, full_name, email, created_at FROM tbluseraccount ORDER BY role, username"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Users';
require_once 'includes/header.php';
?>

<div class="pp-main">
  <div class="pp-breadcrumb">
    <a href="dashboard.php">Dashboard</a><span>›</span><span>Manage Users</span>
  </div>

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Manage Users</div>
      <div class="pp-page-sub"><?php echo count($users); ?> account(s) registered</div>
    </div>
    <a href="dashboard.php" class="pp-btn pp-btn-secondary pp-btn-sm">← Dashboard</a>
  </div>

  <?php if ($error):   ?><div class="pp-alert pp-alert-danger"><?php echo htmlspecialchars($error);   ?></div><?php endif; ?>
  <?php if ($success): ?><div class="pp-alert pp-alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="pp-admin-grid">

    <!-- Form -->
    <div class="pp-card">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">
          <?php echo $editRow ? 'Edit User' : 'Add User'; ?>
        </span>
        <?php if ($editRow): ?>
          <a href="manage_users.php" class="pp-btn pp-btn-secondary pp-btn-sm">Cancel</a>
        <?php endif; ?>
      </div>

      <form method="post">
        <?php if ($editRow): ?>
          <input type="hidden" name="user_id" value="<?php echo $editRow['id']; ?>">
        <?php endif; ?>

        <div class="pp-form-group">
          <label>Full Name</label>
          <input class="pp-input" type="text" name="full_name"
            value="<?php echo htmlspecialchars($editRow['full_name'] ?? ''); ?>"
            placeholder="e.g. Juan Dela Cruz">
        </div>

        <div class="pp-form-group">
          <label>Username</label>
          <input class="pp-input" type="text" name="username"
            value="<?php echo htmlspecialchars($editRow['username'] ?? ''); ?>"
            placeholder="login username">
        </div>

        <div class="pp-form-group">
          <label>Email</label>
          <input class="pp-input" type="email" name="email"
            value="<?php echo htmlspecialchars($editRow['email'] ?? ''); ?>"
            placeholder="user@cit.edu">
        </div>

        <div class="pp-form-group">
          <label>Role</label>
          <select class="pp-input" name="role">
            <?php foreach (['admin','staff','student'] as $r): ?>
              <option value="<?php echo $r; ?>"
                <?php echo (($editRow['role'] ?? 'student') === $r) ? 'selected' : ''; ?>>
                <?php echo ucfirst($r); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="pp-form-group">
          <label>Assigned Station <span style="color:var(--gray-400);font-weight:400;">(staff only)</span></label>
          <select class="pp-input" name="station_id">
            <option value="">-- None --</option>
            <?php
            $stRes = $connection->query("SELECT Station_ID, Location FROM tblstation ORDER BY Location");
            while ($st = $stRes->fetch_assoc()):
            ?>
              <option value="<?php echo $st['Station_ID']; ?>"
                <?php echo (($editRow['station_id'] ?? '') == $st['Station_ID']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($st['Location']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="pp-form-group">
          <label>
            Password
            <?php if ($editRow): ?>
              <span style="font-weight:400; color:var(--gray-400);">(leave blank to keep current)</span>
            <?php endif; ?>
          </label>
          <input class="pp-input" type="password" name="password"
            placeholder="<?php echo $editRow ? 'New password (optional)' : 'Min. 6 characters'; ?>">
        </div>

        <button class="pp-btn pp-btn-primary" type="submit"
          name="<?php echo $editRow ? 'btnUpdate' : 'btnAdd'; ?>"
          style="width:auto; padding:0.6rem 1.5rem;">
          <?php echo $editRow ? 'Save Changes' : 'Create User'; ?>
        </button>
      </form>
    </div>

    <!-- Table -->
    <div class="pp-card" style="overflow-x:auto;">
      <div class="pp-card-header">
        <span class="pp-section-title" style="margin:0;border:none;padding:0;">All Users</span>
      </div>
      <table class="pp-admin-table">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="6" class="pp-table-empty">No users found.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr>
              <td class="pp-td-id"><?php echo $u['id']; ?></td>
              <td><?php echo htmlspecialchars($u['full_name'] ?: '—'); ?></td>
              <td><?php echo htmlspecialchars($u['username']); ?></td>
              <td><span class="pp-role-badge pp-role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
              <td style="font-size:0.78rem; color:var(--gray-400);">
                <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
              </td>
              <td>
                <div class="pp-action-btns">
                  <a href="?edit=<?php echo $u['id']; ?>" class="pp-btn pp-btn-secondary pp-btn-sm">Edit</a>
                  <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                  <a href="?delete=<?php echo $u['id']; ?>" class="pp-btn pp-btn-danger pp-btn-sm"
                     onclick="return confirm('Delete this user?')">Delete</a>
                  <?php endif; ?>
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

<?php require_once 'includes/footer.php'; ?>
