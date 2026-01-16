<?php 
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDbConnection();
$user_id = getCurrentUserId();
$current_page = 'my_vehicles.php';

$success_msg = '';
$error_msg = '';

// Handle Add Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $make = sanitizeInput($_POST['make']);
    $model = sanitizeInput($_POST['model']);
    $year = intval($_POST['year']);
    $license_plate = sanitizeInput($_POST['license_plate']);
    $color = sanitizeInput($_POST['color']);
    $type = sanitizeInput($_POST['type']);

    // Check if license plate exists
    $checkQuery = "SELECT id FROM vehicles WHERE license_plate = ?";
    $checkRes = executeQuery($checkQuery, [$license_plate], 's');
    
    if ($checkRes->num_rows > 0) {
        $error_msg = "A vehicle with this license plate is already registered.";
    } else {
        $query = "INSERT INTO vehicles (user_id, make, model, year, license_plate, color, type) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if (executeQuery($query, [$user_id, $make, $model, $year, $license_plate, $color, $type], 'isiisss')) {
            $success_msg = "Vehicle added to your garage successfully!";
        } else {
            $error_msg = "Error adding vehicle. Please try again.";
        }
    }
}

// Handle Delete Vehicle
if (isset($_GET['delete'])) {
    $vehicle_id = intval($_GET['delete']);
    // Check if vehicle belongs to user and doesn't have active bookings
    $checkQuery = "SELECT id FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'in_progress', 'ready_for_delivery')";
    $checkResult = executeQuery($checkQuery, [$vehicle_id], 'i');
    
    if ($checkResult->num_rows > 0) {
        $error_msg = "Cannot remove vehicle while it has a service in progress.";
    } else {
        $deleteQuery = "DELETE FROM vehicles WHERE id = ? AND user_id = ?";
        if (executeQuery($deleteQuery, [$vehicle_id, $user_id], 'ii')) {
            $success_msg = "Vehicle successfully removed from your garage.";
        } else {
            $error_msg = "Failed to remove vehicle.";
        }
    }
}

// Fetch all vehicles
$vehiclesQuery = "SELECT * FROM vehicles WHERE user_id = ? ORDER BY id DESC";
$vehiclesRes = executeQuery($vehiclesQuery, [$user_id], 'i');
$vehicles = $vehiclesRes->fetch_all(MYSQLI_ASSOC);

$page_title = 'Garage Management';
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
                <?php if ($success_msg): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-2xl relative mb-6 animate-fade-in flex items-center gap-3">
                        <i class="fa-solid fa-circle-check text-xl"></i>
                        <span class="font-bold"><?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-2xl relative mb-6 animate-fade-in flex items-center gap-3">
                        <i class="fa-solid fa-circle-exclamation text-xl"></i>
                        <span class="font-bold"><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                    <div>
                        <h1 class="text-4xl font-black text-gray-900 tracking-tight">My Garage</h1>
                        <p class="text-muted font-medium text-lg">You have <?php echo count($vehicles); ?> specialized vehicle(s) registered.</p>
                    </div>
                    <button onclick="document.getElementById('addVehicleModal').style.display='flex'" class="btn btn-primary h-16 px-10 rounded-2xl font-black text-lg shadow-2xl shadow-blue-100 hover:scale-105 active:scale-95 transition-all flex items-center gap-3">
                        <i class="fa-solid fa-plus-circle"></i> Register New Car
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php if (empty($vehicles)): ?>
                        <div class="col-span-full card p-24 text-center border-dashed border-2 bg-gray-50/30 flex flex-col items-center justify-center">
                            <div class="w-32 h-32 bg-white rounded-full flex items-center justify-center mb-8 shadow-inner text-gray-200">
                                <i class="fa-solid fa-car-rear text-6xl"></i>
                            </div>
                            <h2 class="text-3xl font-black text-gray-400">Your Garage is Empty</h2>
                            <p class="max-w-sm mx-auto mt-4 text-gray-500 font-medium">Add your vehicles here to enjoy seamless service booking and historical tracking.</p>
                            <button onclick="document.getElementById('addVehicleModal').style.display='flex'" class="mt-8 px-8 py-3 bg-primary text-white rounded-xl font-bold hover:shadow-lg transition-all">Start by Adding a Car</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <div class="card group transition-all duration-500 hover:shadow-[0_20px_50px_-20px_rgba(0,0,0,0.1)] hover:-translate-y-2 relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-10 -mt-10 transition-all duration-500 group-hover:bg-primary/10"></div>
                                
                                <div class="p-8 relative">
                                    <div class="flex justify-between items-start mb-8">
                                        <div class="w-16 h-16 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center text-primary text-3xl transition-all duration-500 group-hover:rotate-6 group-hover:scale-110">
                                            <i class="fa-solid fa-car"></i>
                                        </div>
                                        <div class="flex gap-2">
                                            <a href="?delete=<?php echo $vehicle['id']; ?>" 
                                               class="w-10 h-10 bg-white text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-xl flex items-center justify-center transition-all border border-gray-100"
                                               onclick="return confirm('Note: This will permanently remove the vehicle from your garage records. Continue?')">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-10">
                                        <span class="text-[10px] font-black uppercase text-primary tracking-[0.2em] mb-2 block">Premium <?php echo htmlspecialchars($vehicle['type']); ?></span>
                                        <h3 class="text-3xl font-black text-gray-900 mb-2 leading-none cursor-default group-hover:text-primary transition-colors"><?php echo htmlspecialchars($vehicle['make']); ?></h3>
                                        <p class="text-xl font-bold text-gray-400"><?php echo htmlspecialchars($vehicle['model']); ?> <span class="text-sm font-medium opacity-50 ml-1">(<?php echo $vehicle['year']; ?>)</span></p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-10">
                                        <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100">
                                            <div class="text-[9px] font-black uppercase text-muted tracking-widest mb-1.5">Reg Number</div>
                                            <div class="font-mono font-black text-gray-700 text-sm"><?php echo htmlspecialchars($vehicle['license_plate']); ?></div>
                                        </div>
                                        <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100">
                                            <div class="text-[9px] font-black uppercase text-muted tracking-widest mb-1.5">Finish</div>
                                            <div class="font-bold text-gray-700 text-sm flex items-center gap-2">
                                                <div class="w-3 h-3 rounded-full shadow-sm border border-white" style="background-color: <?php echo htmlspecialchars($vehicle['color'] ?? ''); ?>;"></div>
                                                <?php echo htmlspecialchars($vehicle['color'] ?? ''); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-4">
                                        <a href="book_service.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-primary flex-1 h-14 text-sm font-black uppercase tracking-widest rounded-2xl shadow-xl shadow-blue-100/50 flex items-center justify-center gap-3">
                                            Book Care
                                        </a>
                                        <a href="history.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="w-14 h-14 bg-white text-gray-400 hover:text-primary hover:border-primary rounded-2xl flex items-center justify-center border border-gray-100 transition-all font-bold group-hover:shadow-md" title="Maintenance Archive">
                                            <i class="fa-solid fa-receipt text-xl"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Register Vehicle Modal -->
    <div id="addVehicleModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(12px); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
        <div style="background: white; border-radius: 2.5rem; max-width: 650px; width: 100%; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.3); overflow: hidden; animation: modalEnter 0.5s cubic-bezier(0.16, 1, 0.3, 1);">
            <div class="p-12">
                <div class="flex justify-between items-center mb-10">
                    <div>
                        <h2 class="text-4xl font-black text-gray-900 tracking-tight">Add New Car</h2>
                        <p class="text-muted font-medium text-lg">Tell us about your machine to provide the best care.</p>
                    </div>
                    <button onclick="document.getElementById('addVehicleModal').style.display='none'" class="w-14 h-14 rounded-2xl bg-gray-50 text-gray-400 hover:text-gray-900 flex items-center justify-center transition-all border border-gray-100">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <input type="hidden" name="add_vehicle" value="1">
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Brand / Manufacturer</label>
                        <input type="text" name="make" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all" required placeholder="e.g. BMW">
                    </div>
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Model Series</label>
                        <input type="text" name="model" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all" required placeholder="e.g. M3 Competiton">
                    </div>
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Build Year</label>
                        <input type="number" name="year" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all" required placeholder="2021" min="1950" max="<?php echo date('Y') + 1; ?>">
                    </div>
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Plate Number</label>
                        <input type="text" name="license_plate" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all uppercase" required placeholder="KL-XX-XX-XXXX">
                    </div>
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Color Aesthetic</label>
                        <input type="text" name="color" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all" required placeholder="e.g. Alpine White">
                    </div>
                    
                    <div class="form-group flex flex-col gap-2">
                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Body Configuration</label>
                        <div class="relative">
                            <select name="type" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white focus:ring-4 focus:ring-primary/5 transition-all appearance-none cursor-pointer" required>
                                <option value="Sedan">Sedan</option>
                                <option value="SUV">SUV</option>
                                <option value="Hatchback">Hatchback</option>
                                <option value="Coupe">Coupe</option>
                                <option value="Supercar">Supercar</option>
                                <option value="Pickup">Pickup Truck</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none"></i>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 pt-8">
                        <button type="submit" class="btn btn-primary w-full h-18 text-xl font-black rounded-3xl shadow-2xl shadow-blue-100 flex items-center justify-center gap-4 transition-all active:scale-95">
                            Register Into Garage <i class="fa-solid fa-arrow-right-long opacity-40"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        @keyframes modalEnter {
            from { opacity: 0; transform: scale(0.9) translateY(40px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .h-18 { height: 4.5rem; }
    </style>

    <script>
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('addVehicleModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
