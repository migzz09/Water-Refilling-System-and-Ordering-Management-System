<?php
// status.php
require_once 'connect.php';
session_start();

// Helpers
function redirect_tab($tab, $msg = null, $err = null) {
    $url = "status.php?tab={$tab}";
    if ($msg) $url .= "&msg=" . urlencode($msg);
    if ($err) $url .= "&err=" . urlencode($err);
    header("Location: $url");
    exit;
}
function unique_check($pdo, $table, $name, $exclude_id = null) {
    $primary = "{$table}_id";
    if ($exclude_id) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE status_name = :name AND {$primary} != :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['name' => $name, 'id' => $exclude_id]);
    } else {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE status_name = :name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['name' => $name]);
    }
    return $stmt->fetchColumn() == 0;
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_status'])) {
    $table = $_POST['table'] ?? '';
    $status_name = trim($_POST['status_name'] ?? '');
    if ($status_name === '') {
        redirect_tab($table, null, 'Status name cannot be empty.');
    }
    if (!unique_check($pdo, $table, $status_name)) {
        redirect_tab($table, null, 'This status already exists.');
    }
    $sql = "INSERT INTO {$table} (status_name) VALUES (:name)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute(['name' => $status_name]);
        redirect_tab($table, 'Added successfully.');
    } catch (PDOException $e) {
        redirect_tab($table, null, 'Database error while adding.');
    }
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_status'])) {
    $table = $_POST['table'] ?? '';
    $id = $_POST['id'] ?? '';
    $status_name = trim($_POST['status_name'] ?? '');
    $primary = "{$table}_id";
    if ($status_name === '') {
        redirect_tab($table, null, 'Status name cannot be empty.');
    }
    if (!unique_check($pdo, $table, $status_name, $id)) {
        redirect_tab($table, null, 'Another status with this name exists.');
    }
    $sql = "UPDATE {$table} SET status_name = :name WHERE {$primary} = :id";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute(['name' => $status_name, 'id' => $id]);
        redirect_tab($table, 'Updated successfully.');
    } catch (PDOException $e) {
        redirect_tab($table, null, 'Database error while updating.');
    }
}

// Handle Delete (GET with confirmation)
if (isset($_GET['delete']) && isset($_GET['table'])) {
    $table = $_GET['table'];
    $id = $_GET['delete'];
    $primary = "{$table}_id";
    $sql = "DELETE FROM {$table} WHERE {$primary} = :id";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute(['id' => $id]);
        redirect_tab($table, 'Deleted successfully.');
    } catch (PDOException $e) {
        redirect_tab($table, null, 'Database error while deleting.');
    }
}

// UI helpers
$tab = $_GET['tab'] ?? 'batch_status';
$msg = $_GET['msg'] ?? null;
$err = $_GET['err'] ?? null;

function render_table($pdo, $table, $label) {
    $primary = "{$table}_id";
    $out = "<div class='card p-3 mb-3'><h5 class='mb-3 text-primary'>{$label} Status List</h5>";
    $out .= "<div class='table-responsive'><table class='table table-hover align-middle text-center'>";
    $out .= "<thead class='table-light'><tr><th style='width:60px'>#</th><th>Status Name</th><th style='width:220px'>Actions</th></tr></thead><tbody>";

    $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$primary} ASC");
    $i = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = htmlspecialchars($row[$primary], ENT_QUOTES);
        $name = htmlspecialchars($row['status_name'], ENT_QUOTES);
        $out .= "<tr>
                    <td>{$i}</td>
                    <td>{$name}</td>
                    <td>
                      <button class='btn btn-sm btn-warning btn-custom me-1' data-bs-toggle='modal' data-bs-target='#editModal{$table}{$id}'>Edit</button>
                      <a href='status.php?delete={$id}&table={$table}' class='btn btn-sm btn-danger btn-custom' onclick='return confirm(\"Delete this record?\")'>Delete</a>
                    </td>
                 </tr>";

        // edit modal
        $out .= "
        <div class='modal fade' id='editModal{$table}{$id}' tabindex='-1' aria-hidden='true'>
          <div class='modal-dialog'>
            <div class='modal-content'>
              <form method='POST' action='status.php?tab={$table}'>
                <div class='modal-header'>
                  <h5 class='modal-title'>Edit {$label} Status</h5>
                  <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>
                  <input type='hidden' name='id' value='{$id}'>
                  <input type='hidden' name='table' value='{$table}'>
                  <div class='mb-3'>
                    <label class='form-label'>Status Name</label>
                    <input type='text' name='status_name' class='form-control' value=\"".htmlspecialchars($row['status_name'], ENT_QUOTES)."\" required>
                  </div>
                </div>
                <div class='modal-footer'>
                  <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                  <button type='submit' name='edit_status' class='btn btn-primary'>Save</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        ";
        $i++;
    }

    $out .= "</tbody></table></div>";

    // add form
    $out .= "<form method='POST' class='mt-3 d-flex gap-2 justify-content-center' action='status.php?tab={$table}'>
              <input type='hidden' name='table' value='{$table}'>
              <input type='text' name='status_name' class='form-control w-50' placeholder='Enter new status' required>
              <button type='submit' name='add_status' class='btn btn-success'>Add New</button>
             </form>";

    $out .= "</div>";
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Status</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; font-family:Poppins, Arial, sans-serif; }
    .container { margin-top:40px; margin-bottom:80px; max-width:1100px; }
    .nav-tabs .nav-link.active { background:#0d6efd; color:#fff; }
    .btn-custom { border-radius:18px; padding:4px 12px; }
    .back-btn { position:fixed; top:20px; left:20px; }
    .card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
  </style>
</head>
<body>
<a href="admin_dashboard.php" class="btn btn-secondary back-btn">← Back</a>
<div class="container">
  <h2 class="text-center mb-4">Manage Status</h2>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
  <?php endif; ?>

  <ul class="nav nav-tabs justify-content-center mb-3" id="tabs">
    <li class="nav-item"><a class="nav-link <?php echo $tab==='batch_status'?'active':'' ?>" data-bs-toggle="tab" href="#batch">Batch Status</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab==='delivery_status'?'active':'' ?>" data-bs-toggle="tab" href="#delivery">Delivery Status</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab==='payment_status'?'active':'' ?>" data-bs-toggle="tab" href="#payment">Payment Status</a></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade <?php echo $tab==='batch_status'?'show active':'' ?>" id="batch">
      <?php echo render_table($pdo, 'batch_status', 'Batch'); ?>
    </div>
    <div class="tab-pane fade <?php echo $tab==='delivery_status'?'show active':'' ?>" id="delivery">
      <?php echo render_table($pdo, 'delivery_status', 'Delivery'); ?>
    </div>
    <div class="tab-pane fade <?php echo $tab==='payment_status'?'show active':'' ?>" id="payment">
      <?php echo render_table($pdo, 'payment_status', 'Payment'); ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// preserve tab selection when linking with ?tab=table
(function(){
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  if(tab){
    const mapping = {
      'batch_status':'#batch',
      'delivery_status':'#delivery',
      'payment_status':'#payment'
    };
    const selector = mapping[tab];
    if(selector){
      const tabEl = document.querySelector('a[href="'+selector+'"]');
      if(tabEl){
        const bs = bootstrap.Tab.getOrCreateInstance(tabEl);
        bs.show();
      }
    }
  }
})();
</script>
</body>
</html>

