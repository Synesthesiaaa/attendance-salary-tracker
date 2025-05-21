<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Database config
$host = 'localhost';
$db   = 'testdb';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// PDO setup
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Get logged-in user info
    // Fetch user information
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, id_number, email, contact_number, birthday, gender FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch();
 
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    

    $user_id = $user['id'];

    // Process time range input from form
    $fromDate = $_POST['from_date'] ?? date('Y-m-01'); // Default start of current month
    $toDate = $_POST['to_date'] ?? date('Y-m-t');      // Default end of current month

        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $fromDate = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $toDate = date('Y-m-t');
        }
        $toDateEnd = date('Y-m-d 23:59:59', strtotime($toDate));



    // Handle clock in/out submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['clock_in']) || isset($_POST['clock_out']))) {
        if (isset($_POST['clock_in'])) {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, type) VALUES (?, 'in')");
            $stmt->execute([$user_id]);
        } elseif (isset($_POST['clock_out'])) {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, type) VALUES (?, 'out')");
            $stmt->execute([$user_id]);
        }
        // Redirect to avoid resubmission and keep date filters
        header("Location: dashboard.php");
        exit;
    }

    // Get total users count
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Attendance records for current month
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $stmt = $pdo->prepare("SELECT type, timestamp FROM attendance WHERE user_id = ? AND timestamp BETWEEN ? AND ? ORDER BY timestamp ASC");
    $stmt->execute([$user_id, $fromDate . ' 00:00:00', $toDateEnd]);
    $records = $stmt->fetchAll();


    // Process attendance data by day
    $attendanceByDay = [];
    foreach ($records as $record) {
        $day = date('j', strtotime($record['timestamp']));
        $time = date('H:i', strtotime($record['timestamp']));
        $attendanceByDay[$day][] = $record['type'] . " " . $time;
    }

    // For calendar generation
    $firstDayOfMonth = strtotime($monthStart);
    $daysInMonth = date('t', $firstDayOfMonth);
    $startWeekDay = date('w', $firstDayOfMonth); // Sunday=0 ... Saturday=6

// Initialize variables for calculations
$clockIns = [];
$clockOuts = [];
$totalHours = 0;
// Separate clock-in and clock-out records
foreach ($records as $record) {
    if ($record['type'] === 'in') {
        $clockIns[] = $record['timestamp'];
    } elseif ($record['type'] === 'out') {
        $clockOuts[] = $record['timestamp'];
    }
}
    // Calculate total rendered hours
    $clockIns = [];
    $clockOuts = [];
    $totalHours = 0.0;
    foreach ($records as $record) {
        if ($record['type'] === 'in') {
            $clockIns[] = $record['timestamp'];
        } elseif ($record['type'] === 'out') {
            $clockOuts[] = $record['timestamp'];
        }
    }
    $pairs = min(count($clockIns), count($clockOuts));
    for ($i = 0; $i < $pairs; $i++) {
        $inTime = new DateTime($clockIns[$i]);
        $outTime = new DateTime($clockOuts[$i]);
        if ($outTime > $inTime) { // Only count valid intervals
            $diff = $outTime->getTimestamp() - $inTime->getTimestamp();
            $totalHours += ($diff / 3600);
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
  <title>Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Roboto', sans-serif; background: #f3f4f6; }
    .tooltip {
      position: relative;
      display: inline-block;
    }
    .tooltip .tooltiptext {
      visibility: hidden;
      width: 140px;
      background-color: #4f46e5;
      color: #fff;
      text-align: center;
      border-radius: 8px;
      padding: 6px 8px;
      position: absolute;
      z-index: 10;
      bottom: 125%;
      left: 50%;
      margin-left: -70px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 0.825rem;
      pointer-events: none;
    }
    .tooltip:hover .tooltiptext {
      visibility: visible;
      opacity: 1;
      pointer-events: auto;
    }
  </style>
</head>
<body class="min-h-screen p-6 flex flex-col items-center">

  <header class="w-full max-w-5xl mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-indigo-700">Dashboard</h1>
    <a href="logout.php" class="text-red-600 hover:underline font-semibold">Logout</a>
  </header>
  <section class="w-full max-w-5xl bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-2xl font-bold mb-4 text-indigo-700">User  Information</h2>
    <div class="grid grid-cols-2 gap-x-8 text-gray-800">
        <p><strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
        <p><strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
        <p><strong>ID Number:</strong> <?php echo htmlspecialchars($user['id_number']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($user['contact_number']); ?></p>
        <p><strong>Birthday:</strong> <?php echo htmlspecialchars($user['birthday']); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender']); ?></p>
    </div>
</section>
  <section class="w-full max-w-5xl bg-white p-6 rounded-lg shadow mb-6">
    <div class="flex justify-between items-center">
      <div>
        <p class="text-gray-700 text-lg font-semibold">Total Registered Users:</p>
        <p class="text-indigo-700 text-4xl font-extrabold"><?= $totalUsers ?></p>
      </div>
      <div>
        <p class="text-gray-700 text-lg font-semibold">Current Date & Time:</p>
        <p id="datetime" class="text-indigo-700 text-4xl font-extrabold font-mono"></p>
      </div>
    </div>
  </section>

  

  <section class="w-full max-w-5xl bg-white p-6 rounded-lg shadow mb-6">
    <form method="post" class="flex justify-center gap-8">
      <button type="submit" name="clock_in" class="px-8 py-3 bg-green-600 hover:bg-green-700 rounded-md text-white font-bold transition">Clock In</button>
      <button type="submit" name="clock_out" class="px-8 py-3 bg-red-600 hover:bg-red-700 rounded-md text-white font-bold transition">Clock Out</button>
    </form>
  </section>

  <section class="w-full max-w-5xl bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-bold mb-4 text-indigo-700">Attendance Calendar (<?php echo date('F Y'); ?>)</h2>
    <div class="grid grid-cols-7 gap-2 text-center font-semibold text-indigo-700 mb-2">
      <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
    </div>
    <div class="grid grid-cols-7 gap-4 text-gray-800">
      <?php
      // Blank cells before first day
      for ($blank = 0; $blank < $startWeekDay; $blank++) {
          echo '<div></div>';
      }

      for ($day = 1; $day <= $daysInMonth; $day++) {
          $attendances = $attendanceByDay[$day] ?? [];
          $tooltipText = implode(", ", $attendances);
          $hasAttendance = !empty($attendances);
          $todayClass = ($day == date('j')) ? "bg-indigo-200 rounded font-bold" : "";
          ?>
          <div class="relative border border-gray-300 rounded p-2 <?= $hasAttendance ? 'bg-indigo-50 cursor-pointer' : '' ?> <?= $todayClass ?> tooltip">
            <?php echo $day; ?>
            <?php if ($hasAttendance): ?>
              <div class="absolute top-0 right-0 w-3 h-3 bg-indigo-600 rounded-full"></div>
              <span class="tooltiptext"><?php echo htmlentities($tooltipText); ?></span>
            <?php endif; ?>
          </div>
      <?php } ?>
    </div>
  </section>

  <section class="w-full max-w-5xl bg-white p-6 rounded-lg shadow mb-6">
    <form method="post" class="flex flex-wrap items-center gap-4 justify-center">
      <label class="font-semibold text-gray-700" for="from_date">From:</label>
      <input 
        type="date" 
        name="from_date" 
        id="from_date" 
        class="border border-gray-300 rounded-md px-3 py-2" 
        value="<?php echo htmlspecialchars($fromDate); ?>" 
        max="<?php echo date('Y-m-d'); ?>"
      />
      <label class="font-semibold text-gray-700" for="to_date">To:</label>
      <input 
        type="date" 
        name="to_date" 
        id="to_date" 
        class="border border-gray-300 rounded-md px-3 py-2" 
        value="<?php echo htmlspecialchars($toDate); ?>" 
        max="<?php echo date('Y-m-d'); ?>"
      />
      <button 
        type="submit" 
        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-md transition"
        name="filter"
      >
        Calculate
      </button>
    </form>
    <div class="mt-6 text-center">
      <p class="text-xl font-bold text-indigo-700">Total Rendered Hours: <span id="totalHours"><?php echo number_format($totalHours, 2); ?></span> hours</p>
    </div>
  </section>

  <script>
    function updateDateTime() {
      const now = new Date();
      const opts = { year: "numeric", month: "short", day: "numeric", hour: '2-digit', minute: '2-digit', second: '2-digit' };
      document.getElementById('datetime').textContent = now.toLocaleString('en-US', opts);
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();
  </script>
</body>
</html>
