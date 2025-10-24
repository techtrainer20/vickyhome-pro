<!-- TAB 3: MEDIA -->
<div class="tab-content" id="tab-3">
  <div class="card">
    <h2>📂 Media Manager</h2>
    <p>Upload and view media stored in <code><?= htmlspecialchars($mediaDir) ?></code></p>

    <!-- Upload Form -->
    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
      <input type="file" name="mediaFiles[]" multiple required
        style="background:#0d1a1f;border:1px solid #00ffc8;border-radius:6px;color:#00ffc8;padding:8px;">
      <button type="submit" name="upload" style="background:#00ffc8;color:#000;border:none;padding:8px 18px;border-radius:6px;font-weight:bold;cursor:pointer;">⬆️ Upload</button>
    </form>

    <?php
    // Handle uploads
    if (isset($_POST['upload']) && !empty($_FILES['mediaFiles']['name'][0])) {
        $uploadDir = $mediaDir . '/';
        foreach ($_FILES['mediaFiles']['tmp_name'] as $index => $tmpPath) {
            $fileName = basename($_FILES['mediaFiles']['name'][$index]);
            $target = $uploadDir . $fileName;
            if (move_uploaded_file($tmpPath, $target)) {
                echo "<p style='color:#00ffc8;'>✅ Uploaded: $fileName</p>";
            } else {
                echo "<p style='color:#f88;'>❌ Failed: $fileName</p>";
            }
        }
    }

    // Display files
    if (is_dir($mediaDir)) {
        $files = array_diff(scandir($mediaDir), ['.', '..']);
        if (count($files) === 0) {
            echo "<p style='color:#aaa;'>No media files found.</p>";
        } else {
            echo "<div style='display:flex;flex-wrap:wrap;justify-content:center;gap:15px;margin-top:20px;'>";
            foreach ($files as $file) {
                $path = "$mediaDir/$file";
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $url = "/media/" . rawurlencode($file);
                echo "<div style='width:180px;background:#111b21;border:1px solid #00ffc8;border-radius:10px;padding:10px;'>";
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    echo "<img src='$url' style='width:100%;border-radius:6px;margin-bottom:6px;'>";
                } elseif (in_array($ext, ['mp4', 'webm', 'mov'])) {
                    echo "<video src='$url' controls style='width:100%;border-radius:6px;margin-bottom:6px;'></video>";
                } else {
                    echo "<div style='font-size:40px;'>📄</div>";
                }
                echo "<p style='font-size:0.9em;color:#00ffc8;'>$file</p>
                      <a href='$url' target='_blank' style='color:#00ffc8;text-decoration:none;'>Open</a></div>";
            }
            echo "</div>";
        }
    } else {
        echo "<p style='color:#f88;'>❌ Media directory not found: $mediaDir</p>";
    }
    ?>
  </div>
</div>
