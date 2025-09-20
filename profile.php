<?php
session_start();
include 'config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success'; // can be 'success' or 'error'

// Handle form submission for profile updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_fullname = $_POST['fullname'];
    $new_email = $_POST['email'];
    $new_phone = empty($_POST['phone']) ? null : $_POST['phone'];
    $new_dob = empty($_POST['dob']) ? null : $_POST['dob'];
    $new_bio = $_POST['bio'] ?? null;
    
    // --- Profile Picture Upload Logic ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = 'uploads/';
        // Create a unique filename to prevent overwriting
        $image_name = uniqid() . '_' . basename($_FILES['profile_image']['name']);
        $target_file = $upload_dir . $image_name;
        
        // Move the uploaded file to the 'uploads' directory
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            // If upload is successful, update the database with the new filename
            $sql_img_update = "UPDATE users SET profile_image = ? WHERE id = ?";
            $stmt_img = $conn->prepare($sql_img_update);
            $stmt_img->bind_param("si", $image_name, $user_id);
            $stmt_img->execute();
            $stmt_img->close();
        }
    }
    // --- End of Upload Logic ---

    $sql_update = "UPDATE users SET fullname = ?, email = ?, phone = ?, dob = ?, bio = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sssssi", $new_fullname, $new_email, $new_phone, $new_dob, $new_bio, $user_id);
    
    if ($stmt_update->execute()) {
        header("Location: profile.php?status=success");
        exit();
    } else {
        $message = "Error updating profile.";
        $message_type = 'error';
    }
    $stmt_update->close();
}

// Check for status messages from redirects
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $message = "Profile updated successfully!";
        $message_type = 'success';
    }
    if ($_GET['status'] == 'investment_added') {
        $message = "Investment added successfully!";
        $message_type = 'success';
    }
    if ($_GET['status'] == 'investment_error') {
        $message = "Error adding investment.";
        $message_type = 'error';
    }
}

// Fetch user data from the database, including profile_image
$sql_user_data = "SELECT fullname, email, username, phone, dob, bio, profile_image FROM users WHERE id = ?";
$stmt_data = $conn->prepare($sql_user_data);
$stmt_data->bind_param("i", $user_id);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$user_data = $result_data->fetch_assoc();
$stmt_data->close();

$fullname = $user_data['fullname'];
$email = $user_data['email'];
$username = $user_data['username'] ?? 'user';
$phone = $user_data['phone'] ?? '';
$dob = $user_data['dob'] ?? '';
$bio = $user_data['bio'] ?? 'Click Edit Profile to add a bio!';
$profile_image = $user_data['profile_image'];

// Initials logic
$name_parts = explode(' ', $fullname);
$first_initial = strtoupper(substr($name_parts[0], 0, 1));
$last_initial = count($name_parts) > 1 ? strtoupper(substr(end($name_parts), 0, 1)) : '';
$username_initials = $first_initial . $last_initial;

// Calculate total balance from transactions table
$sql_balance = "SELECT SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END) AS total_balance FROM transactions WHERE user_id = ?";
$stmt_balance = $conn->prepare($sql_balance);
$stmt_balance->bind_param("i", $user_id);
$stmt_balance->execute();
$total_balance = $stmt_balance->get_result()->fetch_assoc()['total_balance'] ?? 0;
$stmt_balance->close();

// Calculate total investments from investments table
$sql_investments = "SELECT SUM(amount) AS total_investments FROM investments WHERE user_id = ?";
$stmt_investments = $conn->prepare($sql_investments);
$stmt_investments->bind_param("i", $user_id);
$stmt_investments->execute();
$total_investments = $stmt_investments->get_result()->fetch_assoc()['total_investments'] ?? 0;
$stmt_investments->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FinTracker</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-bg: #f0f2f5; --card-bg: rgba(255, 255, 255, 0.1); --text-color: #e0e0e0; --text-muted: #a0a0b0; --accent-color: #8a2be2; --progress-bar-bg: rgba(0, 0, 0, 0.2); --progress-bar-fill: #4caf50; --progress-bar-fill-alt: #f39c12; --progress-bar-fill-alt2: #3498db;
        }
        body { background-color: #2c2a4a; background-image: linear-gradient(135deg, #2c2a4a 0%, #4f3a65 100%); color: var(--text-color); }
        .main-content { padding: 20px; display: flex; flex-direction: column; gap: 20px; }
        .card { background: var(--card-bg); border-radius: 16px; padding: 24px; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .profile-hero-card { display: flex; align-items: center; gap: 24px; padding: 30px; /* Made bigger */ }
        .profile-hero-card .avatar, .header-actions .profile-avatar { width: 90px; height: 90px; border-radius: 50%; background-color: var(--accent-color); display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold; color: white; flex-shrink: 0; overflow: hidden; }
        .header-actions .profile-avatar { width: 40px; height: 40px; font-size: 16px; }
        .profile-hero-card .avatar img, .header-actions .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-hero-card .profile-info { flex-grow: 1; }
        .profile-hero-card .edit-profile-btn { margin-left: auto; background-color: var(--accent-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; flex-shrink: 0; }
        
        .profile-info .view-mode h1 { margin: 0; font-size: 24px; } .profile-info .view-mode .username { color: var(--text-muted); margin-bottom: 8px; } .profile-info .view-mode .bio { font-size: 14px; margin-bottom: 12px; }
        .profile-info .edit-mode { display: none; } .profile-hero-card.editing .edit-mode { display: block; } .profile-hero-card.editing .view-mode { display: none; }
        .form-group { margin-bottom: 10px; } .form-group input, .form-group textarea { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.3); background: rgba(0, 0, 0, 0.2); color: white; font-family: inherit; font-size: 14px; } .form-group textarea { resize: vertical; min-height: 60px; } .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        .stats-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .stat-block { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; aspect-ratio: 1 / 1; }
        .stat-block .label { font-size: 16px; color: var(--text-muted); margin-bottom: 8px; } .stat-block .value { font-size: 32px; font-weight: bold; }
        .stat-block .add-btn { margin-top: 15px; background-color: rgba(255, 255, 255, 0.2); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: bold; cursor: pointer; }
        .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card h2 { margin-top: 0; border-bottom: 1px solid rgba(255, 255, 255, 0.2); padding-bottom: 10px; margin-bottom: 20px; font-size: 18px; }
        .progress-item { margin-bottom: 15px; } .progress-item .progress-label { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; } .progress-bar { background: var(--progress-bar-bg); height: 8px; border-radius: 4px; overflow: hidden; } .progress-bar .progress-fill { height: 100%; border-radius: 4px; } .progress-fill.green { background-color: var(--progress-bar-fill); } .progress-fill.orange { background-color: var(--progress-bar-fill-alt); } .progress-fill.blue { background-color: var(--progress-bar-fill-alt2); }
        .achievement { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; } .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); } .activity-item:last-child { border-bottom: none; } .activity-item .amount.income { color: #2ecc71; } .activity-item .amount.expense { color: #e74c3c; }
        .alert-success, .alert-error { padding: 15px; color: white; border-radius: 8px; text-align: center; } .alert-success { background-color: #2ecc71; } .alert-error { background-color: #e74c3c; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #4f3a65; padding: 30px; border-radius: 16px; width: 90%; max-width: 500px; position: relative; }
        .modal-close { position: absolute; top: 15px; right: 15px; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }

        @media (max-width: 992px) { .main-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .profile-hero-card { flex-direction: column; align-items: flex-start; } .profile-hero-card .edit-profile-btn { margin-left: 0; margin-top: 10px; } .stats-container { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; }}
    </style>
</head>
<body>
    <header class="header">
        <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        <div class="logo">FinTracker</div>
        <div class="header-actions">
            <div class="profile-avatar" onclick="window.location.href='profile.php'">
                 <?php if (!empty($profile_image)): ?>
                    <img src="uploads/<?php echo htmlspecialchars($profile_image); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo $username_initials; ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="nav-section"><div class="nav-title">Main</div><a href="dashboard.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>Dashboard</a></div>
            <div class="nav-section"><div class="nav-title">Profile</div><a href="profile.php" class="nav-item active"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Profile</a></div>
            <div class="nav-section"><div class="nav-title">Financial</div><a href="#" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/></svg>Account Info</a><a href="add_income.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843-.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>Add Income</a><a href="add_expense.html" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/></svg>Add Transaction</a><a href="#" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Transaction History</a><a href="#" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>Budget Suggestions</a></div>
            <div class="nav-section"><div class="nav-title">Account</div><a href="logout.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>Logout</a></div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>

    <main class="main-content">
        <?php if ($message): ?>
            <div class="alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card profile-hero-card" id="profileCard">
            <div class="avatar">
                 <?php if (!empty($profile_image)): ?>
                    <img src="uploads/<?php echo htmlspecialchars($profile_image); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo $username_initials; ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <div class="view-mode">
                    <h1><?php echo htmlspecialchars($fullname); ?></h1>
                    <p class="username">@<?php echo htmlspecialchars($username); ?></p>
                    <p class="bio"><?php echo nl2br(htmlspecialchars($bio)); ?></p>
                </div>
                <form action="profile.php" method="POST" class="edit-mode" id="profileForm" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <div class="form-group"><input type="text" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="Full Name" required></div>
                    <div class="form-group"><textarea name="bio" placeholder="Your bio..."><?php echo htmlspecialchars($bio); ?></textarea></div>
                    <div class="form-grid">
                        <div class="form-group"><input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Phone Number"></div>
                        <div class="form-group"><input type="date" name="dob" value="<?php echo htmlspecialchars($dob); ?>"></div>
                    </div>
                    <div class="form-group">
                        <label for="profile_image" style="font-size: 12px; color: var(--text-muted);">Update Profile Picture</label>
                        <input type="file" name="profile_image" id="profile_image" style="padding: 0; border: none; background: none;">
                    </div>
                </form>
            </div>
            <button class="edit-profile-btn" id="editProfileBtn">Edit Profile</button>
        </div>

        <div class="stats-container">
            <div class="card stat-block"><div class="label">Total Balance</div><div class="value">$<?php echo number_format($total_balance, 2); ?></div></div>
            <div class="card stat-block">
                <div class="label">Investments</div>
                <div class="value">$<?php echo number_format($total_investments, 2); ?></div>
                <div class="add-btn" id="addInvestmentBtn">+ Add Investment</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="main-column">
                <div class="card">
                    <h2>Savings Goals</h2>
                    <div class="progress-item"><div class="progress-label"><span>Emergency Fund</span><span>$2,000 / $5,000</span></div><div class="progress-bar"><div class="progress-fill green" style="width: 40%;"></div></div></div>
                    <div class="progress-item"><div class="progress-label"><span>Vacation</span><span>$800 / $2,000</span></div><div class="progress-bar"><div class="progress-fill orange" style="width: 40%;"></div></div></div>
                    <div class="progress-item"><div class="progress-label"><span>New Laptop</span><span>$1,100 / $1,500</span></div><div class="progress-bar"><div class="progress-fill blue" style="width: 73%;"></div></div></div>
                </div>
                <div class="card">
                    <h2>Recent Activity</h2>
                    <div class="activity-item"><span>Starbucks Coffee</span><span class="amount expense">-$7.52</span></div>
                    <div class="activity-item"><span>Freelance Job</span><span class="amount income">+$450.00</span></div>
                    <div class="activity-item"><span>Payment for Laptop Fund</span><span class="amount expense">-$150.00</span></div>
                </div>
            </div>
            <div class="side-column">
                <div class="card">
                    <h2>Monthly Budget</h2>
                    <div class="progress-item"><div class="progress-label"><span>Food</span><span>$320 / $450</span></div><div class="progress-bar"><div class="progress-fill green" style="width: 71%;"></div></div></div>
                    <div class="progress-item"><div class="progress-label"><span>Transport</span><span>$95 / $150</span></div><div class="progress-bar"><div class="progress-fill orange" style="width: 63%;"></div></div></div>
                </div>
                <div class="card">
                    <h2>Achievements</h2>
                    <div class="achievement"><div class="icon">🏆</div><div><div class="title">Prodigal</div><div class="description">Completed your first savings goal.</div></div></div>
                    <div class="achievement"><div class="icon">🎯</div><div><div class="title">Budget Master</div><div class="description">Stayed under budget for 3 months.</div></div></div>
                </div>
            </div>
        </div>
    </main>
    
    <div class="modal-overlay" id="investmentModal">
        <div class="modal-content">
            <button class="modal-close" id="closeInvestmentModalBtn">&times;</button>
            <h2>Add New Investment</h2>
            <form action="add_investment.php" method="POST">
                <input type="hidden" name="add_investment" value="1">
                <div class="form-group">
                    <label for="investment_name">Investment Name (e.g., Stocks, Mutual Fund)</label>
                    <input type="text" id="investment_name" name="investment_name" required>
                </div>
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" required>
                </div>
                <div class="form-group">
                    <label for="investment_date">Date</label>
                    <input type="date" id="investment_date" name="investment_date" required>
                </div>
                <button type="submit" class="edit-profile-btn">Add</button>
            </form>
        </div>
    </div>

    <script>
        // Sidebar Script
        const hamburger = document.getElementById('hamburger');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        function toggleSidebar() { hamburger.classList.toggle('active'); sidebar.classList.toggle('active'); overlay.classList.toggle('active'); }
        if (hamburger) hamburger.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', toggleSidebar);

        // Inline Edit Script
        const profileCard = document.getElementById('profileCard');
        const editProfileBtn = document.getElementById('editProfileBtn');
        const profileForm = document.getElementById('profileForm');
        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => {
                const isEditing = profileCard.classList.contains('editing');
                if (isEditing) {
                    profileForm.submit();
                } else {
                    profileCard.classList.add('editing');
                    editProfileBtn.textContent = 'Save Changes';
                }
            });
        }

        // Investment Modal Script
        const addInvestmentBtn = document.getElementById('addInvestmentBtn');
        const investmentModal = document.getElementById('investmentModal');
        const closeInvestmentModalBtn = document.getElementById('closeInvestmentModalBtn');
        if (addInvestmentBtn) {
            addInvestmentBtn.addEventListener('click', () => {
                investmentModal.classList.add('active');
            });
        }
        if (closeInvestmentModalBtn) {
            closeInvestmentModalBtn.addEventListener('click', () => {
                investmentModal.classList.remove('active');
            });
        }
        if (investmentModal) {
            investmentModal.addEventListener('click', (event) => {
                if (event.target === investmentModal) {
                    investmentModal.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>