<?php
header('Content-Type: application/json');

// Collect system information
$uptime = shell_exec('uptime -p');
$load = shell_exec('cat /proc/loadavg');
$disk = shell_exec("df -h / | awk 'NR==2 {print $2, $3, $4, $5}'");
$memory = shell_exec("free -m | awk 'NR==2{printf \"%.2f\", $3*100/$2}'");
$cpu = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'");

// Format clean values
$uptime = trim(str_replace('up', '', $uptime));
$load = explode(' ', trim($load))[0];
$disk_info = explode(' ', trim($disk));
$disk_total = $disk_info[0] ?? '0';
$disk_used = $disk_info[1] ?? '0';
$disk_avail = $disk_info[2] ?? '0';
$disk_percent = $disk_info[3] ?? '0%';

// JSON response
echo json_encode([
  "uptime" => $uptime,
  "load" => $load,
  "disk_total" => $disk_total,
  "disk_used" => $disk_used,
  "disk_avail" => $disk_avail,
  "disk_percent" => $disk_percent,
  "memory_percent" => trim($memory),
  "cpu_percent" => trim($cpu)
]);
?>
