<?php
header('Content-Type: application/json');

$current_version = "v1.3.1"; // your current version
$remote_version_file = "http://192.168.1.51/media/version.json"; // change to your server

// Fetch remote version
$remote_data = @file_get_contents($remote_version_file);
if (!$remote_data) {
    echo json_encode(["status" => "error", "message" => "Cannot fetch version info."]);
    exit;
}

$remote_json = json_decode($remote_data, true);
$latest_version = $remote_json['latest_version'] ?? "";
$update_url = $remote_json['update_url'] ?? "";

if (version_compare(substr($latest_version, 1), substr($current_version, 1), '<=')) {
    echo json_encode(["status" => "ok", "message" => "No new updates. You’re on the latest version.", "version" => $current_version]);
    exit;
}

// Download + Extract
$tmpDir = __DIR__ . "/update_tmp";
if (!file_exists($tmpDir)) mkdir($tmpDir, 0777, true);
$zipPath = $tmpDir . "/update.zip";
file_put_contents($zipPath, file_get_contents($update_url));

$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
    $zip->extractTo(__DIR__);
    $zip->close();
    unlink($zipPath);
    exec("rm -rf " . escapeshellarg($tmpDir));
    echo json_encode(["status" => "success", "message" => "Updated to $latest_version successfully!", "version" => $latest_version]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to extract update."]);
}
?>
