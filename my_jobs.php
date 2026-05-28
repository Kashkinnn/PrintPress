<?php
require_once 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: dashboard.php');
    exit;
}

// Get student record
$stmt = $connection->prepare("SELECT Student_ID, Name FROM tblstudent WHERE account_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch this student's jobs
$student_id = $student['Student_ID'];
$stmt = $connection->prepare(
    "SELECT j.*, st.Location
     FROM tblprint_job j
     JOIN tblstation st ON j.Station_ID = st.Station_ID
     WHERE j.Student_ID = ?
     ORDER BY j.created_at DESC"
);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'My Print Jobs';
require_once 'includes/header.php';
?>

<div class="pp-main">
  <div class="pp-breadcrumb">
    <a href="dashboard.php">Dashboard</a><span>›</span><span>My Print Jobs</span>
  </div>

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">My Print Jobs</div>
      <div class="pp-page-sub"><?php echo count($jobs); ?> job(s) submitted</div>
    </div>
    <a href="create_job.php" class="pp-btn pp-btn-primary pp-btn-sm">+ New Print Job</a>
  </div>

  <div class="pp-card" style="overflow-x:auto;">
    <table class="pp-admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>File</th>
          <th>Station</th>
          <th>Mode</th>
          <th>Size</th>
          <th>Copies</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Submitted</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
          <tr><td colspan="9" class="pp-table-empty">No print jobs yet. <a href="create_job.php">Create one now</a>.</td></tr>
        <?php else: ?>
          <?php foreach ($jobs as $j): ?>
          <tr>
            <td class="pp-td-id"><?php echo $j['Job_ID']; ?></td>
            <td style="max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <?php echo htmlspecialchars($j['File_Name']); ?>
            </td>
            <td><?php echo htmlspecialchars($j['Location']); ?></td>
            <td><?php echo $j['Color_Mode'] === 'colored' ? 'Colored' : 'B&W'; ?></td>
            <td><?php echo strtoupper($j['Paper_Size']); ?></td>
            <td style="text-align:center;"><?php echo $j['Copies']; ?></td>
            <td class="pp-price">₱<?php echo number_format($j['Amount'], 2); ?></td>
            <td>
              <?php
                $sc = [
                  'pending'   => 'pp-status-warn',
                  'printing'  => 'pp-status-info',
                  'completed' => 'pp-status-ok',
                  'cancelled' => 'pp-status-err',
                ];
              ?>
              <span class="pp-status-badge <?php echo $sc[$j['Status']] ?? ''; ?>">
                <?php echo ucfirst($j['Status']); ?>
              </span>
            </td>
            <td style="font-size:0.78rem; color:var(--gray-400); white-space:nowrap;">
              <?php echo date('M j, Y g:i A', strtotime($j['created_at'])); ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
