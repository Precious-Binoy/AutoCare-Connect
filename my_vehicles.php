<?php 
$page_title = 'My Vehicles'; 
require_once 'includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$success_msg = '';
$error_msg = '';

// Handle Add Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $make = sanitizeInput($_POST['make'] ?? '');
    $model = sanitizeInput($_POST['model'] ?? '');
    $year = sanitizeInput($_POST['year'] ?? '');
    $license_plate = sanitizeInput($_POST['license_plate'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? 'sedan');
    
    if (empty($make) || empty($model) || empty($year) || empty($license_plate)) {
        $error_msg = 'Please fill in all required fields.';
    } else {
        // Check if license plate exists
        $checkQuery = "SELECT id FROM vehicles WHERE license_plate = ?";
        $checkResult = executeQuery($checkQuery, [$license_plate], 's');
        
        if ($checkResult && $checkResult->num_rows > 0) {
            $error_msg = 'Vehicle with this license plate already exists.';
        } else {
            $query = "INSERT INTO vehicles (user_id, make, model, year, license_plate, type) VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$user_id, $make, $model, $year, $license_plate, $type];
            $insert = executeQuery($query, $params, 'ississ');
            
            if ($insert) {
                $success_msg = 'Vehicle added successfully!';
            } else {
                $error_msg = 'Error adding vehicle. Please try again.';
            }
        }
    }
}

// Fetch Vehicles
$query = "SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC";
$result = executeQuery($query, [$user_id], 'i');
$vehicles = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Garage - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold">My Garage</h1>
                        <p class="text-muted">Manage your fleet and register new vehicles for service.</p>
                    </div>
                </div>

                <?php if ($success_msg): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $success_msg; ?></span>
                </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $error_msg; ?></span>
                </div>
                <?php endif; ?>

                <div class="flex gap-6 items-start flex-col lg:flex-row-reverse">
                    
                    <!-- Main Fleet Grid -->
                    <div style="flex: 1; width: 100%;">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Your Fleet <span class="text-muted font-normal">(<?php echo count($vehicles); ?> Vehicles)</span></h3>
                        </div>

                        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));">
                            
                            <?php if (count($vehicles) > 0): ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                <div class="card p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex gap-4">
                                            <div style="width: 50px; height: 50px; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                                                <i class="fa-solid fa-car-side text-2xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-lg"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                                <p class="text-muted text-sm"><?php echo htmlspecialchars($vehicle['year']); ?></p>
                                                <span class="badge badge-secondary mt-1"><?php echo htmlspecialchars($vehicle['license_plate']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-4" style="grid-template-columns: 1fr 1fr;">
                                        <div style="background: #F8FAFC; padding: 0.5rem 1rem; border-radius: 6px;">
                                            <div class="text-xs text-muted uppercase font-bold text-[10px]">Type</div>
                                            <div class="font-medium text-sm flex items-center gap-1"><i class="fa-solid fa-car"></i> <?php echo htmlspecialchars(ucfirst($vehicle['type'])); ?></div>
                                        </div>
                                        <div style="background: #F8FAFC; padding: 0.5rem 1rem; border-radius: 6px;">
                                            <div class="text-xs text-muted uppercase font-bold text-[10px]">Active</div>
                                            <div class="font-medium text-sm flex items-center gap-1">
                                                <i class="fa-solid fa-circle text-[8px] <?php echo $vehicle['is_active'] ? 'text-success' : 'text-danger'; ?>"></i> 
                                                <?php echo $vehicle['is_active'] ? 'Yes' : 'No'; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between items-center pt-2 border-t" style="border-top: 1px solid var(--border);">
                                        <span class="text-muted text-sm font-bold">Details</span>
                                        <a href="#" class="text-primary text-sm font-bold flex items-center gap-1">View History <i class="fa-solid fa-arrow-right"></i></a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full card p-8 text-center text-muted">
                                    <i class="fa-solid fa-car text-4xl mb-4 opacity-50"></i>
                                    <p>No vehicles found. Add your first vehicle using the form.</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Register Sidebar -->
                    <div class="card w-full lg:w-[300px] lg:sticky lg:top-[100px]">
                         <div class="flex items-center gap-2 mb-4 text-primary">
                             <i class="fa-solid fa-circle-plus text-xl"></i>
                             <h3 class="font-bold text-lg text-main">Register New Vehicle</h3>
                         </div>
                         
                         <form action="" method="POST" class="flex flex-col gap-4">
                              <input type="hidden" name="add_vehicle" value="1">
                              <div class="flex gap-2">
                                  <div class="form-group mb-0 flex-1">
                                      <label class="form-label text-xs">Make</label>
                                      <input type="text" name="make" class="form-control" placeholder="e.g. Toyota" required>
                                  </div>
                                  <div class="form-group mb-0 flex-1">
                                      <label class="form-label text-xs">Model</label>
                                      <input type="text" name="model" class="form-control" placeholder="e.g. Camry" required>
                                  </div>
                              </div>
                               <div class="flex gap-2">
                                  <div class="form-group mb-0 flex-1">
                                      <label class="form-label text-xs">License Plate</label>
                                      <input type="text" name="license_plate" class="form-control" placeholder="ABC-1234" required>
                                  </div>
                                  <div class="form-group mb-0 w-[80px]">
                                      <label class="form-label text-xs">Year</label>
                                      <input type="number" name="year" class="form-control" placeholder="2024" required>
                                  </div>
                              </div>
                              <div class="form-group mb-0">
                                  <label class="form-label text-xs">Type</label>
                                  <select name="type" class="form-control">
                                      <option value="sedan">Sedan</option>
                                      <option value="suv">SUV</option>
                                      <option value="truck">Truck</option>
                                      <option value="pickup">Pickup</option>
                                      <option value="coupe">Coupe</option>
                                      <option value="hatchback">Hatchback</option>
                                      <option value="van">Van</option>
                                      <option value="other">Other</option>
                                  </select>
                              </div>
                              <button type="submit" class="btn btn-primary w-full mt-2"><i class="fa-solid fa-plus"></i> Add Vehicle</button>
                         </form>
                    </div>

                </div>
            </div>
        </main>
    </div>
</body>
</html>
