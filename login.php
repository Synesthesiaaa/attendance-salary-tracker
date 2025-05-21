<?php
session_start();

// Database credentials - adjust as needed
$host = 'localhost';
$db   = 'testdb';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// DSN for PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$errors = [];
$success = false; // Initialize the success variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = "Please enter both username and password.";
    } else {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $userRow = $stmt->fetch();

            if (!$userRow) {
                $errors[] = "Invalid username or password.";
            } else {
                if (!password_verify($password, $userRow['password'])) {
                    $errors[] = "Invalid username or password.";
                } elseif (!$userRow['is_verified']) {
                    $errors[] = "Your account is not verified yet.";
                } else {
                    $_SESSION['username'] = htmlspecialchars($userRow['username'], ENT_QUOTES, 'UTF-8');
                    header("Location: dashboard.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login Form</title>

  <!-- Roboto font - Material Design classic font -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@900&display=swap" rel="stylesheet" />

  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    /* Animated gradient background */
    @keyframes gradientBG {
      0% {
        background-position: 0% 50%;
      }
      50% {
        background-position: 100% 50%;
      }
      100% {
        background-position: 0% 50%;
      }
    }
    body {
      background: linear-gradient(270deg, #4f46e5, #3b82f6, #9333ea, #2563eb);
      background-size: 800% 800%;
      animation: gradientBG 15s ease infinite;
      margin: 0;
      height: 100vh;
      overflow: hidden;
      position: relative;
      font-family: 'Roboto', sans-serif;
    }

    .app-name {
      position: fixed;
      top: 1rem;
      left: 1rem;
      font-weight: 900;
      font-size: 1.5rem;
      color: #bb86fc; /* Solid purple color */
      user-select: none;
      z-index: 50;
      pointer-events: none;
      letter-spacing: 0.1em;
      white-space: nowrap;
    }
  </style>
</head>
<body>

  <div class="app-name">AppName</div>

  <div class="flex items-center justify-center min-h-screen px-4">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md mx-auto">
      <h1 class="text-3xl font-bold text-center text-indigo-600 mb-8">Login</h1>

      <?php if (!empty($errors)): ?>
        <div class="mb-4">
          <?php foreach ($errors as $error): ?>
            <p class="text-red-600 text-center font-medium"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-6" novalidate>
        <input
          type="text"
          name="username"
          placeholder="Username"
          autocomplete="username"
          required
          class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
          value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
        />
        <input
          type="password"
          name="password"
          placeholder="Password"
          autocomplete="current-password"
          required
          class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <button
          type="submit"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-md transition ease-in-out duration-150"
        >
          Login
        </button>
      </form>

      <!-- Register button tab -->
      <div class="mt-6 text-center">
        <a href="register.php" 
           class="inline-block px-6 py-2 border border-indigo-600 text-indigo-600 rounded-full font-medium hover:bg-indigo-600 hover:text-white transition-colors duration-300">
          Register
        </a>
      </div>
    </div>
  </div>

</body>
</html>
