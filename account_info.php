<?php
session_start();
include 'config.php';

// Redirect to index if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- FETCH LATEST USER DATA FOR DISPLAY ---
$stmt_user = $conn->prepare("SELECT fullname, email, username, profile_image, creation_date FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$fullname = $user_data['fullname'] ?? 'N/A';
$email = $user_data['email'] ?? 'N/A';
$profile_image = $user_data['profile_image'];
$join_date = isset($user_data['creation_date']) ? date("F Y", strtotime($user_data['creation_date'])) : 'N/A';


// --- Get User Initials for Header/Avatar ---
$name_parts = explode(' ', $fullname);
$first_initial = strtoupper(substr($name_parts[0], 0, 1));
$last_initial = count($name_parts) > 1 ? strtoupper(substr(end($name_parts), 0, 1)) : '';
$username_initials = $first_initial . $last_initial;

// --- FETCH FINANCIAL STATS ---
// 1. Total Balance
$stmt_balance = $conn->prepare("SELECT SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END) AS total_balance FROM transactions WHERE user_id = ?");
$stmt_balance->bind_param("i", $user_id);
$stmt_balance->execute();
$total_balance = $stmt_balance->get_result()->fetch_assoc()['total_balance'] ?? 0;
$stmt_balance->close();

// 2. Monthly Spending
$current_month = date('Y-m');
$stmt_expense = $conn->prepare("SELECT SUM(amount) AS total_expense FROM transactions WHERE user_id = ? AND transaction_type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
$stmt_expense->bind_param("is", $user_id, $current_month);
$stmt_expense->execute();
$monthly_spending = $stmt_expense->get_result()->fetch_assoc()['total_expense'] ?? 0;
$stmt_expense->close();

// 3. Savings Goal Progress
$stmt_goal = $conn->prepare("SELECT saved_amount, target_amount FROM savings_goals WHERE user_id = ? ORDER BY creation_date DESC LIMIT 1");
$stmt_goal->bind_param("i", $user_id);
$stmt_goal->execute();
$goal_data = $stmt_goal->get_result()->fetch_assoc();
$stmt_goal->close();

$savings_progress = 0;
if ($goal_data && $goal_data['target_amount'] > 0) {
    $savings_progress = round(($goal_data['saved_amount'] / $goal_data['target_amount']) * 100);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Information - FinTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Using a public CDN for icons for this example */
        @import url('https://unpkg.com/lucide@latest/dist/lucide.css');
        
        :root {
            --color-background: #1a1c23; --color-surface: #22252e; --color-text: #e1e1e6;
            --color-text-secondary: #a8a8b3; --color-primary: #00b37e; --color-secondary: rgba(138, 138, 147, 0.15);
            --color-border: rgba(138, 138, 147, 0.2);
        }
        body { background: #0f172a; }
        
        /* Sidebar and Header styles from your project for consistency */
        .header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: var(--color-surface); border-bottom: 1px solid var(--color-border); position: sticky; top: 0; z-index: 1010; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .logo { font-size: 20px; font-weight: 600; color: var(--color-text); }
        .hamburger { cursor: pointer; z-index: 1002; width: 24px; height: 24px; }
        .hamburger svg { width: 100%; height: 100%; stroke: var(--color-text); }
        .profile-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: var(--color-background); display: flex; align-items: center; justify-content: center; font-weight: 600; cursor: pointer; }
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
        .main-content-area { transition: margin-left 0.3s ease-in-out; }
         @media (min-width: 993px) {
            .main-content-area { margin-left: 260px; }
            .sidebar { left: 0; }
            body.sidebar-closed .main-content-area { margin-left: 0; }
            body.sidebar-closed .sidebar { left: -280px; }
        }

        /* Tab styling */
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    </style>
</head>
<body class="text-white">

    <header class="header">
        <div class="header-left">
            <div class="hamburger" id="hamburger">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="2.5" stroke-linecap="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </div>
            <div class="logo">FinTracker</div>
        </div>
        <div class="header-actions">
            <div class="profile-avatar" onclick="window.location.href='profile.php'"><?php echo htmlspecialchars($username_initials); ?></div>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="nav-section">
                <div class="nav-title">Main</div>
                <a href="dashboard.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>
                    Dashboard
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-title">Profile</div>
                <a href="profile.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Profile</a>
            </div>
            <div class="nav-section">
                <div class="nav-title">Financial</div>
                <a href="account_info.php" class="nav-item active"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/></svg>Account Info</a>
                <a href="add_income.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07-.34-.433.582a2.305 2.305 0 01-.567-.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843-.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>Add Income</a>
                <a href="add_transaction.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/></svg>Add Transaction</a>
                <a href="transaction_history.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Transaction History</a>
                <a href="budget_suggestions.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>Budget Suggestions</a>
            </div>
            <div class="nav-section">
                <div class="nav-title">Account</div>
                <a href="logout.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>Logout</a>
            </div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>

    <div class="main-content-area min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-6">
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-20 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
            <div class="absolute top-40 right-20 w-96 h-96 bg-cyan-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse delay-1000"></div>
            <div class="absolute bottom-20 left-1/2 w-80 h-80 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse delay-2000"></div>
        </div>
        <div class="max-w-6xl mx-auto relative z-10">
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-white mb-2 bg-gradient-to-r from-cyan-400 to-purple-400 bg-clip-text text-transparent">Account Information</h1>
                <p class="text-gray-300">Manage your profile and preferences</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:border-white/40 transition-all duration-300 hover:scale-105 group">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 flex items-center justify-center mb-4 group-hover:rotate-12 transition-transform duration-300">
                        <i data-lucide="wallet" class="w-6 h-6 text-white"></i>
                    </div>
                    <p class="text-gray-300 text-sm mb-1">Total Balance</p>
                    <p class="text-2xl font-bold text-white">$<?php echo number_format($total_balance, 2); ?></p>
                </div>
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:border-white/40 transition-all duration-300 hover:scale-105 group">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center mb-4 group-hover:rotate-12 transition-transform duration-300">
                        <i data-lucide="trending-up" class="w-6 h-6 text-white"></i>
                    </div>
                    <p class="text-gray-300 text-sm mb-1">Monthly Spending</p>
                    <p class="text-2xl font-bold text-white">$<?php echo number_format($monthly_spending, 2); ?></p>
                </div>
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 hover:border-white/40 transition-all duration-300 hover:scale-105 group">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-orange-500 to-red-500 flex items-center justify-center mb-4 group-hover:rotate-12 transition-transform duration-300">
                        <i data-lucide="award" class="w-6 h-6 text-white"></i>
                    </div>
                    <p class="text-gray-300 text-sm mb-1">Savings Progress</p>
                    <p class="text-2xl font-bold text-white"><?php echo $savings_progress; ?>%</p>
                </div>
            </div>

            <div class="bg-white/10 backdrop-blur-lg rounded-3xl border border-white/20 overflow-hidden shadow-2xl">
                <div class="relative h-48 bg-gradient-to-r from-purple-600 via-pink-600 to-blue-600">
                    <div class="absolute inset-0 bg-black/20"></div>
                    <div class="absolute -bottom-16 left-8 flex items-end space-x-6">
                        <div class="relative group">
                             <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-cyan-400 to-purple-600 p-1 shadow-2xl">
                                <?php if (!empty($profile_image)): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="w-full h-full rounded-2xl object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full rounded-2xl bg-slate-800 flex items-center justify-center text-5xl font-bold">
                                        <?php echo htmlspecialchars($username_initials); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pb-4">
                            <h2 class="text-3xl font-bold text-white mb-1"><?php echo htmlspecialchars($fullname); ?></h2>
                            <span class="inline-block px-4 py-1 bg-gradient-to-r from-yellow-400 to-orange-500 text-white text-sm font-semibold rounded-full">Premium User</span>
                        </div>
                    </div>
                    <a href="profile.php" class="absolute top-6 right-6 px-6 py-3 bg-white/20 backdrop-blur-md hover:bg-white/30 text-white rounded-xl transition-all flex items-center space-x-2 border border-white/30 hover:scale-105">
                        <i data-lucide="edit-2" class="w-4 h-4"></i><span>Edit Profile</span>
                    </a>
                </div>

                <div class="flex space-x-1 px-8 pt-20 border-b border-white/10">
                    <button data-tab="profile" class="tab-button px-6 py-3 font-semibold capitalize rounded-t-xl transition-all bg-white/10 text-white border-b-2 border-purple-500">Profile</button>
                    <button data-tab="security" class="tab-button px-6 py-3 font-semibold capitalize rounded-t-xl transition-all text-gray-400 hover:text-white hover:bg-white/5">Security</button>
                    <button data-tab="preferences" class="tab-button px-6 py-3 font-semibold capitalize rounded-t-xl transition-all text-gray-400 hover:text-white hover:bg-white/5">Preferences</button>
                </div>

                <div class="p-8">
                    <div id="profile-content" class="tab-content active">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="flex items-center space-x-2 text-gray-300 text-sm font-medium"><i data-lucide="user" class="w-4 h-4"></i><span>Full Name</span></label>
                                    <div class="px-4 py-3 bg-white/5 rounded-xl text-white border border-white/10"><?php echo htmlspecialchars($fullname); ?></div>
                                </div>
                                <div class="space-y-2">
                                    <label class="flex items-center space-x-2 text-gray-300 text-sm font-medium"><i data-lucide="mail" class="w-4 h-4"></i><span>Email Address</span></label>
                                    <div class="px-4 py-3 bg-white/5 rounded-xl text-white border border-white/10"><?php echo htmlspecialchars($email); ?></div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 px-4 py-3 bg-purple-500/20 border border-purple-500/30 rounded-xl">
                                <i data-lucide="calendar" class="w-5 h-5 text-purple-400"></i>
                                <span class="text-gray-300">Member since</span>
                                <span class="text-white font-semibold"><?php echo htmlspecialchars($join_date); ?></span>
                            </div>
                        </div>
                    </div>
                    <div id="security-content" class="tab-content">
                        <div class="space-y-6">
                            <div class="flex items-start space-x-4 p-6 bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-500/30 rounded-2xl">
                                <i data-lucide="shield" class="w-6 h-6 text-green-400 mt-1"></i>
                                <div>
                                    <h3 class="text-white font-semibold mb-1">Two-Factor Authentication</h3>
                                    <p class="text-gray-300 text-sm mb-3">Add an extra layer of security to your account.</p>
                                    <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-all">Enable 2FA</button>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <h3 class="text-white font-semibold flex items-center space-x-2"><i data-lucide="lock" class="w-5 h-5"></i><span>Change Password</span></h3>
                                <p class="text-gray-400 text-sm">To change your password, please go to the 'Edit Profile' page.</p>
                                <a href="profile.php" class="inline-block px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-xl font-semibold transition-all shadow-lg hover:scale-105">Go to Profile</a>
                            </div>
                        </div>
                    </div>
                    <div id="preferences-content" class="tab-content">
                         <div class="space-y-6">
                            <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl border border-white/10">
                                <div class="flex items-center space-x-3">
                                    <i data-lucide="bell" class="w-5 h-5 text-purple-400"></i>
                                    <div>
                                        <p class="text-white font-medium">Email Notifications</p><p class="text-gray-400 text-sm">Receive updates about your account</p>
                                    </div>
                                </div>
                                <label class="relative inline-block w-12 h-6"><input type="checkbox" class="sr-only peer" checked><div class="w-12 h-6 bg-gray-600 rounded-full peer peer-checked:after:translate-x-6 peer-checked:bg-purple-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
                            </div>
                            <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl border border-white/10">
                                <div class="flex items-center space-x-3">
                                    <i data-lucide="trending-up" class="w-5 h-5 text-cyan-400"></i>
                                    <div>
                                        <p class="text-white font-medium">Spending Alerts</p><p class="text-gray-400 text-sm">Get notified about unusual spending</p>
                                    </div>
                                </div>
                                <label class="relative inline-block w-12 h-6"><input type="checkbox" class="sr-only peer" checked><div class="w-12 h-6 bg-gray-600 rounded-full peer peer-checked:after:translate-x-6 peer-checked:bg-purple-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      lucide.createIcons();

      // Sidebar Toggle Script
      const hamburger = document.getElementById('hamburger');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      const body = document.body;
      
      function toggleSidebar() {
          if (window.innerWidth >= 993) {
               body.classList.toggle('sidebar-closed');
          } else {
               sidebar.classList.toggle('active');
               overlay.classList.toggle('active');
          }
      }
      hamburger.addEventListener('click', toggleSidebar);
      overlay.addEventListener('click', toggleSidebar);
       if (window.innerWidth >= 993) {
          body.classList.remove('sidebar-closed');
      }

      // Tab Functionality Script
      const tabButtons = document.querySelectorAll('.tab-button');
      const tabContents = document.querySelectorAll('.tab-content');

      tabButtons.forEach(button => {
          button.addEventListener('click', () => {
              const tab = button.dataset.tab;

              tabButtons.forEach(btn => {
                  btn.classList.remove('bg-white/10', 'text-white', 'border-purple-500');
                  btn.classList.add('text-gray-400', 'hover:text-white', 'hover:bg-white/5');
              });
              
              button.classList.add('bg-white/10', 'text-white', 'border-purple-500');
              button.classList.remove('text-gray-400', 'hover:text-white', 'hover:bg-white/5');
              
              tabContents.forEach(content => {
                  if (content.id === `${tab}-content`) {
                      content.classList.add('active');
                  } else {
                      content.classList.remove('active');
                  }
              });
          });
      });

    </script>
</body>
</html>