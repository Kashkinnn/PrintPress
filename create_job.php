<?php
require_once "connect.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: dashboard.php");
    exit();
}

$error = $success = "";

// Get student record
$stmt = $connection->prepare(
    "SELECT s.Student_ID, s.Name FROM tblstudent s WHERE s.account_id = ?",
);
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found. Please contact admin.");
}

// Get operational stations
$stations = $connection
    ->query(
        "SELECT Station_ID, Location FROM tblstation WHERE Hardware_Status = 'operational' ORDER BY Location",
    )
    ->fetch_all(MYSQLI_ASSOC);

// Handle submission
if (isset($_POST["btnSubmit"])) {
    $station_id = intval($_POST["station_id"]);
    $color_mode = $_POST["color_mode"];
    $paper_size = $_POST["paper_size"];
    $copies = max(1, intval($_POST["copies"]));
    $amount = $color_mode === "colored" ? 10.0 * $copies : 5.0 * $copies;

    // File upload
    $file_name = "";
    $file_path = "";

    if (
        isset($_FILES["print_file"]) &&
        $_FILES["print_file"]["error"] === UPLOAD_ERR_OK
    ) {
        $upload_dir = "uploads/print_jobs/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $orig_name = basename($_FILES["print_file"]["name"]);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

        if (!in_array($ext, $allowed)) {
            $error = "Only PDF, JPG, PNG, DOC, DOCX files are allowed.";
        } else {
            $file_name =
                time() .
                "_" .
                preg_replace("/[^a-zA-Z0-9._-]/", "_", $orig_name);
            $file_path = $upload_dir . $file_name;
            if (
                !move_uploaded_file(
                    $_FILES["print_file"]["tmp_name"],
                    $file_path,
                )
            ) {
                $error = "File upload failed. Please try again.";
                $file_name = $file_path = "";
            }
        }
    } else {
        $error = "Please upload a file.";
    }

    if (!$error && $station_id && $file_name) {
        $stmt = $connection->prepare(
            "INSERT INTO tblprint_job
             (Student_ID, Station_ID, File_Name, File_Path, Copies, Color_Mode, Paper_Size, Amount, Status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
        );
        $student_id = $student["Student_ID"];
        $stmt->bind_param(
            "iississd",
            $student_id,
            $station_id,
            $file_name,
            $file_path,
            $copies,
            $color_mode,
            $paper_size,
            $amount,
        );
        if ($stmt->execute()) {
            $success =
                "Print job submitted! Total: ₱" . number_format($amount, 2);
        } else {
            $error = "Failed to submit job. Please try again.";
        }
        $stmt->close();
    }
}

$pageTitle = "Create Print Job";
require_once "includes/header.php";
?>

<div class="pp-main" style="max-width:620px;">
  <div class="pp-breadcrumb">
    <a href="dashboard.php">Dashboard</a><span>›</span><span>Create Print Job</span>
  </div>

  <div class="pp-action-bar">
    <div>
      <div class="pp-page-title">Create Print Job</div>
      <div class="pp-page-sub">Submitting as <strong><?php echo htmlspecialchars(
          $student["Name"],
      ); ?></strong></div>
    </div>
    <a href="dashboard.php" class="pp-btn pp-btn-secondary pp-btn-sm">← Back</a>
  </div>

  <?php if (
      $error
  ): ?><div class="pp-alert pp-alert-danger"><?php echo htmlspecialchars(
    $error,
); ?></div><?php endif; ?>
  <?php if ($success): ?>
    <div class="pp-alert pp-alert-success"><?php echo htmlspecialchars(
        $success,
    ); ?></div>
    <div style="text-align:center; margin-top:1rem;">
      <a href="my_jobs.php" class="pp-btn pp-btn-primary" style="width:auto; padding:0.7rem 2rem;">View My Jobs</a>
      <a href="create_job.php" class="pp-btn pp-btn-secondary" style="padding:0.7rem 2rem;">Submit Another</a>
    </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <div class="pp-card">
    <form method="post" enctype="multipart/form-data" novalidate>

      <!-- Student name (read-only) -->
      <div class="pp-form-group">
        <label>Your Name</label>
        <input class="pp-input" type="text"
               value="<?php echo htmlspecialchars(
                   $student["Name"],
               ); ?>" disabled>
      </div>

      <!-- Station -->
      <div class="pp-form-group">
        <label>Print Station</label>
        <select class="pp-input" name="station_id" required>
          <option value="">-- Select a station --</option>
          <?php foreach ($stations as $st): ?>
            <option value="<?php echo $st["Station_ID"]; ?>"
              <?php echo isset($_POST["station_id"]) &&
              $_POST["station_id"] == $st["Station_ID"]
                  ? "selected"
                  : ""; ?>>
              <?php echo htmlspecialchars($st["Location"]); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- File upload -->
      <div class="pp-form-group">
        <label>Upload File</label>
        <div class="pp-file-drop" id="fileDrop">
          <input type="file" name="print_file" id="print_file"
                 accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required
                 style="display:none;">
          <div class="pp-file-drop-inner" onclick="document.getElementById('print_file').click()">
            <div class="pp-file-icon">&#128196;</div>
            <div class="pp-file-label" id="fileLabel">Click to upload or drag &amp; drop</div>
            <div style="font-size:0.75rem; color:var(--gray-400); margin-top:0.25rem;">
              PDF, JPG, PNG, DOC, DOCX
            </div>
          </div>
        </div>
      </div>

      <!-- Color mode -->
      <div class="pp-form-group">
        <label>Print Mode</label>
        <div class="pp-radio-group">
          <label class="pp-radio-label">
            <input type="radio" name="color_mode" value="black_and_white"
              <?php echo !isset($_POST["color_mode"]) ||
              $_POST["color_mode"] === "black_and_white"
                  ? "checked"
                  : ""; ?>
              onchange="updatePrice()">
            <span>Black &amp; White</span>
            <span class="pp-price-tag">₱5 / copy</span>
          </label>
          <label class="pp-radio-label">
            <input type="radio" name="color_mode" value="colored"
              <?php echo isset($_POST["color_mode"]) &&
              $_POST["color_mode"] === "colored"
                  ? "checked"
                  : ""; ?>
              onchange="updatePrice()">
            <span>Colored</span>
            <span class="pp-price-tag">₱10 / copy</span>
          </label>
        </div>
      </div>

      <!-- Paper size -->
      <div class="pp-form-group">
        <label>Paper Size</label>
        <select class="pp-input" name="paper_size">
          <?php foreach (
              [
                  "short" => 'Short (8.5x11")',
                  "a4" => 'A4 (8.27x11.7")',
                  "long" => 'Long (8.5x14")',
              ]
              as $val => $label
          ): ?>
            <option value="<?php echo $val; ?>"
              <?php echo isset($_POST["paper_size"]) &&
              $_POST["paper_size"] === $val
                  ? "selected"
                  : ""; ?>>
              <?php echo $label; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Copies -->
      <div class="pp-form-group">
        <label>Number of Copies</label>
        <input class="pp-input" type="number" name="copies" id="copies"
               min="1" max="100" value="<?php echo $_POST["copies"] ?? 1; ?>"
               oninput="updatePrice()" style="max-width:120px;">
      </div>

      <!-- Price display -->
      <div class="pp-price-summary">
        <span>Estimated Total</span>
        <span class="pp-price-total" id="priceTotal">₱5.00</span>
      </div>

      <div class="mt-2">
        <button class="pp-btn pp-btn-primary" type="submit" name="btnSubmit"
                style="width:auto; padding:0.7rem 2rem;">
          Submit Print Job
        </button>
      </div>

    </form>
  </div>
  <?php endif; ?>
</div>

<script>
function updatePrice() {
  const colored = document.querySelector('input[name="color_mode"]:checked')?.value === 'colored';
  const copies  = Math.max(1, parseInt(document.getElementById('copies')?.value) || 1);
  const rate    = colored ? 10 : 5;
  const total   = rate * copies;
  document.getElementById('priceTotal').textContent = '₱' + total.toFixed(2);
}

// File drop label update
document.getElementById('print_file').addEventListener('change', function() {
  const label = document.getElementById('fileLabel');
  label.textContent = this.files[0] ? this.files[0].name : 'Click to upload or drag & drop';
});

// Drag and drop
const dropZone = document.getElementById('fileDrop');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('pp-file-drag'); });
dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('pp-file-drag'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('pp-file-drag');
  const input = document.getElementById('print_file');
  input.files = e.dataTransfer.files;
  document.getElementById('fileLabel').textContent = input.files[0]?.name || 'File selected';
});

updatePrice();
</script>

<?php require_once "includes/footer.php"; ?>
