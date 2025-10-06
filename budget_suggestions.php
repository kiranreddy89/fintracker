<?php
session_start();
include 'config.php';

// Authenticate user
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

// --- HANDLE ALL FORM SUBMISSIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Handle "Add New Goal" ---
    if (isset($_POST['add_goal'])) {
        $goal_name = trim($_POST['goal_name']);
        $target_amount = $_POST['target_amount'];

        if (!empty($goal_name) && is_numeric($target_amount) && $target_amount > 0) {
            $sql = "INSERT INTO savings_goals (user_id, goal_name, target_amount) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isd", $user_id, $goal_name, $target_amount);
            $stmt->execute();
            $stmt->close();
            header("Location: budget_suggestions.php?status=goal_added");
            exit();
        }
    }

    // --- Handle "Activate Plan" ---
    if (isset($_POST['activate_plan'])) {
        $goal_id = $_POST['goal_id'];
        $plan_type = $_POST['plan_type'];
        $plan_value = $_POST['plan_value'];
        
        $sql = "UPDATE savings_goals SET active_plan_type = ?, active_plan_value = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddi", $plan_type, $plan_value, $goal_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: budget_suggestions.php?status=plan_activated");
        exit();
    }
    
    // --- Handle "Contribute Money" ---
    if (isset($_POST['contribute_money'])) {
        $goal_id = $_POST['goal_id'];
        $goal_name = $_POST['goal_name'];
        $contribution_amount = $_POST['contribution_amount'];

        if (is_numeric($contribution_amount) && $contribution_amount > 0) {
            // Start a transaction to ensure both operations succeed or fail together
            $conn->begin_transaction();
            try {
                // 1. Add money to the savings goal
                $sql_update_goal = "UPDATE savings_goals SET saved_amount = saved_amount + ? WHERE id = ? AND user_id = ?";
                $stmt_update_goal = $conn->prepare($sql_update_goal);
                $stmt_update_goal->bind_param("ddi", $contribution_amount, $goal_id, $user_id);
                $stmt_update_goal->execute();
                $stmt_update_goal->close();

                // 2. Create a corresponding transaction in the main ledger
                $sql_insert_tx = "INSERT INTO transactions (user_id, transaction_type, amount, source, category, description, transaction_date) VALUES (?, 'expense', ?, ?, 'Savings', ?, ?)";
                $stmt_insert_tx = $conn->prepare($sql_insert_tx);
                $source = "Contribution to '" . $goal_name . "'";
                $description = "Saved $" . $contribution_amount . " towards your goal.";
                $tx_date = date('Y-m-d');
                $stmt_insert_tx->bind_param("idsss", $user_id, $contribution_amount, $source, $description, $tx_date);
                $stmt_insert_tx->execute();
                $stmt_insert_tx->close();

                // If both queries were successful, commit the transaction
                $conn->commit();
                header("Location: budget_suggestions.php?status=contribution_success");
                exit();

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback(); // Rollback on error
                header("Location: budget_suggestions.php?status=contribution_error");
                exit();
            }
        }
    }
}


// --- Fetch Data for Suggestions ---
$stmt_balance = $conn->prepare("SELECT SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END) AS total_balance FROM transactions WHERE user_id = ?");
$stmt_balance->bind_param("i", $user_id);
$stmt_balance->execute();
$total_balance = $stmt_balance->get_result()->fetch_assoc()['total_balance'] ?? 0;
$stmt_balance->close();

$current_month = date('Y-m');
$stmt_income = $conn->prepare("SELECT SUM(amount) AS monthly_income FROM transactions WHERE user_id = ? AND transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
$stmt_income->bind_param("is", $user_id, $current_month);
$stmt_income->execute();
$monthly_income = $stmt_income->get_result()->fetch_assoc()['monthly_income'] ?? 0;
$stmt_income->close();

// Fetch goals including the new active plan columns
$stmt_goals = $conn->prepare("SELECT id, goal_name, target_amount, saved_amount, active_plan_type, active_plan_value FROM savings_goals WHERE user_id = ? ORDER BY creation_date DESC");
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$goals = $stmt_goals->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_goals->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Suggestions - FinTracker</title>
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

        .suggestions-container { width: 100%; max-width: 800px; display: flex; flex-direction: column; gap: 30px; margin: 0 auto; }
        .card { background: rgba(34, 37, 46, 0.7); backdrop-filter: blur(10px); border: 1px solid var(--color-border); border-radius: 16px; padding: 30px; }
        .card-title { font-size: 22px; font-weight: 600; margin-bottom: 20px; color: var(--color-text); }
        .goal-card { background: var(--color-background); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--color-border); }
        .goal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .goal-name { font-size: 18px; font-weight: bold; }
        .goal-amount { color: var(--color-text-secondary); }
        .progress-bar { background: var(--color-surface); height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 10px; }
        .progress-fill { height: 100%; background: var(--color-primary); border-radius: 4px; }
        
        /* New Styles for Suggestions & Active Plan */
        .suggestions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;}
        .suggestion-card { background: var(--color-surface); padding: 15px; border-radius: 8px; border: 1px solid var(--color-border); }
        .suggestion-card p { font-size: 14px; color: #aaa; line-height: 1.6; margin-bottom: 12px; }
        .suggestion-card strong { color: var(--color-primary); }
        .activate-btn { width: 100%; padding: 8px; border: none; border-radius: 6px; background: var(--color-primary); color: var(--color-background); font-weight: bold; cursor: pointer; transition: background-color 0.2s;}
        .activate-btn:hover { background-color: #00a071; }
        
        .active-plan-card { background: rgba(0, 179, 126, 0.1); border: 1px solid var(--color-primary); padding: 20px; border-radius: 12px; margin-top: 20px; }
        .active-plan-card h4 { margin: 0 0 10px; font-weight: 600; color: var(--color-primary); }
        .active-plan-card p { margin: 0; color: #ddd; }
        .contribution-form { margin-top: 20px; display: flex; gap: 10px; align-items: center; }
        .contribution-form input { flex: 1; padding: 10px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; color: #fff; }
        .contribution-form button { padding: 10px 15px; border: none; border-radius: 8px; background: var(--color-primary); color: var(--color-background); font-weight: bold; cursor: pointer;}
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #ddd; }
        .form-input { width: 100%; padding: 12px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; color: #fff; }
        .submit-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; background: var(--color-primary); color: var(--color-background); font-weight: bold; cursor: pointer; font-size: 16px; transition: background-color 0.2s; }
        .submit-btn:hover { background-color: #00a071; }
        .no-goals { text-align: center; color: var(--color-text-secondary); padding: 20px 0; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left"><div class="hamburger" id="hamburger"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></div><div class="logo">FinTracker</div></div>
        <div class="header-actions"><div class="profile-avatar" onclick="window.location.href='profile.php'"><?php echo htmlspecialchars($username_initials); ?></div></div>
    </header>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="nav-section"><div class="nav-title">Main</div><a href="dashboard.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>Dashboard</a></div>
            <div class="nav-section"><div class="nav-title">Profile</div><a href="profile.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>Profile</a></div>
            <div class="nav-section"><div class="nav-title">Financial</div><a href="account_info.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/></svg>Account Info</a><a href="add_income.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07-.34-.433.582a2.305 2.305 0 01-.567-.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843-.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>Add Income</a><a href="add_transaction.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/></svg>Add Transaction</a><a href="transaction_history.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Transaction History</a><a href="budget_suggestions.php" class="nav-item active"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg>Budget Suggestions</a></div>
            <div class="nav-section"><div class="nav-title">Account</div><a href="logout.php" class="nav-item"><svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>Logout</a></div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>
    
    <main class="main-content">
        <div class="suggestions-container">
            <div class="card">
                <h2 class="card-title">Your Savings Goals</h2>
                <?php if (empty($goals)): ?>
                    <p class="no-goals">You haven't added any goals yet. Add one below!</p>
                <?php else: ?>
                    <?php foreach ($goals as $goal):
                        $remaining = $goal['target_amount'] - $goal['saved_amount'];
                        $progress_percent = $goal['target_amount'] > 0 ? round(($goal['saved_amount'] / $goal['target_amount']) * 100) : 0;
                    ?>
                        <div class="goal-card">
                            <div class="goal-header">
                                <span class="goal-name"><?php echo htmlspecialchars($goal['goal_name']); ?></span>
                                <span class="goal-amount">$<?php echo number_format($goal['saved_amount'], 2); ?> / $<?php echo number_format($goal['target_amount'], 2); ?></span>
                            </div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $progress_percent; ?>%;"></div></div>

                            <?php if ($remaining > 0): ?>
                                <?php if (!empty($goal['active_plan_type'])): // If a plan is ACTIVE ?>
                                    <div class="active-plan-card">
                                        <h4>Your Active Plan</h4>
                                        <p>
                                            <?php
                                                if ($goal['active_plan_type'] == 'fixed') {
                                                    echo "Save <strong>$".number_format($goal['active_plan_value'], 2)."</strong> per contribution.";
                                                } elseif ($goal['active_plan_type'] == 'reach_in_months') {
                                                    echo "Reach your goal in ".$goal['active_plan_value']." months by saving <strong>$".number_format(ceil($remaining / $goal['active_plan_value']), 2)."</strong> per month.";
                                                } else { // percentage
                                                    echo "Save <strong>".$goal['active_plan_value']."%</strong> of your monthly income per month.";
                                                }
                                            ?>
                                        </p>
                                        <form action="budget_suggestions.php" method="POST" class="contribution-form">
                                            <input type="hidden" name="contribute_money" value="1">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <input type="hidden" name="goal_name" value="<?php echo htmlspecialchars($goal['goal_name']); ?>">
                                            <input type="number" name="contribution_amount" placeholder="Enter amount" step="0.01" required>
                                            <button type="submit">Contribute</button>
                                        </form>
                                    </div>
                                <?php else: // If NO plan is active, show suggestions ?>
                                    <div class="suggestions-grid">
                                        <?php 
                                            // Suggestion 1: Reach in 6 months
                                            $reach_in_6 = ceil($remaining / 6);
                                            echo "<div class='suggestion-card'><p><strong>Steady Plan:</strong> Reach your goal in 6 months by saving approx. <strong>$".$reach_in_6."</strong> per month.</p><form method='POST'><input type='hidden' name='activate_plan' value='1'><input type='hidden' name='goal_id' value='".$goal['id']."'><input type='hidden' name='plan_type' value='reach_in_months'><input type='hidden' name='plan_value' value='6'><button type='submit' class='activate-btn'>Stick to this plan</button></form></div>";
                                            
                                            // Suggestion 2: Save 10% of monthly income
                                            if ($monthly_income > 0) {
                                                $save_10_percent = $monthly_income * 0.10;
                                                echo "<div class='suggestion-card'><p><strong>Income Plan:</strong> Save <strong>10%</strong> of your income (<strong>$".number_format($save_10_percent, 2)."</strong>) each month.</p><form method='POST'><input type='hidden' name='activate_plan' value='1'><input type='hidden' name='goal_id' value='".$goal['id']."'><input type='hidden' name='plan_type' value='percentage'><input type='hidden' name='plan_value' value='10'><button type='submit' class='activate-btn'>Stick to this plan</button></form></div>";
                                            }
                                            
                                            // Suggestion 3: Fixed amount
                                            echo "<div class='suggestion-card'><p><strong>Fixed Plan:</strong> Contribute a fixed amount of <strong>$50.00</strong> whenever you can.</p><form method='POST'><input type='hidden' name='activate_plan' value='1'><input type='hidden' name='goal_id' value='".$goal['id']."'><input type='hidden' name='plan_type' value='fixed'><input type='hidden' name='plan_value' value='50'><button type='submit' class='activate-btn'>Stick to this plan</button></form></div>";
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                 <p class="suggestion" style="margin-top: 15px;">🎉 <strong>Congratulations, you've reached this goal!</strong></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2 class="card-title">Add a New Goal</h2>
                <form action="budget_suggestions.php" method="POST">
                    <input type="hidden" name="add_goal" value="1">
                    <div class="form-group"><label for="goal_name" class="form-label">Goal Name (e.g., New Laptop, Vacation)</label><input type="text" id="goal_name" name="goal_name" class="form-input" required></div>
                    <div class="form-group"><label for="target_amount" class="form-label">Target Amount ($)</label><input type="number" id="target_amount" name="target_amount" class="form-input" step="0.01" required></div>
                    <button type="submit" class="submit-btn">Add Goal</button>
                </form>
            </div>
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
    </script>
</body>
</html>