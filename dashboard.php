<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$name_parts = explode(' ', $fullname);
$first_initial = strtoupper(substr($name_parts[0], 0, 1));
$last_initial = count($name_parts) > 1 ? strtoupper(substr(end($name_parts), 0, 1)) : '';
$username_initials = $first_initial . $last_initial;

// --- DATA FETCHING ---
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

$stmt_balance = $conn->prepare("SELECT SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END) AS total_balance FROM transactions WHERE user_id = ?");
$stmt_balance->bind_param("i", $user_id);
$stmt_balance->execute();
$total_balance = $stmt_balance->get_result()->fetch_assoc()['total_balance'] ?? 0;
$stmt_balance->close();

$stmt_transactions = $conn->prepare("SELECT category, description, amount, transaction_date, transaction_type FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC LIMIT 5");
$stmt_transactions->bind_param("i", $user_id);
$stmt_transactions->execute();
$recent_transactions = $stmt_transactions->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_transactions->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FinTracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --color-background: #1a1c23;
            --color-surface: #22252e;
            --color-text: #e1e1e6;
            --color-text-secondary: #a8a8b3;
            --color-primary: #00b37e;
            --color-primary-hover: #00a071;
            --color-secondary: rgba(138, 138, 147, 0.15);
            --color-border: rgba(138, 138, 147, 0.2);
            --color-error: #f75a68;
            --color-success: #00b37e;
            --font-family-base: "Inter", -apple-system, sans-serif;
            --font-size-sm: 12px; --font-size-base: 14px; --font-size-lg: 16px; --font-size-2xl: 20px; --font-size-3xl: 24px; --font-size-4xl: 30px;
            --font-weight-medium: 500; --font-weight-bold: 600;
            --space-8: 8px; --space-12: 12px; --space-16: 16px; --space-20: 20px; --space-24: 24px; --space-32: 32px;
            --radius-base: 8px; --radius-lg: 12px; --radius-full: 9999px;
            --duration-fast: 150ms;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: var(--font-family-base); background: var(--color-background); color: var(--color-text); margin: 0; line-height: 1.5; -webkit-font-smoothing: antialiased; }
        h1, h2, h3, h4 { margin: 0; font-weight: var(--font-weight-bold); }
        a { color: var(--color-primary); text-decoration: none; }
        p { margin: 0; }
        .container { width: 100%; max-width: 1280px; margin: 0 auto; padding: 0 var(--space-24); }

        /* --- NEW BACKGROUND GRAPHICS --- */
        .background-graphics { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden; }
        .background-graphics::before, .background-graphics::after { content: ''; position: absolute; border-radius: 50%; background: radial-gradient(circle, rgba(0, 179, 126, 0.15), transparent 60%); animation: float 15s infinite ease-in-out; }
        .background-graphics::before { width: 400px; height: 400px; top: 10%; left: 20%; }
        .background-graphics::after { width: 300px; height: 300px; bottom: 5%; right: 15%; animation-duration: 20s; animation-delay: -5s; }
        @keyframes float { 0%, 100% { transform: translateY(0) translateX(0); } 50% { transform: translateY(-30px) translateX(20px); } }

        /* --- CORRECTED HEADER AND SIDEBAR --- */
        .header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-16) var(--space-24); background: var(--color-surface); border-bottom: 1px solid var(--color-border); position: sticky; top: 0; z-index: 1010; }
        .header-left { display: flex; align-items: center; gap: var(--space-16); }
        .logo { font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); }
        .hamburger { display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 1002; width: 24px; height: 24px; }
        .hamburger svg { width: 100%; height: 100%; stroke: var(--color-text); }
        .hidden { display: none !important; }

        .profile-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: var(--color-background); display: flex; align-items: center; justify-content: center; font-weight: var(--font-weight-bold); cursor: pointer; }
        
        .sidebar { position: fixed; top: 0; left: -280px; width: 260px; height: 100%; background: var(--color-surface); border-right: 1px solid var(--color-border); transition: left 0.3s ease-in-out; z-index: 1001; padding-top: 80px; overflow-y: auto; }
        .sidebar.active { left: 0; box-shadow: 0 0 20px rgba(0,0,0,0.2); }
        .sidebar-content { padding: var(--space-24); }
        .nav-section { margin-bottom: var(--space-24); }
        .nav-title { font-size: var(--font-size-sm); color: var(--color-text-secondary); margin-bottom: var(--space-8); text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; gap: var(--space-12); padding: var(--space-12); border-radius: var(--radius-base); color: var(--color-text-secondary); font-weight: var(--font-weight-medium); transition: all var(--duration-fast); }
        .nav-item:hover, .nav-item.active { background: var(--color-secondary); color: var(--color-text); }
        .nav-icon { width: 20px; height: 20px; }
        
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .overlay.active { opacity: 1; pointer-events: auto; }
        
        .main-content { transition: margin-left 0.3s ease-in-out; padding: var(--space-32); }
        
        /* --- CORRECTED SIDEBAR BEHAVIOR --- */
        @media (min-width: 993px) {
            .main-content { margin-left: 260px; }
            .sidebar { left: 0; }
            body.sidebar-closed .main-content { margin-left: 0; }
            body.sidebar-closed .sidebar { left: -280px; }
        }

        /* --- SECTIONS AND DASHBOARD --- */
        .section { display: none; }
        .section.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .section-header { text-align: center; margin-bottom: var(--space-32); }
        .section-header h1 { margin-bottom: var(--space-8); font-size: var(--font-size-4xl); }
        .section-header p { color: var(--color-text-secondary); font-size: var(--font-size-lg); }
        
        .overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-24); margin-bottom: var(--space-32); }
        .overview-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-20); display: flex; align-items: center; gap: var(--space-16); }
        .card-icon { font-size: var(--font-size-3xl); width: 50px; height: 50px; display: flex; flex-shrink: 0; align-items: center; justify-content: center; border-radius: var(--radius-full); background: rgba(119, 124, 124, 0.1); }
        .card-content h3 { margin: 0 0 var(--space-4) 0; color: var(--color-text-secondary); font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); white-space: nowrap;}
        .card-amount { font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); color: var(--color-text); line-height: 1.2; }
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-24); margin-bottom: var(--space-32); }
        .chart-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-24); }
        .chart-card h3 { margin: 0 0 var(--space-20) 0; color: var(--color-text); }
        .recent-transactions { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-24); }
        .recent-transactions h3 { margin: 0 0 var(--space-20) 0; }
        .transactions-list { display: flex; flex-direction: column; gap: var(--space-12); }
        .transaction-item { display: flex; align-items: center; justify-content: space-between; padding: var(--space-12); background: var(--color-background); border-radius: var(--radius-base); border: 1px solid var(--color-border); }
        .transaction-info { display: flex; align-items: center; gap: var(--space-12); }
        .transaction-icon { font-size: var(--font-size-xl); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-full); background: var(--color-secondary); }
        .transaction-details h4 { margin: 0; font-size: var(--font-size-base); color: var(--color-text); }
        .transaction-details p { margin: 0; font-size: var(--font-size-sm); color: var(--color-text-secondary); }
        .transaction-amount { font-weight: var(--font-weight-semibold); }
        .transaction-amount.positive { color: var(--color-success); }
        .transaction-amount.negative { color: var(--color-error); }
        
        @media (max-width: 1200px) { .charts-row { grid-template-columns: 1fr; } }
        @media (max-width: 992px) { .header-left .logo { display: none; } }
    </style>
</head>
<body>
    <div class="background-graphics"></div>
    <header class="header">
        <div class="header-left">
            <div class="hamburger" id="hamburger">
                <svg id="menuIcon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="2.5" stroke-linecap="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
                <svg id="closeIcon" class="hidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
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
                <a href="dashboard.php" class="nav-item active">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>
                    Dashboard
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-title">Profile</div>
                <a href="profile.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                    Profile
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-title">Financial</div>
                <a href="account_info.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/></svg>
                    Account Info
                </a>
                <a href="add_income.php" class="nav-item">
                     <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07-.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
                    Add Income
                </a>
                <a href="add_transaction.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/></svg>
                    Add Transaction
                </a>
                <a href="transaction_history.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Transaction History
                </a>
                <a href="budget_suggestions.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>
                    Budget Suggestions
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-title">Account</div>
                <a href="logout.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
                    Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>

    <main class="main-content" id="mainContent">
        <section id="dashboard" class="section active">
            <div class="container">
                <div class="section-header"><h1>Welcome back, <?php echo htmlspecialchars($fullname); ?>!</h1><p>Here's your financial overview for the current month.</p></div>
                <div class="overview-grid">
                    <div class="overview-card"><div class="card-icon">💰</div><div class="card-content"><h3>Total Balance</h3><p class="card-amount">$<?php echo number_format($total_balance, 2); ?></p></div></div>
                    <div class="overview-card"><div class="card-icon">📈</div><div class="card-content"><h3>Monthly Income</h3><p class="card-amount">$<?php echo number_format($monthly_income, 2); ?></p></div></div>
                    <div class="overview-card"><div class="card-icon">📉</div><div class="card-content"><h3>Monthly Expenses</h3><p class="card-amount">$<?php echo number_format($monthly_expenses, 2); ?></p></div></div>
                    <div class="overview-card"><div class="card-icon">🏦</div><div class="card-content"><h3>Net Savings</h3><p class="card-amount">$<?php echo number_format($monthly_income - $monthly_expenses, 2); ?></p></div></div>
                </div>
                <div class="charts-row">
                    <div class="chart-card"><h3>Income vs Expenses (Last 6 Months)</h3><canvas id="incomeExpenseChart"></canvas></div>
                    <div class="chart-card"><h3>This Month's Expense Categories</h3><canvas id="expenseCategoriesChart"></canvas></div>
                </div>
                <div class="recent-transactions"><h3>Recent Transactions</h3><div class="transactions-list" id="recentTransactions"></div></div>
            </div>
        </section>
        
    </main>

    <script>
        const hamburger = document.getElementById('hamburger');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const body = document.body;
        const mainContent = document.getElementById('mainContent');
        const menuIcon = document.getElementById('menuIcon');
        const closeIcon = document.getElementById('closeIcon');

        function toggleSidebar() {
            const isSidebarOpen = body.classList.contains('sidebar-open');
            
            if (window.innerWidth >= 993) {
                 body.classList.toggle('sidebar-closed');
            } else {
                 body.classList.toggle('sidebar-open');
                 sidebar.classList.toggle('active');
                 overlay.classList.toggle('active');
            }
            
            menuIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        }

        hamburger.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
        
        if (window.innerWidth >= 993) {
            body.classList.remove('sidebar-closed');
            menuIcon.classList.add('hidden');
            closeIcon.classList.remove('hidden');
        } else {
             menuIcon.classList.remove('hidden');
             closeIcon.classList.add('hidden');
        }

        const serverData = {
            monthlyIncome: <?php echo json_encode($monthly_income); ?>,
            monthlyExpenses: <?php echo json_encode($monthly_expenses); ?>,
            recentTransactions: <?php echo json_encode($recent_transactions); ?>
        };

        class FinanceTracker {
            constructor(data) {
                this.data = data;
                this.init();
            }

            init() {
                this.renderRecentTransactions();
                setTimeout(() => this.setupCharts(), 100);
            }

            renderRecentTransactions() {
                const container = document.getElementById('recentTransactions');
                if (!container) return;
                container.innerHTML = this.data.recentTransactions.length === 0 ? '<p>No recent transactions found.</p>' : '';
                
                this.data.recentTransactions.forEach(tx => {
                    const isIncome = tx.transaction_type === 'income';
                    container.innerHTML += `
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <div class="transaction-icon">${isIncome ? '📈' : '📉'}</div>
                                <div class="transaction-details">
                                    <h4>${tx.description || 'N/A'}</h4>
                                    <p>${tx.category} • ${new Date(tx.transaction_date).toLocaleDateString()}</p>
                                </div>
                            </div>
                            <div class="transaction-amount ${isIncome ? 'positive' : 'negative'}">
                                ${isIncome ? '+' : '-'}$${parseFloat(tx.amount).toFixed(2)}
                            </div>
                        </div>`;
                });
            }

            setupCharts() {
                const incomeExpenseCtx = document.getElementById('incomeExpenseChart')?.getContext('2d');
                if (incomeExpenseCtx) {
                    new Chart(incomeExpenseCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
                            datasets: [
                                { label: 'Income', data: [4000, 4100, 4200, 4150, 4300, this.data.monthlyIncome], backgroundColor: 'rgba(0, 179, 126, 0.6)' },
                                { label: 'Expenses', data: [2800, 3200, 2900, 3100, 2850, this.data.monthlyExpenses], backgroundColor: 'rgba(247, 90, 104, 0.6)' }
                            ]
                        }
                    });
                }

                const expenseCategoriesCtx = document.getElementById('expenseCategoriesChart')?.getContext('2d');
                if (expenseCategoriesCtx) {
                    const categoryTotals = {};
                    this.data.recentTransactions.forEach(tx => {
                        if (tx.transaction_type === 'expense') {
                           categoryTotals[tx.category] = (categoryTotals[tx.category] || 0) + parseFloat(tx.amount);
                        }
                    });

                    new Chart(expenseCategoriesCtx, {
                        type: 'pie',
                        data: {
                            labels: Object.keys(categoryTotals).length ? Object.keys(categoryTotals) : ['No Expenses'],
                            datasets: [{
                                data: Object.keys(categoryTotals).length ? Object.values(categoryTotals) : [1],
                                backgroundColor: ['#00b37e', '#f75a68', '#ffc185', '#32b8c6', '#a8a8b3']
                            }]
                        },
                        options: { plugins: { legend: { position: 'right' } } }
                    });
                }
            }
        }
        new FinanceTracker(serverData);
    </script>
</body>
</html>