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
$username_initials = strtoupper(substr($fullname, 0, 1) . substr(strrchr($fullname, ' '), 1, 1));
$message = '';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['incomeAmount'];
    $source = $_POST['incomeSource'];
    $frequency = $_POST['frequency'];
    $description = $_POST['description'];
    $transaction_date = date('Y-m-d'); // Uses current date, as per previous logic

    // Insert the income transaction into the database
    $sql = "INSERT INTO transactions (user_id, transaction_type, amount, source, description, transaction_date) VALUES (?, 'income', ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idsss", $user_id, $amount, $source, $description, $transaction_date);

    if ($stmt->execute()) {
        $message = "Income added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    // Redirect after successful submission to prevent resubmission on refresh
    if ($message === "Income added successfully!") {
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Income - FinTracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="logo">FinTracker</div>
        <div class="header-actions">
            <div class="profile-avatar" onclick="window.location.href='profile.php'"><?php echo $username_initials; ?></div>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="nav-section">
                <div class="nav-title">Main</div>
                <a href="dashboard.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                    </svg>
                    Dashboard
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-title">Profile</div>
                <a href="profile.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                    </svg>
                    Profile
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Financial</div>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h8zM6 10a2 2 0 114 0 2 2 0 01-4 0z"/>
                    </svg>
                    Account Info
                </a>
                <a href="add_income.php" class="nav-item active">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                    </svg>
                    Add Income
                </a>
                <a href="add_expense.html" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293-7.707a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L4.414 9H17a1 1 0 100-2H4.414l1.879-1.293z" clip-rule="evenodd"/>
                    </svg>
                    Add Transaction
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Transaction History
                </a>
                <a href="#" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/>
                    </svg>
                    Budget Suggestions
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Account</div>
                <a href="logout.php" class="nav-item">
                    <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="overlay" id="overlay"></div>

    <main class="main-content">
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="income-container">
            <div class="page-title">
                <h1 class="title">Add Income</h1>
                <p class="subtitle">Let's add your income source to track your finances better</p>
            </div>

            <div class="user-type-section">
                <h2 class="section-title">What describes you best?</h2>
                <div class="user-type-options">
                    <div class="user-type-card" data-type="employee">
                        <div class="user-type-icon">
                            <svg width="24" height="24" fill="white" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                <path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z"/>
                            </svg>
                        </div>
                        <h3 class="user-type-title">Employee</h3>
                        <p class="user-type-description">I work for a company and receive regular salary/wages</p>
                    </div>
                    
                    <div class="user-type-card" data-type="student">
                        <div class="user-type-icon">
                            <svg width="24" height="24" fill="white" viewBox="0 0 20 20">
                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <h3 class="user-type-title">Student</h3>
                        <p class="user-type-description">I'm a student with income from part-time work, scholarships, or allowances</p>
                    </div>
                </div>
            </div>

            <form class="income-form" id="incomeForm" action="add_income.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="incomeAmount">Income Amount</label>
                    <div class="amount-input">
                        <span class="currency-symbol">$</span>
                        <input type="number" id="incomeAmount" name="incomeAmount" class="form-input" placeholder="0.00" step="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="incomeSource">Income Source</label>
                    <select id="incomeSource" name="incomeSource" class="form-select" required>
                        <option value="">Select income source</option>
                        </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="frequency">Payment Frequency</label>
                    <select id="frequency" name="frequency" class="form-select" required>
                        <option value="">Select frequency</option>
                        <option value="weekly">Weekly</option>
                        <option value="bi-weekly">Bi-weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                        <option value="one-time">One-time</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description (Optional)</label>
                    <input type="text" id="description" name="description" class="form-input" placeholder="Add any additional notes...">
                </div>

                <button type="submit" class="submit-btn">Add Income</button>
            </form>
        </div>
    </main>

    <script>
        const hamburger = document.getElementById('hamburger');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            hamburger.classList.toggle('active');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        hamburger.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        let selectedUserType = null;
        
        // User type selection
        document.querySelectorAll('.user-type-card').forEach(card => {
            card.addEventListener('click', function() {
                selectedUserType = this.dataset.type;
                
                // Remove active class from all cards
                document.querySelectorAll('.user-type-card').forEach(c => c.classList.remove('selected'));
                
                // Add active class to selected card
                this.classList.add('selected');
                
                // Show form with animation
                setTimeout(() => {
                    showIncomeForm();
                }, 300);
                
                // Populate income source options based on user type
                populateIncomeSourceOptions();
            });
        });

        function showIncomeForm() {
            const form = document.getElementById('incomeForm');
            form.classList.add('active');
        }

        function populateIncomeSourceOptions() {
            const incomeSourceSelect = document.getElementById('incomeSource');
            incomeSourceSelect.innerHTML = '<option value="">Select income source</option>';
            
            let options = [];
            
            if (selectedUserType === 'employee') {
                options = [
                    'Salary',
                    'Hourly Wages',
                    'Overtime Pay',
                    'Bonus',
                    'Commission',
                    'Tips',
                    'Freelance Work',
                    'Side Business',
                    'Other Employment Income'
                ];
            } else if (selectedUserType === 'student') {
                options = [
                    'Part-time Job',
                    'Scholarship',
                    'Grant',
                    'Student Allowance',
                    'Parents/Family Support',
                    'Freelance Work',
                    'Tutoring',
                    'Internship Stipend',
                    'Work-Study Program',
                    'Other Student Income'
                ];
            }
            
            options.forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option.toLowerCase().replace(/\s+/g, '_');
                optionElement.textContent = option;
                incomeSourceSelect.appendChild(optionElement);
            });
        }
    </script>
</body>
</html>
