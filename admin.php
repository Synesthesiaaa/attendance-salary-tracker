<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Database configuration
$host = 'localhost';
$db   = 'testdb';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Verify if user is admin
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $result = $stmt->fetch();

    if (!$result || !$result['is_admin']) {
        // Not admin, redirect
        header("Location: dashboard.php");
        exit;
    }

    // Search query
    $search = trim($_GET['search'] ?? '');

    // Pagination setup
    $perPage = 10;
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    // Base query and count query
    $params = [];
    $where = '';
    if ($search !== '') {
        $where = "WHERE username LIKE ? OR email LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);

    // Fetch users for current page
    $sql = "SELECT id, username, first_name, last_name, id_number, email, contact_number, birthday, gender, is_admin FROM users $where ORDER BY id LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Fetch attendance counts per user in batch for efficiency
    $userIds = array_column($users, 'id');
    $attendanceCounts = [];
    if ($userIds) {
        $inQuery = str_repeat('?,', count($userIds) -1) . '?';

        // Get clock-in counts
        $inStmt = $pdo->prepare("SELECT user_id, COUNT(*) as total_in FROM attendance WHERE type = 'in' AND user_id IN ($inQuery) GROUP BY user_id");
        $inStmt->execute($userIds);
        $ins = $inStmt->fetchAll();
        foreach ($ins as $row) {
            $attendanceCounts[$row['user_id']]['in'] = $row['total_in'];
        }

        // Get clock-out counts
        $outStmt = $pdo->prepare("SELECT user_id, COUNT(*) as total_out FROM attendance WHERE type = 'out' AND user_id IN ($inQuery) GROUP BY user_id");
        $outStmt->execute($userIds);
        $outs = $outStmt->fetchAll();
        foreach ($outs as $row) {
            $attendanceCounts[$row['user_id']]['out'] = $row['total_out'];
        }

        // Handle hourly rate update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
    $userIdToUpdate = intval($_POST['user_id']);
    $newRate = floatval($_POST['hourly_rate']);
    $updateStmt = $pdo->prepare("UPDATE users SET hourly_rate = ? WHERE id = ?");
    $updateStmt->execute([$newRate, $userIdToUpdate]);
    header("Location: admin.php?page={$page}&search=" . urlencode($search));
    exit;
}

    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <title>Admin Panel - User Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6 font-sans">

  <header class="max-w-7xl mx-auto flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-indigo-700">Admin Panel - User Management</h1>
    <a href="logout.php" class="text-red-600 font-semibold hover:underline">Logout</a>
  </header>

  <main class="max-w-7xl mx-auto bg-white shadow rounded p-6">
    <form method="get" class="mb-4 flex items-center gap-2">
      <input
        type="search"
        name="search"
        value="<?php echo htmlspecialchars($search); ?>"
        placeholder="Search by username or email"
        class="border border-gray-300 rounded px-3 py-2 flex-grow focus:outline-none focus:ring-2 focus:ring-indigo-600"
      />
      <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Search</button>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse border border-gray-300">
        <thead class="bg-indigo-100">
          <tr>
            <th class="border border-gray-300 px-4 py-2">ID</th>
            <th class="border border-gray-300 px-4 py-2">Username</th>
            <th class="border border-gray-300 px-4 py-2">First Name</th>
            <th class="border border-gray-300 px-4 py-2">Last Name</th>
            <th class="border border-gray-300 px-4 py-2">ID Number</th>
            <th class="border border-gray-300 px-4 py-2">Email</th>
            <th class="border border-gray-300 px-4 py-2">Contact Number</th>
            <th class="border border-gray-300 px-4 py-2">Birthday</th>
            <th class="border border-gray-300 px-4 py-2">Gender</th>
            <th class="border border-gray-300 px-4 py-2">Admin</th>
            <th class="border border-gray-300 px-4 py-2">Total Clock-Ins</th>
            <th class="border border-gray-300 px-4 py-2">Total Clock-Outs</th>
            <th class="border border-gray-300 px-4 py-2">Hourly Rate (₱)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($users) === 0): ?>
            <tr>
              <td colspan="12" class="border border-gray-300 px-4 py-4 text-center text-gray-500">No users found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr class="hover:bg-indigo-50">
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['id']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['username']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['first_name']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['last_name']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['id_number']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['email']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['contact_number']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['birthday']); ?></td>
                <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($u['gender']); ?></td>
                <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $u['is_admin'] ? 'Yes' : 'No'; ?></td>
                <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $attendanceCounts[$u['id']]['in'] ?? 0; ?></td>
                <td class="border border-gray-300 px-4 py-2 text-center"><?php echo $attendanceCounts[$u['id']]['out'] ?? 0; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div class="mt-4 flex justify-center space-x-2">
      <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Previous</a>
      <?php endif; ?>

      <span class="px-3 py-1 bg-gray-200 rounded"><?php echo $page; ?> / <?php echo $totalPages; ?></span>

      <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Next</a>
      <?php endif; ?>
  </form>
</td>

    </div>
  </main>
</body>
</html>
