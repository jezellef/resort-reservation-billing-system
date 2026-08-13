<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Fetch all pending feedbacks (both public and private)
$stmt_pending = $mysqli->prepare("SELECT id, name, email, message, rating, section FROM feedbacks WHERE status = 'pending'");
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
$pending_count = $result_pending->num_rows;

// Fetch all processed feedbacks (approved or rejected, both public and private)
$stmt_all = $mysqli->prepare("SELECT id, name, email, message, rating, section, status FROM feedbacks WHERE status != 'pending'");
$stmt_all->execute();
$result_all = $stmt_all->get_result();

// Count feedbacks for dashboard summary
$stmt_count = $mysqli->prepare("SELECT COUNT(*) as total FROM feedbacks WHERE status = 'approved'");
$stmt_count->execute();
$total_approved = $stmt_count->get_result()->fetch_assoc()['total'];

$stmt_count = $mysqli->prepare("SELECT COUNT(*) as total FROM feedbacks WHERE status = 'rejected'");
$stmt_count->execute();
$total_rejected = $stmt_count->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <title>Admin - Feedback Management</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/adminstyle.css">

 
    <link rel="stylesheet" href="styles/adminstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #3a58c7;
            --secondary: #6c757d;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --dark: #5a5c69;
            --light: #f8f9fc;
            --border-radius: 8px;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --transition: all 0.3s ease;
        }
        
        .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            position: relative;
            padding-bottom: 10px;
        }
        .dashboard-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 4px;
            width: 330px;
            background: linear-gradient(to right, #3498db, #1abc9c);
        }
        
 
        /* Dashboard Cards */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            transition: var(--transition);
            border-left: 5px solid;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-pending { border-left-color: var(--primary); }
        .card-approved { border-left-color: var(--success); }
        .card-rejected { border-left-color: var(--danger); }
        
        .card h3 {
            color: #4e73df;
            margin-top: 0;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 10px 0;
            color: #5a5c69;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 20px;
            background-color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 600;
            color: var(--secondary);
            position: relative;
            transition: var(--transition);
        }
        
        .tab:hover, .tab.active {
            color: var(--primary);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
        }
        
        .tab i {
            margin-right: 8px;
        }
        
        /* Tab content */
        .tab-content {
            display: none;
            background-color: white;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Tables */
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .feedback-table th, 
        .feedback-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: top;
        }
        
        .feedback-table th {
            background-color: #f8f9fc;
            color: #6e707e;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.1em;
        }
        
        .feedback-table tr:hover {
            background-color: #f8f9fc;
        }
        
        .feedback-table .message-col {
            max-width: 300px;
            white-space: pre-wrap;
        }
        
        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-pending { background-color: #eaecf4; color: #858796; }
        .badge-approved { background-color: #e0f8ee; color: #1cc88a; }
        .badge-rejected { background-color: #fce8e6; color: #e74a3b; }
        .badge-public { background-color: #e8f4fc; color: #36b9cc; }
        .badge-private { background-color: #f8f9fc; color: #5a5c69; }
        
        /* Buttons */
        .button {
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .button i {
            margin-right: 6px;
        }
        
        .button-approve {
            background-color: var(--success);
            color: white;
        }
        
        .button-reject {
            background-color: var(--danger);
            color: white;
        }
        
        .button-delete {
            background-color: var(--secondary);
            color: white;
        }
        
        .button:hover {
            opacity: 0.85;
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        /* Rating Stars */
        .stars {
            color: #f6c23e;
            letter-spacing: 2px;
        }
        
        /* Alerts */
        .alert {
            margin: 0 0 25px;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .alert-success {
            background-color: #edfaf5;
            color: #1cc88a;
            border-left: 4px solid #1cc88a;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(78, 115, 223, 0.5);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .modal-title {
            margin-top: 0;
            color: var(--dark);
            font-size: 1.5rem;
        }
        
        .modal-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .modal-approve .modal-icon { color: var(--success); }
        .modal-reject .modal-icon { color: var(--danger); }
        .modal-delete .modal-icon { color: var(--secondary); }
        
        .modal form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
       /* Responsive - Enhanced for mobile */
        @media (max-width: 1024px) {
            .feedback-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .card {
                padding: 15px;
            }
            
            .tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                flex-wrap: nowrap;
            }
            
            .tab {
                font-size: 14px;
                padding: 10px 15px;
                white-space: nowrap;
            }
            
            .tab-content {
                padding: 15px;
            }
            
            .tab-content h2 {
                font-size: 1.3rem;
            }
            
            /* Let adminstyle.css handle the table responsiveness */
            /* Just add data-label attributes to your tables */
            
            .modal {
                align-items: flex-start;
                padding-top: 50px;
            }
            
            .modal-content {
                width: 90% !important;
                padding: 20px !important;
                margin: 0 auto;
            }
            
            .modal-title {
                font-size: 1.3rem;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
            
            .modal-buttons .button {
                width: 100%;
                margin: 5px 0;
            }
            
            .dashboard-title {
                font-size: 1.5rem !important;
            }
            
            .dashboard-title:after {
                width: 180px !important;
            }
        }
        
        @media screen and (max-width: 480px) {
            .dashboard-title {
                font-size: 1.2rem !important;
            }
            
            .card-value {
                font-size: 1.3rem;
            }
            
            .tab {
                font-size: 12px;
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div style="margin-bottom: 20px;">
    <h2 class="dashboard-title">Feedbacks Dashboard</h2>
</div>
        
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $_SESSION['message']; unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Dashboard Cards -->
    <div class="dashboard">
        <div class="card card-pending">
            <h3>Pending Feedback</h3>
            <div class="card-value"><?= $pending_count ?></div>
            <p>Awaiting review</p>
        </div>
        <div class="card card-approved">
            <h3>Approved</h3>
            <div class="card-value"><?= $total_approved ?></div>
            <p>Published feedback</p>
        </div>
        <div class="card" style="border-left-color: #e74a3b;">
            <h3>All Feedback</h3>
            <div class="card-value"><?= $total_approved + $total_rejected ?></div>
            <p>Total processed</p>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" onclick="openTab('pending')">
            <i class="fas fa-clock"></i> Pending Feedback
            <?php if($pending_count > 0): ?>
                <span class="badge badge-pending"><?= $pending_count ?></span>
            <?php endif; ?>
        </div>
        <div class="tab" onclick="openTab('all-feedback')">
            <i class="fas fa-archive"></i> All Feedback
        </div>
    </div>
    
    <!-- Pending Feedback Tab (both public and private) -->
    <div id="pending" class="tab-content active">
        <h2><i class="fas fa-comments"></i> Pending Feedback</h2>
        <?php if ($pending_count > 0): ?>
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Rating</th>
                    
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $result_pending->data_seek(0);
                while ($feedback = $result_pending->fetch_assoc()): 
                ?>
                    <tr>
                        <td data-label="Name"><?= htmlspecialchars($feedback['name']) ?></td>
                        <td data-label="Email"><?= htmlspecialchars($feedback['email']) ?></td>
                        <td data-label="Message" class="message-col"><?= htmlspecialchars($feedback['message']) ?></td>
                        <td data-label="Rating">
                            <?php if(isset($feedback['rating']) && $feedback['rating'] > 0): ?>
                                <div class="stars"><?= str_repeat('★', $feedback['rating']) ?></div>
                            <?php else: ?>
                                <span class="badge badge-pending">No Rating</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                                <button class="button button-approve" onclick="showModal('approve', <?= $feedback['id'] ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="button button-reject" onclick="showModal('reject', <?= $feedback['id'] ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No pending feedback at this time.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- All Feedback Tab (both public and private, approved and rejected) -->
    <div id="all-feedback" class="tab-content">
        <h2><i class="fas fa-archive"></i> All Feedback</h2>
        <?php if ($result_all->num_rows > 0): ?>
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Rating</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($feedback = $result_all->fetch_assoc()): ?>
                   <tr>
                        <td data-label="Name"><?= htmlspecialchars($feedback['name']) ?></td>
                        <td data-label="Email"><?= htmlspecialchars($feedback['email']) ?></td>
                        <td data-label="Message" class="message-col"><?= htmlspecialchars($feedback['message']) ?></td>
                        <td data-label="Rating">
                            <?php if(isset($feedback['rating']) && $feedback['rating'] > 0): ?>
                                <div class="stars"><?= str_repeat('★', $feedback['rating']) ?></div>
                            <?php else: ?>
                                <span class="badge badge-pending">No Rating</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Type">
                            <span class="badge badge-<?= $feedback['section'] ?>">
                                <?= ucfirst(htmlspecialchars($feedback['section'])) ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <span class="badge badge-<?= $feedback['status'] ?>">
                                <?= ucfirst(htmlspecialchars($feedback['status'])) ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <button class="button button-delete" onclick="showModal('delete', <?= $feedback['id'] ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No processed feedback found.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal -->
    <div class="modal" id="action-modal">
        <div class="modal-content">
            <div id="modal-header">
                <i class="fas fa-check-circle modal-icon" id="modal-icon"></i>
                <h3 class="modal-title" id="modal-title">Confirm Action</h3>
            </div>
            <p id="modal-text">Are you sure you want to take this action?</p>
            <form id="modal-form" method="POST" action="approve_feedback.php">
                <input type="hidden" name="id" id="modal-id">
                <input type="hidden" name="action" id="modal-action">
                <input type="hidden" name="confirm_action" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="button" id="modal-confirm-btn">Confirm</button>
                    <button type="button" class="button button-reject" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Tab functionality
    function openTab(tabId) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Show the selected tab content
        document.getElementById(tabId).classList.add('active');
        
        // Update tab button states
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        
        // Add active class to clicked tab
        event.currentTarget.classList.add('active');
    }
    
    // Modal functionality
    function showModal(action, id) {
        const modal = document.getElementById('action-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalText = document.getElementById('modal-text');
        const modalIcon = document.getElementById('modal-icon');
        const confirmBtn = document.getElementById('modal-confirm-btn');
        const modalHeader = document.getElementById('modal-header');
        
        document.getElementById('modal-id').value = id;
        document.getElementById('modal-action').value = action;
        
        // Configure modal based on action
        if (action === 'approve') {
            modalTitle.innerText = 'Approve Feedback';
            modalText.innerText = 'Are you sure you want to approve this feedback?';
            modalIcon.className = 'fas fa-check-circle modal-icon';
            confirmBtn.className = 'button button-approve';
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
            modalHeader.className = 'modal-approve';
        } else if (action === 'reject') {
            modalTitle.innerText = 'Reject Feedback';
            modalText.innerText = 'Are you sure you want to reject this feedback?';
            modalIcon.className = 'fas fa-times-circle modal-icon';
            confirmBtn.className = 'button button-reject';
            confirmBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
            modalHeader.className = 'modal-reject';
        } else if (action === 'delete') {
            modalTitle.innerText = 'Delete Feedback';
            modalText.innerText = 'Are you sure you want to permanently delete this feedback?';
            modalIcon.className = 'fas fa-trash-alt modal-icon';
            confirmBtn.className = 'button button-delete';
            confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
            modalHeader.className = 'modal-delete';
        }
        
        modal.style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('action-modal').style.display = 'none';
    }
    
    // Close modal if clicked outside
    window.onclick = function(event) {
        const modal = document.getElementById('action-modal');
        if (event.target === modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>