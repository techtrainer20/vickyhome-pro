<?php
// ============================
// VickyHome Pro - Control Center v1.3.0
// ============================

$mediaDir = '/var/www/html/media/storage';
$netIf = 'enp2s0';
$wifiIf = 'wlan0';

// ========= API endpoint: returns JSON =========
if(isset($_GET['api'])){
    $cpu = trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'"));
    $ram = trim(shell_exec("free | awk '/Mem/{printf(\"%.1f\", $3/$2*100)}'"));
    $disk = trim(shell_exec("df --output=pcent / | tail -1 | tr -dc '0-9'"));
    $tempRaw = trim(@file_get_contents("/sys/class/thermal/thermal_zone0/temp"));
    $temp = $tempRaw ? round($tempRaw/1000,1) : null;

    $rx1 = (int)@file_get_contents("/sys/class/net/{$netIf}/statistics/rx_bytes");
    $tx1 = (int)@file_get_contents("/sys/class/net/{$netIf}/statistics/tx_bytes");
    usleep(900000);
    $rx2 = (int)@file_get_contents("/sys/class/net/{$netIf}/statistics/rx_bytes");
    $tx2 = (int)@file_get_contents("/sys/class/net/{$netIf}/statistics/tx_bytes");
    $rx_kb = round(($rx2-$rx1)/1024,1);
    $tx_kb = round(($tx2-$tx1)/1024,1);

    $wifiInfo = null;
    $iw = @shell_exec("iwconfig {$wifiIf} 2>/dev/null");
    if($iw){
        if(preg_match('/ESSID:\"([^\"]+)\"/',$iw,$m)) $ssid = $m[1]; else $ssid = null;
        if(preg_match('/Link Quality=([0-9\/]+)/',$iw,$q)) $quality = $q[1];
        else if(preg_match('/Signal level=([-\w]+)/',$iw,$q2)) $quality = $q2[1];
        else $quality = null;
        $wifiInfo = ['ssid'=>$ssid,'quality'=>$quality];
    }

    echo json_encode([
        'cpu'=>floatval($cpu),
        'ram'=>floatval($ram),
        'disk'=>intval($disk),
        'temp'=>$temp,
        'rx_kb'=>$rx_kb,
        'tx_kb'=>$tx_kb,
        'wifi'=>$wifiInfo
    ]);
    exit;
}

// ========= File upload =========
$uploadMsg = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload']) && !empty($_FILES['file']['name'][0])){
    if(!is_dir($mediaDir)) mkdir($mediaDir,0755,true);
    foreach($_FILES['file']['name'] as $k=>$name){
        $tmp = $_FILES['file']['tmp_name'][$k];
        $target = $mediaDir . '/' . basename($name);
        if(move_uploaded_file($tmp,$target)) $uploadMsg .= "✅ Uploaded: ".htmlspecialchars($name)."<br>";
        else $uploadMsg .= "❌ Failed: ".htmlspecialchars($name)."<br>";
    }
}

// ========= Maintenance =========
$maintMsg = '';
if(isset($_GET['clean'])){ shell_exec("sudo apt clean 2>/dev/null; sudo rm -rf /tmp/* 2>/dev/null"); $maintMsg = '🧹 System cleaned.'; }
if(isset($_GET['reboot'])){ $maintMsg = '🔁 Rebooting...'; shell_exec("sudo reboot &"); }
if(isset($_GET['shutdown'])){ $maintMsg = '💤 Shutting down...'; shell_exec("sudo shutdown now &"); }

function listMediaRoot($dir){
    $out = [];
    if(is_dir($dir)){
        $items = scandir($dir);
        foreach($items as $it){ if($it!='.' && $it!='..') $out[] = $it; }
    }
    return $out;
}
$mediaFiles = listMediaRoot($mediaDir);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>VickyHome Pro — Control Center</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--accent:#00e6b8;--bg:#050913;--card:rgba(255,255,255,0.04);--muted:#9aa6b2;}
body{margin:0;background:linear-gradient(180deg,#04111a 0%, #031019 100%);font-family:Inter, "Segoe UI", Roboto, sans-serif;color:#e8f0f2;}
header{padding:18px;text-align:center;border-bottom:1px solid rgba(0,230,184,0.05);box-shadow:0 6px 30px rgba(0,0,0,0.6);}
h1{color:var(--accent);text-shadow:0 0 12px rgba(0,230,184,0.12);margin:0;font-weight:700;}
.tabs{display:flex;gap:8px;justify-content:center;padding:12px;background:transparent;flex-wrap:wrap}
.tab{padding:10px 16px;border-radius:10px;cursor:pointer;color:#cde;transition:all .18s;border:1px solid transparent}
.tab:hover{transform:translateY(-4px);box-shadow:0 6px 22px rgba(0,230,184,0.06)}
.tab.active{background:rgba(0,230,184,0.07);border-color:rgba(0,230,184,0.12);color:var(--accent)}
.container{max-width:1150px;margin:18px auto;padding:12px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
.card{background:var(--card);padding:18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.6);min-height:90px}
.small{font-size:0.9rem;color:var(--muted)}
.btn{background:var(--accent);color:#001;padding:8px 12px;border-radius:8px;border:none;font-weight:700;cursor:pointer}
.progress{height:12px;background:rgba(255,255,255,0.06);border-radius:8px;overflow:hidden;margin:6px 0}
.fill{height:100%;background:var(--accent);width:20%}
.file-list{max-height:220px;overflow:auto;background:rgba(255,255,255,0.02);padding:8px;border-radius:8px}
.file-item{padding:6px;border-bottom:1px solid rgba(255,255,255,0.02);display:flex;justify-content:space-between;align-items:center;color:#dfe}
.upload-area{border:2px dashed rgba(0,230,184,0.12);padding:14px;border-radius:8px;text-align:center;color:var(--muted)}
.footer{color:#94a3a6;text-align:center;margin:20px 0;font-size:0.9rem}
.kv{font-weight:700;color:#d6fff0}
.small-muted{font-size:0.85rem;color:#a8b6bd}
@media (max-width:600px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header><h1>💎 VickyHome Pro — Control Center</h1></header>

<div class="tabs" id="tabs">
  <div class="tab active" onclick="openTab(0)">🧠 System</div>
  <div class="tab" onclick="openTab(1)">🖼 Media</div>
  <div class="tab" onclick="openTab(2)">🎵 Apps</div>
  <div class="tab" onclick="openTab(3)">⚙️ Updater</div>
</div>

<div class="container">
  <!-- System -->
  <div class="tab-content" id="tab-0" style="display:block">
    <div class="grid">
      <div class="card"><h3>System Info</h3><pre class="small-muted"><?php echo shell_exec('uptime && df -h /'); ?></pre></div>
      <div class="card"><h3>CPU / Memory</h3><canvas id="cpuChart" height="120"></canvas><canvas id="ramChart" height="120"></canvas></div>
    </div>
  </div>

  <!-- Media -->
  <div class="tab-content" id="tab-1" style="display:none">
    <div class="card">
      <h3>Media Manager</h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="file" name="file[]" multiple>
        <button class="btn" type="submit" name="upload">Upload</button>
      </form>
      <div class="file-list">
        <?php
          if(!is_dir($mediaDir)) echo "<div class='small-muted'>No media folder yet.</div>";
          else foreach($mediaFiles as $mf){
            $path = htmlspecialchars($mf);
            echo "<div class='file-item'><span>$path</span><span><a class='btn' href=\"/media/storage/".rawurlencode($mf)."\" target='_blank'>Open</a></span></div>";
          }
        ?>
      </div>
      <?php if($uploadMsg) echo "<div class='small-muted'>$uploadMsg</div>"; ?>
    </div>
  </div>

  <!-- Apps -->
  <div class="tab-content" id="tab-2" style="display:none">
    <div class="grid">
      <div class="card"><h3>📁 File Browser</h3><a class="btn" href="http://192.168.1.51:8080" target="_blank">Open</a></div>
      <div class="card"><h3>📺 Gerbera</h3><a class="btn" href="http://192.168.1.51:49152" target="_blank">Open</a></div>
      <div class="card"><h3>🎵 Navidrome</h3><a class="btn" href="http://192.168.1.51:4533" target="_blank">Open</a></div>
      <div class="card"><h3>🖼 Gallery</h3><a class="btn" href="/mediahub/gallery.php" target="_blank">Open</a></div>
    </div>
  </div>

  <!-- Updater -->
<!-- TAB 3: Updater -->
<!-- TAB 4: Updater -->
<div class="tab-content" id="tab-4" style="display:none;text-align:center;">
  <h3>🪄 Auto-Updater</h3>
  <?php
    // Local version
    $currentVersion = "1.3.1";

    // Path to version info (you can use local or remote)
    $remoteJson = "http://192.168.1.51/media/storage/version.json";
    $latestVersion = $currentVersion;
    $updateZip = "";
    $updateAvailable = false;

    // Try to fetch version info
    $json = @file_get_contents($remoteJson);
    if($json){
      $data = json_decode($json, true);
      if(isset($data['version']) && version_compare($data['version'],$currentVersion,">")){
        $latestVersion = $data['version'];
        $updateZip = $data['update_url'];
        $updateAvailable = true;
      }
    }
  ?>

  <p>Current Version: <b style="color:#00ffcc;">v<?=$currentVersion?></b></p>

  <?php if($updateAvailable): ?>
    <div style="margin:10px auto;background:rgba(0,255,180,0.08);padding:10px;border-radius:10px;color:#0f0;">
      ⚡ New update available → <b>v<?=$latestVersion?></b><br>
      <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
        <input type="hidden" name="update_url" value="<?=$updateZip?>">
        <button class="btn" type="submit" name="update">Update Now</button>
      </form>
    </div>
  <?php else: ?>
    <div style="color:#aaa;margin:12px;">✅ No new updates. You’re on the latest version.</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="text" name="update_url" placeholder="Enter raw update URL (.zip)"
        style="width:60%;padding:8px;border-radius:8px;border:1px solid #0b3;background:#111;color:#e8f0f2;">
      <button class="btn" type="submit" name="update">Manual Update</button>
    </form>
  <?php endif; ?>

  <div id="progressBox" style="display:none;margin:20px auto;width:60%;background:rgba(255,255,255,0.1);border-radius:8px;overflow:hidden;">
    <div id="progressBar" style="height:16px;width:0;background:linear-gradient(90deg,#00ff99,#00b3ff);transition:width 0.4s;"></div>
  </div>

  <div id="updateStatus" style="margin-top:12px;text-align:center;color:#bbb;font-weight:500;"></div>

  <?php
    if(isset($_POST['update'])){
      $url = trim($_POST['update_url']);
      echo "<script>
      const statusBox=document.getElementById('updateStatus');
      const progressBox=document.getElementById('progressBox');
      const progressBar=document.getElementById('progressBar');
      progressBox.style.display='block';
      let progress=0;
      statusBox.innerHTML='⚙️ Preparing update...';
      const simulate=setInterval(()=>{
        progress+=10;
        progressBar.style.width=progress+'%';
        if(progress===30)statusBox.innerHTML='⬇️ Downloading update...';
        if(progress===60)statusBox.innerHTML='📦 Extracting files...';
        if(progress===90)statusBox.innerHTML='🧹 Cleaning temporary files...';
        if(progress>=100){
          clearInterval(simulate);
          progressBar.style.width='100%';
          statusBox.innerHTML='✅ Update Installed Successfully!<br><span style=\"color:#0f0;\">Restarting dashboard...</span>';
          setTimeout(()=>location.reload(),3000);
        }
      },400);
      </script>";

      if(!preg_match('/\.zip$/',$url)){
        echo "<script>
          document.getElementById('updateStatus').innerHTML='⚠️ Invalid URL — must end with .zip';
          document.getElementById('progressBox').style.display='none';
        </script>";
      } else {
        $tmpZip = "/tmp/update_".time().".zip";
        $targetDir = "/var/www/html/";
        @shell_exec("wget -q -O $tmpZip $url");
        if(file_exists($tmpZip)){
          @shell_exec("unzip -o $tmpZip -d $targetDir");
          @unlink($tmpZip);
        } else {
          echo "<script>
            document.getElementById('updateStatus').innerHTML='❌ Failed to download update.';
            document.getElementById('progressBox').style.display='none';
          </script>";
        }
      }
    }
  ?>
</div>

<div class="footer">© 2025 VickyHome · Local Network Only</div>

<!-- Floating Neon Dock -->
<style>
.dock{position:fixed;bottom:15px;left:50%;transform:translateX(-50%);display:flex;gap:28px;background:rgba(0,10,10,0.6);backdrop-filter:blur(12px);border-radius:50px;padding:10px 24px;box-shadow:0 0 25px rgba(0,230,184,0.25);border:1px solid rgba(0,230,184,0.15);z-index:9999;}
.dock a{font-size:28px;text-decoration:none;color:#00e6b8;transition:all 0.25s ease;}
.dock a:hover{transform:translateY(-6px) scale(1.2);text-shadow:0 0 22px rgba(0,230,184,0.9);}
</style>
<div class="dock">
  <a href="#!" title="System" onclick="openTab(0)">🧠</a>
  <a href="#!" title="Media" onclick="openTab(1)">🖼</a>
  <a href="#!" title="Apps" onclick="openTab(2)">🎵</a>
  <a href="#!" title="Updater" onclick="openTab(3)">⚙️</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function openTab(i){
  document.querySelectorAll('.tab').forEach((t,idx)=>t.classList.toggle('active', idx===i));
  document.querySelectorAll('.tab-content').forEach((c,idx)=>c.style.display = (idx===i ? 'block' : 'none'));
}
</script>
</body>
</html>
