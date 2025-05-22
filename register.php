<?php
session_start();

// Database credentials - change as needed
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

// Initialize variables
$errors = [];
$success = false;
$showForm = true;

// Check form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $birthday = $_POST['birthday'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $id_number = trim($_POST['id_number'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');

    // Validate inputs
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores.';
    }
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($first_name)) {
        $errors[] = 'First Name is required.';
    }
    if (empty($last_name)) {
        $errors[] = 'Last Name is required.';
    }
    if (empty($birthday)) {
        $errors[] = 'Birthday is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || !strtotime($birthday)) {
        $errors[] = 'Birthday is not a valid date.';
    }
    if (empty($gender) || !in_array($gender, ['Male','Female','Other'])) {
        $errors[] = 'Please select a valid gender.';
    }
    if (empty($id_number)) {
        $errors[] = 'ID Number is required.';
    }
    if (empty($contact_number)) {
        $errors[] = 'Contact Number is required.';
    }

    if (empty($errors)) {
        try {
            // Connect to DB
            $pdo = new PDO($dsn, $user, $pass, $options);

            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $existing = $stmt->fetch();

            if ($existing) {
                $errors[] = 'Username or email already exists.';
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert user and mark as verified immediately
                $stmt = $pdo->prepare("INSERT INTO users (username, first_name, last_name, id_number, email, contact_number, birthday, gender, password, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$username, $first_name, $last_name, $id_number, $email, $contact_number, $birthday, $gender, $password_hash]);
                
                $success = true;
                $showForm = false;
            }
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Registration</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-green-400 to-blue-500 min-h-screen flex items-center justify-center">

  <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
    <h1 class="text-3xl font-bold text-center text-green-700 mb-6">Register</h1>

    <?php if ($success): ?>
      <div class="text-center text-green-700 font-semibold mb-4">
        Registration successful! You can now login.
      </div>

    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="mb-4">
        <?php foreach ($errors as $error): ?>
          <p class="text-red-600 font-medium"><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
      <form method="post" class="space-y-6" novalidate>
        <input 
          type="text" 
          name="username" 
          placeholder="Username" 
          required 
          class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
          value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
        />
        <input 
          type="email"
          name="email"
          placeholder="Email"
          required
          class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
          value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
        />
        <input 
          type="password" 
          name="password" 
          placeholder="Password" 
          required 
          class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
        />
        <input 
          type="password" 
          name="password_confirm" 
          placeholder="Confirm Password" 
          required 
          class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
        />
        <input
    type="text"
    name="first_name"
    placeholder="First Name"
    required
    class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
  />
  <input
    type="text"
    name="last_name"
    placeholder="Last Name"
    required
    class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
  />
  <input
    type="date"
    name="birthday"
    required
    class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
    value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>"
  />
  <select
    name="gender"
    required
    class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
  >
    <option value="" disabled <?php echo empty($_POST['gender']) ? 'selected' : ''; ?>>Select gender</option>
    <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
    <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
    <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
  </select>
  <input
    type="text"
    name="id_number"
    placeholder="ID Number"
    required
    class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
    value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>"
  />
  <input
    type="text"
    name="contact_number"
    placeholder="Contact Number"
    required
    class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600"
    value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>"
  />
        <button 
          type="submit" 
          class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-md transition ease-in-out duration-150"
        >Register</button>
      </form>
    <?php endif; ?>
  </div>

</body>
</html>
