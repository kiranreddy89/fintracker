<?php
session_start();
include 'config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- HANDLE PROFILE UPDATE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_fullname = $_POST['fullname'];
    $new_bio = $_POST['bio'] ?? null;
    $profile_image_name = $_POST['existing_profile_image']; // Keep existing image by default

    // Profile Picture Upload Logic
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = 'uploads/';
        // Create a unique name to prevent overwriting files
        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $image_name = 'avatar_' . uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $image_name;
        
        // Check file size (max 5MB) and type
        if ($_FILES['profile_image']['size'] < 5 * 1024 * 1024 && in_array($_FILES['profile_image']['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                $profile_image_name = $image_name; // Set new image name for DB update
            }
        }
    }

    // Update profile details in the database
    $sql_update = "UPDATE users SET fullname = ?, bio = ?, profile_image = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sssi", $new_fullname, $new_bio, $profile_image_name, $user_id);
    
    if ($stmt_update->execute()) {
        $_SESSION['fullname'] = $new_fullname; // Update session variable
        $message = "Profile updated successfully!";
        // Redirect to prevent form resubmission
        header("Location: profile.php?status=success");
        exit();
    } else {
        $message = "Error updating profile.";
    }
    $stmt_update->close();
}

// --- FETCH DATA FOR DISPLAY ---

// 1. User Data
$stmt_user = $conn->prepare("SELECT fullname, email, bio, profile_image, creation_date FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$fullname = $user_data['fullname'] ?? 'N/A';
$email = $user_data['email'] ?? 'N/A';
$bio = $user_data['bio'] ?? 'Add a bio by editing your profile!';
$profile_image = $user_data['profile_image'] ?? null;
$join_date = isset($user_data['creation_date']) ? date("F Y", strtotime($user_data['creation_date'])) : 'N/A';
$name_parts = explode(' ', $fullname);
$first_initial = strtoupper(substr($name_parts[0], 0, 1));
$last_initial = count($name_parts) > 1 ? strtoupper(substr(end($name_parts), 0, 1)) : '';
$username_initials = $first_initial . $last_initial;

// 2. Financial Stats
$stmt_balance = $conn->prepare("SELECT SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END) AS total_balance FROM transactions WHERE user_id = ?");
$stmt_balance->bind_param("i", $user_id);
$stmt_balance->execute();
$total_balance = $stmt_balance->get_result()->fetch_assoc()['total_balance'] ?? 0;
$stmt_balance->close();

$current_month = date('Y-m');
$stmt_income = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
$stmt_income->bind_param("is", $user_id, $current_month);
$stmt_income->execute();
$monthly_income = $stmt_income->get_result()->fetch_assoc()['total_income'] ?? 0;
$stmt_income->close();

$stmt_expense = $conn->prepare("SELECT SUM(amount) AS total_expense FROM transactions WHERE user_id = ? AND transaction_type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
$stmt_expense->bind_param("is", $user_id, $current_month);
$stmt_expense->execute();
$monthly_expenses = $stmt_expense->get_result()->fetch_assoc()['total_expense'] ?? 0;
$stmt_expense->close();

$stmt_investments = $conn->prepare("SELECT SUM(amount) AS total_investments FROM investments WHERE user_id = ?");
$stmt_investments->bind_param("i", $user_id);
$stmt_investments->execute();
$total_investments = $stmt_investments->get_result()->fetch_assoc()['total_investments'] ?? 0;
$stmt_investments->close();

// 3. Recent Activity
$stmt_recent = $conn->prepare("SELECT source, transaction_date, amount, transaction_type, category FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 3");
$stmt_recent->bind_param("i", $user_id);
$stmt_recent->execute();
$recent_transactions = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_recent->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FinTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --color-background: #1a1c23; --color-surface: #22252e; --color-text: #e1e1e6;
            --color-text-secondary: #a8a8b3; --color-primary: #00b37e; --color-secondary: rgba(138, 138, 147, 0.15);
            --color-border: rgba(138, 138, 147, 0.2);
        }
        /* Sidebar and Header styles from your project for consistency */
        .header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: var(--color-surface); border-bottom: 1px solid var(--color-border); position: sticky; top: 0; z-index: 1010; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .logo { font-size: 20px; font-weight: 600; color: var(--color-text); }
        .hamburger { cursor: pointer; z-index: 1002; width: 24px; height: 24px; }
        .hamburger svg { width: 100%; height: 100%; stroke: var(--color-text); }
        .profile-avatar-header { width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: var(--color-background); display: flex; align-items: center; justify-content: center; font-weight: 600; cursor: pointer; }
        .sidebar { position: fixed; top: 0; left: -280px; width: 260px; height: 100%; background: var(--color-surface); border-right: 1px solid var(--color-border); transition: left 0.3s ease-in-out; z-index: 1001; padding-top: 80px; }
        .sidebar.active { left: 0; box-shadow: 0 0 20px rgba(0,0,0,0.2); }
        .sidebar-content { padding: 24px; }
        .nav-section { margin-bottom: 24px; }
        .nav-title { font-size: 12px; color: var(--color-text-secondary); margin-bottom: 8px; text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px; color: var(--color-text-secondary); font-weight: 500; transition: all 150ms; text-decoration: none; }
        .nav-item:hover, .nav-item.active { background: var(--color-secondary); color: var(--color-text); }
        .nav-icon { width: 20px; height: 20px; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .overlay.active { opacity: 1; pointer-events: auto; }
        .main-content { transition: margin-left 0.3s ease-in-out; }
         @media (min-width: 993px) {
            .main-content { margin-left: 260px; }
            .sidebar { left: 0; }
            body.sidebar-closed .main-content { margin-left: 0; }
            body.sidebar-closed .sidebar { left: -280px; }
        }
        /* Modal Styles */
        .modal-overlay { display: none; }
        .modal-overlay.active { display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); align-items: center; justify-content: center; z-index: 1010; }
    </style>
</head>
<body class="bg-slate-50">
    <header class="header">
        <div class="header-left">
            <div class="hamburger" id="hamburger">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </div>
            <div class="logo">FinTracker</div>
        </div>
        <div class="header-actions">
            <div class="profile-avatar-header"><?php echo htmlspecialchars($username_initials); ?></div>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
         <div class="sidebar-content">
            <div class="nav-section"><div class="nav-title">Main</div><a href="dashboard.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>Dashboard</a></div>
            <div class="nav-section"><div class="nav-title">Profile</div><a href="profile.php" class="nav-item active"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Profile</a></div>
            <div class="nav-section"><div class="nav-title">Financial</div><a href="account_info.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/></svg>Account Info</a><a href="add_income.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07-.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843-.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>Add Income</a><a href="add_transaction.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/></svg>Add Transaction</a><a href="transaction_history.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Transaction History</a><a href="budget_suggestions.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>Budget Suggestions</a></div>
            <div class="nav-section"><div class="nav-title">Account</div><a href="logout.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>Logout</a></div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>
    
    <main class="main-content">
        <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10">
            <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-blue-400/20 to-purple-400/20 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-gradient-to-tr from-emerald-400/20 to-cyan-400/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8">
                <h1 class="text-4xl font-bold bg-gradient-to-r from-slate-800 via-blue-800 to-indigo-800 bg-clip-text text-transparent mb-2">My Profile</h1>
                <p class="text-slate-600">Manage your account and preferences</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl border border-white/20 overflow-hidden">
                        <div class="h-32 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 relative"></div>
                        <div class="relative px-6 pb-6">
                            <div class="flex justify-center -mt-16 mb-4">
                                <div class="relative group">
                                    <div class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 p-1 shadow-2xl">
                                        <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-5xl font-bold text-slate-600">
                                            <?php if ($profile_image): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($username_initials); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button class="editProfileBtn absolute bottom-2 right-2 w-10 h-10 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transform hover:scale-110 transition-all">
                                        <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-center mb-6">
                                <h2 class="text-2xl font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($fullname); ?></h2>
                                <p class="text-slate-500 text-sm">Premium Member</p>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3 text-sm p-2 rounded-lg">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-100 to-indigo-100 flex items-center justify-center"><i data-lucide="mail" class="w-4 h-4 text-blue-600"></i></div>
                                    <span class="text-slate-600"><?php echo htmlspecialchars($email); ?></span>
                                </div>
                                 <div class="flex items-center gap-3 text-sm p-2 rounded-lg">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-100 to-pink-100 flex items-center justify-center"><i data-lucide="user" class="w-4 h-4 text-purple-600"></i></div>
                                    <span class="text-slate-600"><?php echo htmlspecialchars($bio); ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-sm p-2 rounded-lg">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-100 to-red-100 flex items-center justify-center"><i data-lucide="calendar" class="w-4 h-4 text-orange-600"></i></div>
                                    <span class="text-slate-600">Joined <?php echo htmlspecialchars($join_date); ?></span>
                                </div>
                            </div>
                            <button class="editProfileBtn w-full mt-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-medium hover:shadow-lg transform hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                                <i data-lucide="edit-2" class="w-4 h-4"></i>Edit Profile
                            </button>
                        </div>
                    </div>
                    <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl border border-white/20 p-6">
                        <div class="space-y-2">
                            <a href="logout.php" class="w-full py-2 px-4 text-left text-sm text-slate-700 hover:bg-gradient-to-r hover:from-red-50 hover:to-orange-50 rounded-lg transition-all flex items-center gap-3 group">
                                <i data-lucide="log-out" class="w-4 h-4 text-red-500"></i><span class="font-medium">Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6"><div class="flex items-start justify-between mb-4"><div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 flex items-center justify-center shadow-lg"><i data-lucide="dollar-sign" class="w-6 h-6 text-white"></i></div></div><p class="text-sm text-slate-600 mb-1">Total Balance</p><p class="text-2xl font-bold text-slate-800">$<?php echo number_format($total_balance, 2); ?></p></div>
                        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6"><div class="flex items-start justify-between mb-4"><div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center shadow-lg"><i data-lucide="trending-up" class="w-6 h-6 text-white"></i></div></div><p class="text-sm text-slate-600 mb-1">Monthly Income</p><p class="text-2xl font-bold text-slate-800">$<?php echo number_format($monthly_income, 2); ?></p></div>
                        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6"><div class="flex items-start justify-between mb-4"><div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-lg"><i data-lucide="credit-card" class="w-6 h-6 text-white"></i></div></div><p class="text-sm text-slate-600 mb-1">Monthly Expenses</p><p class="text-2xl font-bold text-slate-800">$<?php echo number_format($monthly_expenses, 2); ?></p></div>
                        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6"><div class="flex items-start justify-between mb-4"><div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-red-500 flex items-center justify-center shadow-lg"><i data-lucide="briefcase" class="w-6 h-6 text-white"></i></div></div><p class="text-sm text-slate-600 mb-1">Investments</p><p class="text-2xl font-bold text-slate-800">$<?php echo number_format($total_investments, 2); ?></p></div>
                    </div>
                    <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl border border-white/20 p-6">
                        <h3 class="text-xl font-bold text-slate-800 mb-6">Recent Activity</h3>
                        <div class="space-y-4">
                            <?php if (empty($recent_transactions)): ?>
                                <p class="text-slate-500 text-center py-4">No recent activity to display.</p>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $tx): ?>
                                    <div class="flex items-center gap-4 p-4 hover:bg-slate-50 rounded-xl transition-colors">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br <?php echo $tx['transaction_type'] === 'income' ? 'from-emerald-500 to-teal-500' : 'from-purple-500 to-pink-500'; ?> flex items-center justify-center">
                                            <i data-lucide="<?php echo $tx['transaction_type'] === 'income' ? 'arrow-down-left' : 'arrow-up-right'; ?>" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($tx['source']); ?></p>
                                            <p class="text-sm text-slate-500"><?php echo htmlspecialchars($tx['category']); ?> &bull; <?php echo date("d M, Y", strtotime($tx['transaction_date'])); ?></p>
                                        </div>
                                        <p class="font-bold <?php echo $tx['transaction_type'] === 'income' ? 'text-emerald-600' : 'text-red-600'; ?>">
                                            <?php echo ($tx['transaction_type'] === 'income' ? '+' : '-') . '$' . number_format($tx['amount'], 2); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="editProfileModal">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md m-4">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-slate-800">Edit Your Profile</h2>
                <button class="closeModalBtn text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="existing_profile_image" value="<?php echo htmlspecialchars($profile_image); ?>">
                <div class="space-y-4">
                    <div>
                        <label for="fullname" class="text-sm font-medium text-slate-600 block mb-2">Full Name</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label for="bio" class="text-sm font-medium text-slate-600 block mb-2">Bio</label>
                        <textarea id="bio" name="bio" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"><?php echo htmlspecialchars($bio); ?></textarea>
                    </div>
                    <div>
                        <label for="profile_image" class="text-sm font-medium text-slate-600 block mb-2">Update Profile Picture</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-4">
                    <button type="button" class="closeModalBtn px-6 py-2 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      lucide.createIcons();

      const hamburger = document.getElementById('hamburger');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      const body = document.body;
      function toggleSidebar() {
          if (window.innerWidth >= 993) { body.classList.toggle('sidebar-closed'); } 
          else { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); }
      }
      hamburger.addEventListener('click', toggleSidebar);
      overlay.addEventListener('click', toggleSidebar);
      if (window.innerWidth >= 993) { body.classList.remove('sidebar-closed'); }

      const editProfileModal = document.getElementById('editProfileModal');
      const editButtons = document.querySelectorAll('.editProfileBtn');
      const closeButtons = document.querySelectorAll('.closeModalBtn');
      
      editButtons.forEach(btn => btn.addEventListener('click', () => editProfileModal.classList.add('active')));
      closeButtons.forEach(btn => btn.addEventListener('click', () => editProfileModal.classList.remove('active')));
      editProfileModal.addEventListener('click', (event) => {
          if (event.target === editProfileModal) { editProfileModal.classList.remove('active'); }
      });
    </script>
</body>
</html>