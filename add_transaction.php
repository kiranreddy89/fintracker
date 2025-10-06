<?php
// Start a session and include the database configuration
session_start();
include 'config.php';

// --- AUTHENTICATION ---
// Redirect to the login page if the user is not logged in.
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// --- FORM PROCESSING ---
$message = '';
$message_type = ''; // 'success' or 'error'

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- DATA RETRIEVAL & VALIDATION ---
    // Get the user_id from the session, not a placeholder.
    $user_id = $_SESSION['user_id']; 

    $transaction_type = $_POST['type'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $transaction_date = $_POST['date'] ?? '';
    $source = trim($_POST['description']) ?? ''; // Mapping form's 'description' to DB 'source'
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['notes']) ?? ''; // Mapping form's 'notes' to DB 'description'
    $payment_method = $_POST['payment_method'] ?? '';
    $tags = trim($_POST['tags']) ?? '';
    $receipt_path = null;

    // Basic validation
    if (empty($transaction_type) || empty($amount) || empty($transaction_date) || empty($source) || empty($category)) {
        $message = "Please fill in all required fields (*).";
        $message_type = 'error';
    } else {
        // --- FILE UPLOAD HANDLING ---
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            
            // Create a unique filename to prevent overwriting existing files
            $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $unique_filename = 'receipt_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $unique_filename;

            // Check file size (max 5MB)
            if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                $message = "Sorry, your file is too large. Maximum size is 5MB.";
                $message_type = 'error';
            } else {
                // Move the file to the uploads directory
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                    $receipt_path = $unique_filename; // Store only the filename
                } else {
                    $message = "Sorry, there was an error uploading your file.";
                    $message_type = 'error';
                }
            }
        }

        // Proceed with database insertion only if there was no validation or upload error
        if ($message_type !== 'error') {
            // --- DATABASE INSERTION (using Prepared Statements to prevent SQL Injection) ---
            $sql = "INSERT INTO transactions (user_id, transaction_type, amount, source, category, description, transaction_date, payment_method, tags, receipt_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            // Bind parameters: i=integer, d=double, s=string
            $stmt->bind_param("isdsssssss", 
                $user_id, 
                $transaction_type, 
                $amount, 
                $source, 
                $category, 
                $description, 
                $transaction_date, 
                $payment_method, 
                $tags, 
                $receipt_path
            );

            if ($stmt->execute()) {
                // Redirect on success to prevent form resubmission
                header("Location: dashboard.php?status=transaction_added");
                exit();
            } else {
                $message = "Error adding transaction: " . $stmt->error;
                $message_type = 'error';
            }

            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - FinTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #1a1a1a 0%, #2d3748 100%); color: #ffffff; min-height: 100vh; }

        #open-sidebar-btn {
            position: fixed; top: 25px; left: 30px; z-index: 999;
            background: none; border: none; color: #fff;
            font-size: 24px; cursor: pointer; transition: opacity 0.3s, transform 0.3s;
        }
        #open-sidebar-btn.hidden { opacity: 0; pointer-events: none; transform: rotate(180deg); }

        .sidebar {
            width: 260px; background-color: #f8f9fa; border-right: 1px solid #dee2e6;
            padding: 20px 0; position: fixed; height: 100vh; z-index: 1000;
            transform: translateX(-100%); transition: transform 0.3s ease-in-out;
        }
        .sidebar.open { transform: translateX(0); }
        .logo { display: flex; align-items: center; justify-content: space-between;
            padding: 0 25px 20px; border-bottom: 1px solid #dee2e6; }
        .logo h2 { font-size: 24px; font-weight: 700; color: #4a00e0; }
        .sidebar-toggle-btn { background: none; border: none; cursor: pointer; font-size: 22px; color: #495057; }
        .nav-section { margin-top: 20px; padding: 0 15px; }
        .nav-title { font-size: 12px; color: #6c757d; text-transform: uppercase;
            font-weight: 600; letter-spacing: 0.5px; padding: 0 10px 10px; }
        .nav-item { display: flex; align-items: center; padding: 12px 10px; color: #495057;
            text-decoration: none; border-radius: 8px; margin-bottom: 5px;
            transition: all 0.2s ease-in-out; font-weight: 500; }
        .nav-icon { width: 20px; margin-right: 15px; text-align: center; opacity: 0.8; }
        .nav-item:hover { background-color: #e9ecef; color: #000; }
        .nav-item.active { background-color: #e8e1ff; color: #4a00e0; font-weight: 700; }
        
        .main-content {
            width: 100%; padding: 30px; display: flex;
            flex-direction: column; align-items: center; justify-content: flex-start;
        }
        .page-header {
            width: 100%; max-width: 800px; text-align: center;
            margin-bottom: 30px; margin-top: 20px;
        }
        .page-title { font-size: 28px; font-weight: 600; margin: 0;
            background: linear-gradient(135deg, #00d4aa, #4facfe);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .page-subtitle { color: #888; font-size: 16px; }
        
        .message { width: 100%; max-width: 800px; padding: 15px;
            margin-bottom: 20px; border-radius: 10px; border: 1px solid; font-weight: 500; }
        .message.success { background-color: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.5); color: #22c55e; }
        .message.error { background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.5); color: #ef4444; }

        .transaction-form {
            width: 100%; max-width: 800px; background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; padding: 30px;
        }

        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; } .form-group.full-width { flex: 1 1 100%; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #ddd; }
        
        input, select, textarea {
            width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 10px; color: #fff; font-size: 14px;
            transition: all 0.3s ease;
        }
        
        select option {
            background: #2d3748; /* Dark background to match theme */
            color: #ffffff;      /* White text */
        }

        input:focus, select:focus, textarea:focus { outline: none; border-color: #00d4aa;
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1); background: rgba(255, 255, 255, 0.12); }
        textarea { resize: vertical; min-height: 80px; }
        .transaction-type { display: flex; gap: 10px; margin-bottom: 20px; }
        .type-option { flex: 1; padding: 16px; background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1); border-radius: 12px; cursor: pointer; 
            text-align: center; transition: all 0.3s ease; position: relative; }
        .type-option input[type="radio"] { display: none; }
        .type-option.income { border-color: rgba(34, 197, 94, 0.3); }
        .type-option.expense { border-color: rgba(239, 68, 68, 0.3); }
        .type-option.income input[type="radio"]:checked ~ .type-content {
             background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.1));
             border: 1px solid rgba(34, 197, 94, 0.5); }
        .type-option.expense input[type="radio"]:checked ~ .type-content {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1));
            border: 1px solid rgba(239, 68, 68, 0.5); }
        .type-content { padding: 10px; border-radius: 8px; transition: all 0.3s ease; border: 1px solid transparent; }
        .type-icon { font-size: 24px; margin-bottom: 8px; }
        .amount-input { position: relative; }
        .currency-symbol { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #888; font-weight: 600; }
        .amount-input input { padding-left: 35px; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; justify-content: flex-end; }
        .btn { padding: 14px 24px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #00d4aa, #4facfe); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 212, 170, 0.3); }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: #ddd; border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.15); }
        .quick-amounts { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .quick-amount { padding: 6px 12px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px; font-size: 12px; cursor: pointer; transition: all 0.3s ease; }
        .quick-amount:hover { background: rgba(0, 212, 170, 0.2); border-color: #00d4aa; }
        .attachment-area { border: 2px dashed rgba(255, 255, 255, 0.3); border-radius: 12px; padding: 20px;
            text-align: center; transition: all 0.3s ease; cursor: pointer; }
        .attachment-area:hover { border-color: #00d4aa; background: rgba(0, 212, 170, 0.05); }
        .attachment-area input[type="file"] { display: none; }
    </style>
</head>
<body>
    <button id="open-sidebar-btn" class="sidebar-toggle-btn"><i class="fas fa-bars"></i></button>
    
    <nav class="sidebar">
        <div class="logo">
            <h2>FinTracker</h2>
            <button id="close-sidebar-btn" class="sidebar-toggle-btn"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="nav-section"><div class="nav-title">Main</div><a href="dashboard.php" class="nav-item"><i class="fas fa-th-large nav-icon"></i>Dashboard</a></div>
        <div class="nav-section"><div class="nav-title">Profile</div><a href="profile.php" class="nav-item"><i class="fas fa-user nav-icon"></i>Profile</a></div>
        <div class="nav-section">
            <div class="nav-title">Financial</div>
            <a href="account_info.php" class="nav-item"><i class="fas fa-wallet nav-icon"></i>Account Info</a>
            <a href="add_income.php" class="nav-item"><i class="fas fa-dollar-sign nav-icon"></i>Add Income</a>
            <a href="add_transaction.php" class="nav-item active"><i class="fas fa-exchange-alt nav-icon"></i>Add Transaction</a>
            <a href="transaction_history.php" class="nav-item"><i class="fas fa-history nav-icon"></i>Transaction History</a>
            <a href="budget_suggestions.php" class="nav-item"><i class="fas fa-lightbulb nav-icon"></i>Budget Suggestions</a>
        </div>
        <div class="nav-section"><div class="nav-title">Account</div><a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt nav-icon"></i>Logout</a></div>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Add Transaction</h1>
                <p class="page-subtitle">Record your income or expense transaction</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form class="transaction-form" method="POST" action="add_transaction.php" enctype="multipart/form-data">
            <div class="transaction-type">
                <label class="type-option income"><input type="radio" name="type" value="income" checked>
                    <div class="type-content"><div class="type-icon">💰</div><div><strong>Income</strong></div><div style="font-size: 12px; color: #888;">Money received</div></div>
                </label>
                <label class="type-option expense"><input type="radio" name="type" value="expense">
                    <div class="type-content"><div class="type-icon">💸</div><div><strong>Expense</strong></div><div style="font-size: 12px; color: #888;">Money spent</div></div>
                </label>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount *</label>
                    <div class="amount-input"><span class="currency-symbol">$</span><input type="number" id="amount" name="amount" step="0.01" required></div>
                    <div class="quick-amounts"><span class="quick-amount" onclick="setAmount(10)">$10</span><span class="quick-amount" onclick="setAmount(20)">$20</span><span class="quick-amount" onclick="setAmount(50)">$50</span><span class="quick-amount" onclick="setAmount(100)">$100</span></div>
                </div>
                <div class="form-group"><label for="date">Date *</label><input type="date" id="date" name="date" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="description">Description * (e.g., Groceries, Salary)</label><input type="text" id="description" name="description" placeholder="Short title for the transaction" required></div>
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <optgroup label="Income Categories"><option value="salary">Salary</option><option value="freelance">Freelance</option><option value="business">Business</option><option value="investment">Investment Returns</option><option value="other-income">Other Income</option></optgroup>
                        <optgroup label="Expense Categories"><option value="food">Food & Dining</option><option value="transportation">Transportation</option><option value="shopping">Shopping</option><option value="entertainment">Entertainment</option><option value="bills">Bills & Utilities</option><option value="healthcare">Healthcare</option><option value="education">Education</option><option value="travel">Travel</option><option value="other-expense">Other Expense</option></optgroup>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="payment-method">Payment Method</label>
                    <select id="payment-method" name="payment_method">
                        <option value="">Select Payment Method</option><option value="cash">Cash</option><option value="debit-card">Debit Card</option><option value="credit-card">Credit Card</option><option value="bank-transfer">Bank Transfer</option><option value="upi">UPI</option><option value="wallet">Digital Wallet</option><option value="cheque">Cheque</option>
                    </select>
                </div>
                <div class="form-group"><label for="tags">Tags</label><input type="text" id="tags" name="tags" placeholder="e.g., personal, work, recurring"></div>
            </div>
            <div class="form-row"><div class="form-group full-width"><label for="notes">Notes</label><textarea id="notes" name="notes" placeholder="Additional notes or details about this transaction..."></textarea></div></div>
            <div class="form-row">
                <div class="form-group full-width">
                    <label>Attachment (Receipt/Invoice)</label>
                    <div class="attachment-area" onclick="document.getElementById('attachment').click()">
                        <input type="file" id="attachment" name="attachment" accept="image/*,.pdf">
                        <div>📎 Click to upload receipt or invoice</div><div style="font-size: 12px; color: #888; margin-top: 5px;">Supports: JPG, PNG, PDF (Max 5MB)</div>
                    </div>
                </div>
            </div>
            <div class="form-actions"><button type="reset" class="btn btn-secondary">Reset Form</button><button type="submit" class="btn btn-primary">Add Transaction</button></div>
        </form>
    </main>

    <script>
        document.getElementById('date').valueAsDate = new Date();
        function setAmount(amount) { document.getElementById('amount').value = amount; }
        document.getElementById('attachment').addEventListener('change', function(e) {
            const file = e.target.files[0]; const attachmentArea = document.querySelector('.attachment-area');
            if (file) { attachmentArea.innerHTML = `<div>📎 ${file.name}</div><div style="font-size: 12px; color: #888; margin-top: 5px;">Click to change file</div>`;
                attachmentArea.style.borderColor = '#00d4aa'; attachmentArea.style.background = 'rgba(0, 212, 170, 0.1)'; }
        });
        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const categorySelect = document.getElementById('category'); const isIncome = this.value === 'income';
                const incomeOptions = categorySelect.querySelector('optgroup[label="Income Categories"]');
                const expenseOptions = categorySelect.querySelector('optgroup[label="Expense Categories"]');
                incomeOptions.style.display = isIncome ? 'block' : 'none';
                expenseOptions.style.display = isIncome ? 'none' : 'block';
                categorySelect.value = '';
            });
        });
        document.querySelector('input[name="type"]:checked').dispatchEvent(new Event('change'));
    </script>
    
    <script>
        const sidebar = document.querySelector('.sidebar');
        const openBtn = document.getElementById('open-sidebar-btn');
        const closeBtn = document.getElementById('close-sidebar-btn');

        openBtn.addEventListener('click', () => {
            sidebar.classList.add('open');
            openBtn.classList.add('hidden'); // Hide hamburger
        });
        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('open');
            openBtn.classList.remove('hidden'); // Show hamburger
        });
    </script>
</body>
</html>