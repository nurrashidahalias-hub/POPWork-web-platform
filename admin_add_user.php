<?php
session_start();
include("backend/db_connect.php");

// 🔒 Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.html");
    exit();
}

// Handle Search
$search = $_GET['search'] ?? '';
$where_clause = "";
if ($search) {
    $search = $conn->real_escape_string($search);
    $where_clause = "WHERE u.email LIKE '%$search%' OR p.name LIKE '%$search%'";
}

// Fetch Users (Join users table with profiles table)
$sql = "SELECT u.user_id, u.email, u.role, u.created_at, p.name, p.phone 
        FROM users u 
        LEFT JOIN profiles p ON u.user_id = p.user_id 
        $where_clause
        ORDER BY u.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse Dashboard Styles */
        :root { --pop-maroon: #8a1538; --pop-light: #fdf2f4; --pop-gray: #f8f9fa; }
        body { font-family: 'Poppins', sans-serif; background: var(--pop-gray); display: flex; }
        
        /* Sidebar (Simplified for brevity - matches dashboard) */
        .sidebar { width: 260px; background: #8a1538; color: white; min-height: 100vh; padding: 20px; position: fixed; }
        .sidebar h2 { margin-bottom: 30px; }
        .menu-item { display: block; padding: 12px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: white; }
        .menu-item i { margin-right: 10px; width: 20px; }

        /* Main Content */
        .main-content { margin-left: 260px; padding: 30px; width: 100%; }
        
        /* Header Section */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .add-btn { background: var(--pop-maroon); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .add-btn:hover { background: #6d1029; }

        /* Table Styles */
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #666; font-weight: 600; font-size: 14px; }
        
        /* Badges */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge.admin { background: #fee2e2; color: #991b1b; } /* Red for Admin */
        .badge.worker { background: #e0f2fe; color: #075985; }
        .badge.employer { background: #dcfce7; color: #166534; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 100%; max-width: 500px; padding: 30px; border-radius: 12px; position: relative; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-modal { position: absolute; top: 20px; right: 20px; cursor: pointer; font-size: 20px; color: #999; }
        
        /* Form Styles */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .submit-btn { width: 100%; background: var(--pop-maroon); color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-weight: 500; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>POP!Work</h2>
        <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="admin_users.php" class="menu-item active"><i class="fas fa-users"></i> User Management</a>
        </div>

    <div class="main-content">
        <div class="page-header">
            <h1>User Management</h1>
            <button class="add-btn" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div style="padding: 10px; background: #dcfce7; color: #166534; border-radius: 6px; margin-bottom: 20px;">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Joined Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['name'] ?? 'No Profile') ?></strong></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <span class="badge <?= $row['role'] ?>">
                                <?= ucfirst($row['role']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                        <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="#" style="color: #666;"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 style="margin-bottom: 20px;">Add New User</h2>
            
            <form action="backend/admin_add_user.php" method="POST">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. John Doe">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="john@example.com">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Set temporary password">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="worker">Worker</option>
                        <option value="employer">Employer</option>
                        <option value="admin" style="color: red; font-weight: bold;">⚠ Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone (Optional)</label>
                    <input type="text" name="phone" placeholder="012-3456789">
                </div>

                <button type="submit" class="submit-btn">Create Account</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addUserModal');
        
        function openModal() {
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close if clicked outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>