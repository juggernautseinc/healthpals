<?php

/**
 * Web interface to list and decrypt HL7 result files encrypted by OpenEMR
 */


require_once(__DIR__ . '/interface/globals.php');

use OpenEMR\Common\Crypto\CryptoGen;

// Configuration - Update this path to your HL7 files directory
$hl7Directory = 'sites/default/documents/temp';

// Handle decrypt request
$decryptedContent = '';
$selectedFile = '';
$error = '';

if (isset($_GET['file']) && !empty($_GET['file'])) {
    $selectedFile = basename($_GET['file']); // Security: prevent directory traversal
    $filePath = $hl7Directory . '/' . $selectedFile;
    
    if (!file_exists($filePath)) {
        $error = "File not found: $selectedFile";
    } else {
        try {
            $encryptedContent = file_get_contents($filePath);
            
            if ($encryptedContent === false) {
                $error = "Could not read file: $selectedFile";
            } else {
                // Check if encryption is enabled
                if (!$GLOBALS['drive_encryption']) {
                    $decryptedContent = $encryptedContent;
                } else {
                    $crypto = new CryptoGen();
                    $decryptedContent = $crypto->decryptStandard($encryptedContent, null, 'database');
                    
                    if ($decryptedContent === false) {
                        $error = "Decryption failed for: $selectedFile";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get list of files
$files = [];
if (is_dir($hl7Directory)) {
    $fileList = scandir($hl7Directory);
    foreach ($fileList as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $fullPath = $hl7Directory . '/' . $file;
        if (is_file($fullPath)) {
            $fileInfo = [
                'name' => $file,
                'size' => filesize($fullPath),
                'modified' => filemtime($fullPath),
                'encrypted' => false
            ];
            
            // Check if encrypted
            $handle = @fopen($fullPath, 'r');
            if ($handle) {
                $firstBytes = fread($handle, 3);
                fclose($handle);
                if (preg_match('/^00[1-6]/', $firstBytes)) {
                    $fileInfo['encrypted'] = true;
                    $fileInfo['version'] = $firstBytes;
                }
            }
            
            $files[] = $fileInfo;
        }
    }
    
    // Sort by modification time, newest first
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HL7 File Decryptor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 0;
            min-height: calc(100vh - 200px);
        }
        
        .file-list {
            border-right: 1px solid #e0e0e0;
            background: #fafafa;
            overflow-y: auto;
            max-height: calc(100vh - 200px);
        }
        
        .file-list-header {
            padding: 15px 20px;
            background: white;
            border-bottom: 2px solid #e0e0e0;
            font-weight: 600;
            color: #333;
        }
        
        .file-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .file-item:hover {
            background: #f0f0f0;
        }
        
        .file-item.active {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
        }
        
        .file-name {
            font-weight: 500;
            color: #2196F3;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .file-meta {
            font-size: 12px;
            color: #666;
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
        
        .encrypted-badge {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .viewer {
            padding: 30px;
            overflow-y: auto;
        }
        
        .viewer-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            font-size: 16px;
            text-align: center;
        }
        
        .viewer-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .viewer-header h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 10px;
            word-break: break-all;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        textarea {
            width: 100%;
            min-height: 500px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            line-height: 1.5;
            background: #fafafa;
        }
        
        .actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
        }
        
        .btn-secondary {
            background: #757575;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #616161;
        }
        
        .no-files {
            padding: 40px;
            text-align: center;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>HL7 File Decryptor</h1>
            <p>Directory: <?php echo htmlspecialchars($hl7Directory); ?> (<?php echo count($files); ?> files)</p>
        </div>
        
        <div class="content">
            <div class="file-list">
                <div class="file-list-header">
                    Files
                </div>
                
                <?php if (empty($files)): ?>
                    <div class="no-files">No files found in directory</div>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <a href="?file=<?php echo urlencode($file['name']); ?>" 
                           class="file-item <?php echo ($selectedFile === $file['name']) ? 'active' : ''; ?>">
                            <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="file-meta">
                                <span><?php echo number_format($file['size']); ?> bytes</span>
                                <span><?php echo date('Y-m-d H:i:s', $file['modified']); ?></span>
                            </div>
                            <?php if ($file['encrypted']): ?>
                                <span class="encrypted-badge">Encrypted v<?php echo htmlspecialchars($file['version']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="viewer">
                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($selectedFile && !$error): ?>
                    <div class="viewer-header">
                        <h2><?php echo htmlspecialchars($selectedFile); ?></h2>
                    </div>
                    
                    <textarea id="hl7Content" readonly><?php echo htmlspecialchars($decryptedContent); ?></textarea>
                    
                    <div class="actions">
                        <button class="btn btn-primary" onclick="copyToClipboard()">Copy to Clipboard</button>
                        <button class="btn btn-secondary" onclick="downloadContent()">Download</button>
                    </div>
                <?php elseif (!$error): ?>
                    <div class="viewer-empty">
                        <div>
                            <svg width="64" height="64" fill="#ccc" viewBox="0 0 24 24" style="margin-bottom: 20px;">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                            </svg>
                            <p>Select a file from the list to view its decrypted content</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard() {
            const textarea = document.getElementById('hl7Content');
            textarea.select();
            document.execCommand('copy');
            alert('Content copied to clipboard!');
        }
        
        function downloadContent() {
            const content = document.getElementById('hl7Content').value;
            const filename = '<?php echo $selectedFile ? preg_replace('/\\.hl7$/i', '_decrypted.hl7', $selectedFile) : 'decrypted.hl7'; ?>';
            
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
