<?php
$file = 'driver_dashboard.php';
$content = file_get_contents($file);

$pattern = '/<div class="grid grid-cols-2 gap-3">[\s\S]*?Follow safety protocols\.\s*<\/p>/';

$replacement = <<<'EOD'
<div class="flex flex-wrap items-center gap-3">
                                                      <!-- Navigate -->
                                                      <?php 
                                                          $nav_url = "https://www.google.com/maps/search/?api=1&query=";
                                                          if (!empty($job['lat']) && !empty($job['lng'])) {
                                                              $nav_url .= $job['lat'] . "," . $job['lng'];
                                                          } else {
                                                              $nav_url .= urlencode($job['address']);
                                                          }
                                                      ?>
                                                      <a href="<?php echo $nav_url; ?>" target="_blank" class="btn btn-primary w-auto px-4 py-2 text-xs font-bold rounded-lg inline-flex items-center justify-center shadow-sm hover:bg-blue-600">
                                                          <i class="fa-solid fa-location-arrow mr-2"></i> Navigate
                                                      </a>

                                                      <!-- Call -->
                                                      <a href="tel:<?php echo htmlspecialchars($job['customer_phone'] ?? ''); ?>" class="btn btn-outline border-gray-200 w-auto px-4 py-2 text-xs font-bold rounded-lg inline-flex items-center justify-center hover:bg-white bg-white text-gray-700">
                                                          <i class="fa-solid fa-phone mr-2"></i> Call 
                                                          <span class="ml-2 font-mono opacity-70 border-l border-gray-200 pl-2"><?php echo htmlspecialchars($job['customer_phone'] ?? 'N/A'); ?></span>
                                                      </a>

                                                      <!-- Action (Start/Complete) -->
                                                      <?php if ($job['status'] === 'scheduled'): ?>
                                                          <form method="POST" class="inline-block">
                                                              <input type="hidden" name="start_mission" value="1">
                                                              <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                              <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                              <button type="submit" class="btn btn-primary w-auto px-4 py-2 text-xs font-bold rounded-lg shadow-sm hover:shadow-md inline-flex items-center">
                                                                  <i class="fa-solid fa-play mr-2"></i> Confirm Start
                                                              </button>
                                                          </form>
                                                      <?php else: ?>
                                                          <button type="button" class="btn btn-success w-auto px-4 py-2 text-xs font-bold rounded-lg shadow-sm hover:shadow-md inline-flex items-center" 
                                                                  onclick="openDriverCompleteModal(<?php echo $job['booking_id']; ?>, <?php echo $job['id']; ?>, '<?php echo $job['type']; ?>')">
                                                              <i class="fa-solid fa-flag-checkered mr-2"></i> Complete Mission
                                                          </button>
                                                      <?php endif; ?>
                                                  </div>
                                                  <p class="text-[10px] text-center text-muted font-bold mt-3 flex items-center justify-center opacity-70">
                                                      <i class="fa-solid fa-shield-halved mr-1.5"></i> Follow safety protocols.
                                                  </p>
EOD;

$newContent = preg_replace($pattern, $replacement, $content, 1, $count);

if ($count > 0 && $newContent) {
    file_put_contents($file, $newContent);
    echo "Patched successfully. Replaced $count block(s).";
} else {
    echo "Regex failed to match. Pattern: " . $pattern;
    // Debug: show context around grid
    $pos = strpos($content, 'grid grid-cols-2');
    if ($pos !== false) {
        echo "\nContext found at pos $pos:\n" . substr($content, $pos - 20, 100);
    }
}
?>
