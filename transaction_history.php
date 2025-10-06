<?php
session_start();
include 'config.php';

// Redirect to index if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// --- Get User Initials for Header ---
$name_parts = explode(' ', $fullname);
$first_initial = strtoupper(substr($name_parts[0], 0, 1));
$last_initial = count($name_parts) > 1 ? strtoupper(substr(end($name_parts), 0, 1)) : '';
$username_initials = $first_initial . $last_initial;

// --- Transaction Fetching Logic with Filtering ---
$filter_type = $_GET['filter_type'] ?? 'all'; // Default to 'all'
$where_clause = "WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter_type === 'monthly' && isset($_GET['month']) && !empty($_GET['month'])) {
    $where_clause .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $params[] = $_GET['month'];
    $types .= "s";
} elseif ($filter_type === 'yearly' && isset($_GET['year']) && !empty($_GET['year'])) {
    $where_clause .= " AND YEAR(transaction_date) = ?";
    $params[] = $_GET['year'];
    $types .= "i";
}

$sql = "SELECT transaction_date, source, category, amount, transaction_type FROM transactions $where_clause ORDER BY transaction_date DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Handle SQL error
    $transactions = [];
    error_log("SQL Prepare Failed: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - FinTracker</title>
    
    <style>
        :root {
            --color-background: #1a1c23; --color-surface: #22252e; --color-text: #e1e1e6;
            --color-text-secondary: #a8a8b3; --color-primary: #00b37e; --color-secondary: rgba(138, 138, 147, 0.15);
            --color-border: rgba(138, 138, 147, 0.2);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: "Inter", sans-serif; background: var(--color-background); color: var(--color-text); margin: 0; }
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
        
        .main-content { padding: 32px; transition: margin-left 0.3s ease-in-out; }
        @media (min-width: 993px) {
            .main-content { margin-left: 260px; }
            .sidebar { left: 0; }
            body.sidebar-closed .main-content { margin-left: 0; }
            body.sidebar-closed .sidebar { left: -280px; }
        }

        .history-container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            background: rgba(34, 37, 46, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 30px;
        }
        .page-title { font-size: 28px; font-weight: 600; text-align: center; margin-bottom: 8px; color: var(--color-text); }
        .page-subtitle { text-align: center; color: var(--color-text-secondary); font-size: 16px; margin-bottom: 30px; }
        
        .filter-form { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px; background: var(--color-background); padding: 15px; border-radius: 12px; }
        .filter-form select, .filter-form input { padding: 10px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; color: #fff; }
        .filter-form button { padding: 10px 20px; border: none; border-radius: 8px; background: var(--color-primary); color: var(--color-background); font-weight: bold; cursor: pointer; transition: background-color 0.2s; }
        .filter-form button:hover { background-color: #00a071; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; color: var(--color-text-secondary); margin-bottom: 4px;}

        .transactions-table { width: 100%; border-collapse: collapse; }
        .transactions-table th, .transactions-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--color-border); }
        .transactions-table th { font-size: 12px; text-transform: uppercase; color: var(--color-text-secondary); }
        .transactions-table td { font-size: 14px; }
        .transactions-table tr:last-child td { border-bottom: none; }
        .amount { font-weight: bold; }
        .amount.income { color: #2ecc71; }
        .amount.expense { color: #e74c3c; }

        .type-badge { padding: 4px 8px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: capitalize; }
        .type-badge.income { background-color: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .type-badge.expense { background-color: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        
        .no-transactions { text-align: center; padding: 40px; color: var(--color-text-secondary); }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="hamburger" id="hamburger">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </div>
            <div class="logo">FinTracker</div>
        </div>
        <div class="header-actions">
            <div class="profile-avatar" onclick="window.location.href='profile.php'"><?php echo htmlspecialchars($username_initials); ?></div>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="nav-section"><div class="nav-title">Main</div><a href="dashboard.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>Dashboard</a></div>
            <div class="nav-section"><div class="nav-title">Profile</div><a href="profile.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Profile</a></div>
            <div class="nav-section"><div class="nav-title">Financial</div><a href="account_info.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/></svg>Account Info</a><a href="add_income.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07-.34-.433.582a2.305 2.305 0 01-.567-.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843-.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>Add Income</a><a href="add_transaction.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/></svg>Add Transaction</a><a href="transaction_history.php" class="nav-item active"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Transaction History</a><a href="budget_suggestions.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>Budget Suggestions</a></div>
            <div class="nav-section"><div class="nav-title">Account</div><a href="logout.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>Logout</a></div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>

    <main class="main-content">
        <div class="history-container">
            <h1 class="page-title">Transaction History</h1>
            <p class="page-subtitle">Review and filter your past transactions.</p>
            
            <form action="transaction_history.php" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="filter_type_select">Filter By</label>
                    <select name="filter_type" id="filter_type_select">
                        <option value="all" <?php if ($filter_type === 'all') echo 'selected'; ?>>All Time</option>
                        <option value="monthly" <?php if ($filter_type === 'monthly') echo 'selected'; ?>>Monthly</option>
                        <option value="yearly" <?php if ($filter_type === 'yearly') echo 'selected'; ?>>Yearly</option>
                    </select>
                </div>
                <div class="filter-group" id="month_filter_group" style="display: none;">
                    <label for="month_select">Select Month</label>
                    <input type="month" name="month" id="month_select" value="<?php echo htmlspecialchars($_GET['month'] ?? date('Y-m')); ?>">
                </div>
                <div class="filter-group" id="year_filter_group" style="display: none;">
                    <label for="year_select">Enter Year</label>
                    <input type="number" name="year" id="year_select" placeholder="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($_GET['year'] ?? date('Y')); ?>" min="1900" max="2100">
                </div>
                <div class="filter-group">
                    <button type="submit">Filter</button>
                </div>
            </form>

            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Description</th><th>Category</th><th>Type</th><th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="5" class="no-transactions">No transactions found for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date("d M, Y", strtotime($tx['transaction_date']))); ?></td>
                                <td><?php echo htmlspecialchars($tx['source'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($tx['category'] ?? 'N/A'); ?></td>
                                <td><span class="type-badge <?php echo htmlspecialchars($tx['transaction_type']); ?>"><?php echo htmlspecialchars($tx['transaction_type']); ?></span></td>
                                <td class="amount <?php echo htmlspecialchars($tx['transaction_type']); ?>" style="text-align: right;">
                                    <?php echo ($tx['transaction_type'] === 'income' ? '+' : '-') . ' $' . number_format($tx['amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
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

        const filterTypeSelect = document.getElementById('filter_type_select');
        const monthFilterGroup = document.getElementById('month_filter_group');
        const yearFilterGroup = document.getElementById('year_filter_group');

        function toggleFilterInputs() {
            const selectedValue = filterTypeSelect.value;
            monthFilterGroup.style.display = selectedValue === 'monthly' ? 'flex' : 'none';
            yearFilterGroup.style.display = selectedValue === 'yearly' ? 'flex' : 'none';
        }
        toggleFilterInputs(); // Run on page load
        filterTypeSelect.addEventListener('change', toggleFilterInputs);
    </script>
</body>
</html>