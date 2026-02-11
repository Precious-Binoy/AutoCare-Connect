<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

$page_title = 'Leave Management';
$current_page = 'admin_leave_management.php';

// Fetch all leave requests with user names
$leavesQuery = "SELECT lr.*, u.name as user_name, u.role as user_role 
                FROM leave_requests lr 
                JOIN users u ON lr.user_id = u.id 
                ORDER BY lr.created_at DESC";
$leavesResult = executeQuery($leavesQuery);
$leaveRequests = [];
if ($leavesResult) {
    while ($row = $leavesResult->fetch_assoc()) {
        $leaveRequests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-black text-gray-900">Personnel Leave Management</h1>
                        <p class="text-muted font-medium">Review and process leave requests from mechanics and drivers.</p>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden shadow-xl border-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50/80 text-xs font-black uppercase text-muted tracking-widest border-b border-gray-100">
                                <tr>
                                    <th class="p-6">Applicant</th>
                                    <th class="p-6">Role</th>
                                    <th class="p-6">Leave Type</th>
                                    <th class="p-6">Duration</th>
                                    <th class="p-6">Reason</th>
                                    <th class="p-6">Status</th>
                                    <th class="p-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($leaveRequests)): ?>
                                    <tr>
                                        <td colspan="7" class="p-24 text-center">
                                            <div class="flex flex-col items-center opacity-20">
                                                <i class="fa-solid fa-calendar-check text-7xl mb-4"></i>
                                                <h4 class="text-xl font-black">No requests found</h4>
                                                <p class="text-sm">All leave requests will appear here for review.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaveRequests as $lr): ?>
                                        <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all">
                                            <td class="p-6">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center font-black text-xs shadow-lg shadow-blue-500/20">
                                                        <?php echo strtoupper(substr($lr['user_name'], 0, 1)); ?>
                                                    </div>
                                                    <span class="font-bold text-gray-900"><?php echo htmlspecialchars($lr['user_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="p-6">
                                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider <?php echo $lr['user_role'] === 'mechanic' ? 'bg-orange-50 text-orange-600 border border-orange-100' : 'bg-blue-50 text-blue-600 border border-blue-100'; ?>">
                                                    <?php echo $lr['user_role']; ?>
                                                </span>
                                            </td>
                                            <td class="p-6">
                                                <div class="font-bold text-gray-700 capitalize"><?php echo $lr['leave_type']; ?></div>
                                            </td>
                                            <td class="p-6">
                                                <div class="font-black text-gray-900 text-sm"><?php echo date('M d', strtotime($lr['start_date'])); ?> - <?php echo date('M d', strtotime($lr['end_date'])); ?></div>
                                                <div class="text-xs text-gray-400 font-bold uppercase tracking-tighter mt-0.5">
                                                    <?php 
                                                        $diff = date_diff(date_create($lr['start_date']), date_create($lr['end_date']));
                                                        echo ($diff->days + 1) . ' Day(s)';
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="p-6">
                                                <div class="max-w-xs truncate font-medium text-gray-600" title="<?php echo htmlspecialchars($lr['reason']); ?>">
                                                    <?php echo htmlspecialchars($lr['reason']); ?>
                                                </div>
                                            </td>
                                            <td class="p-6">
                                                <span class="badge <?php 
                                                    echo $lr['status'] === 'approved' ? 'badge-success' : ($lr['status'] === 'rejected' ? 'badge-danger' : 'badge-warning'); 
                                                ?> px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                                    <?php echo $lr['status']; ?>
                                                </span>
                                            </td>
                                            <td class="p-6 text-right">
                                                <?php if ($lr['status'] === 'pending'): ?>
                                                    <div class="flex justify-end gap-2 text-sm italic text-muted">
                                                        <button onclick="openReviewModal(<?php echo $lr['id']; ?>, 'approved')" class="btn btn-success btn-xs px-4 py-2 rounded-lg font-black text-xs uppercase tracking-widest shadow-lg shadow-green-500/10 hover:scale-105 transition-all">
                                                            <i class="fa-solid fa-check"></i> Approve
                                                        </button>
                                                        <button onclick="openReviewModal(<?php echo $lr['id']; ?>, 'rejected')" class="btn btn-danger btn-xs px-4 py-2 rounded-lg font-black text-xs uppercase tracking-widest shadow-lg shadow-red-500/10 hover:scale-105 transition-all">
                                                            <i class="fa-solid fa-xmark"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-xs font-black text-gray-300 uppercase italic">Decision Logged</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(12px); z-index: 10000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: 2rem; max-width: 450px; width: 100%; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.4); overflow: hidden; animation: modalEnter 0.5s cubic-bezier(0.16, 1, 0.3, 1);">
            <div id="modalHeader" style="padding: 25px 30px; color: white; position: relative;">
                <h2 id="modalTitle" style="margin: 0; font-size: 1.4rem; font-weight: 900; letter-spacing: -0.025em;">Leave Request Action</h2>
                <p style="margin: 4px 0 0; font-size: 0.85rem; font-weight: 500; opacity: 0.9;">Add a comment to finalize your decision.</p>
            </div>
            <div style="padding: 30px;">
                <form id="reviewForm" class="flex flex-col gap-6">
                    <input type="hidden" name="action" value="admin_update">
                    <input type="hidden" name="leave_id" id="modal_leave_id">
                    <input type="hidden" name="status" id="modal_status">
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Admin Comment (Optional)</label>
                        <textarea name="admin_comment" class="form-control p-4 font-bold rounded-xl h-32 resize-none bg-gray-50 border-2 border-gray-100 focus:bg-white focus:border-primary transition-all outline-none" placeholder="Provide feedback or reason for this decision..."></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <button type="button" onclick="closeModal()" class="h-12 border-2 border-gray-100 text-gray-500 rounded-xl font-bold hover:bg-gray-50 transition-all">Cancel</button>
                        <button type="submit" id="submitBtn" class="h-12 text-white rounded-xl font-black uppercase tracking-widest text-xs shadow-xl transition-all active:scale-95">Complete Decision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        @keyframes modalEnter {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>

    <script>
        const modal = document.getElementById('reviewModal');
        const header = document.getElementById('modalHeader');
        const submitBtn = document.getElementById('submitBtn');

        function openReviewModal(leaveId, status) {
            document.getElementById('modal_leave_id').value = leaveId;
            document.getElementById('modal_status').value = status;
            
            if (status === 'approved') {
                header.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                submitBtn.style.background = '#10b981';
                submitBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Approve Request';
            } else {
                header.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                submitBtn.style.background = '#ef4444';
                submitBtn.innerHTML = '<i class="fa-solid fa-xmark mr-2"></i> Reject Request';
            }
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        document.getElementById('reviewForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('api/leave_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error processing leave request:', error);
                alert('An error occurred. Please try again.');
            }
        });

        window.onclick = function(event) {
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>
