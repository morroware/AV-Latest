<?php
/**
 * Configuration Files Editor
 * This file provides a web interface for managing additional configuration files
 * including transmitters.txt, favorites.ini, and WLEDlist.ini
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the config.php file exists
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Define editable files and their descriptions
$editableFiles = [
    'transmitters.txt' => [
        'title' => 'IR Transmitters',
        'description' => 'List of transmitter devices with IR blasters and their URLs. Format: Name, URL (one per line)',
        'format' => 'csv',
        'sample' => "Cable Box 1, http://192.168.8.30\nCable Box 2, http://192.168.8.18\nCable Box 3, http://192.168.8.19"
    ],
    'favorites.ini' => [
        'title' => 'Favorite Channels',
        'description' => 'List of favorite channel numbers and their names. Format: Number=Name (one per line)',
        'format' => 'ini',
        'sample' => "; Sports Networks\n35=ESPN\n36=ESPN2\n\n; Local Stations\n11=WPIX\n4=NBC"
    ],
    'WLEDlist.ini' => [
        'title' => 'WLED Devices',
        'description' => 'List of WLED device IP addresses for lighting control. Format: INI file with [WLEDs] section',
        'format' => 'ini',
        'sample' => "[WLEDs]\nip1 = \"192.168.6.13\"\nip2 = \"192.168.6.223\""
    ],
    'payloads.txt' => [
        'title' => 'IR Commands',
        'description' => 'List of infrared command codes for remote control. Format: command=code (one per line)',
        'format' => 'ini',
        'sample' => "power=sendir,1:1,1,58000,1,1,192,192,48,145,48...\nchannel_up=sendir,1:1,1,58000,1,1,193,192,49..."
    ]
];

// Process file selection or save request
$selectedFile = isset($_GET['file']) ? $_GET['file'] : null;
$message = null;

// Check if the selected file is in our allowed list
if ($selectedFile && !array_key_exists($selectedFile, $editableFiles)) {
    $message = ['type' => 'error', 'text' => 'Invalid file selection'];
    $selectedFile = null;
}

// Handle file save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && isset($_POST['content']) && isset($_POST['file'])) {
    $fileToSave = $_POST['file'];
    
    // Validate that the file is in our allowed list
    if (!array_key_exists($fileToSave, $editableFiles)) {
        $message = ['type' => 'error', 'text' => 'Invalid file selection for saving'];
    } else {
        $filePath = __DIR__ . '/' . $fileToSave;
        
        // Check if file exists and is writable
        if (!is_writable($filePath) && file_exists($filePath)) {
            $message = ['type' => 'error', 'text' => "File {$fileToSave} is not writable. Please check permissions."];
        } else {
            // Create backup
            if (file_exists($filePath)) {
                $backupFile = __DIR__ . '/' . pathinfo($fileToSave, PATHINFO_FILENAME) . '_backup_' . date('Y-m-d_H-i-s') . '.' . pathinfo($fileToSave, PATHINFO_EXTENSION);
                if (!copy($filePath, $backupFile)) {
                    $message = ['type' => 'warning', 'text' => "Could not create backup of {$fileToSave} but proceeding with save."];
                }
            }
            
            // Save the file
            if (file_put_contents($filePath, $_POST['content']) !== false) {
                $message = ['type' => 'success', 'text' => "File {$fileToSave} saved successfully."];
            } else {
                $message = ['type' => 'error', 'text' => "Failed to save file {$fileToSave}."];
            }
        }
    }
    
    // Keep the same file selected after save
    $selectedFile = $fileToSave;
}

// Load file content if a file is selected
$fileContent = '';
if ($selectedFile) {
    $filePath = __DIR__ . '/' . $selectedFile;
    if (file_exists($filePath)) {
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $message = ['type' => 'error', 'text' => "Failed to read file {$selectedFile}."];
            $fileContent = '';
        }
    } else {
        // File doesn't exist, use sample content
        $fileContent = $editableFiles[$selectedFile]['sample'];
        $message = ['type' => 'info', 'text' => "File {$selectedFile} doesn't exist yet. Using sample content that will be created when you save."];
    }
}

// Function to check file permissions
function getFileDetails($filename) {
    $filePath = __DIR__ . '/' . $filename;
    $exists = file_exists($filePath);
    
    if (!$exists) {
        return [
            'exists' => false,
            'writable' => true, // Assume directory is writable
            'size' => 0,
            'modified' => 'N/A'
        ];
    }
    
    return [
        'exists' => true,
        'writable' => is_writable($filePath),
        'size' => filesize($filePath),
        'modified' => date('Y-m-d H:i:s', filemtime($filePath))
    ];
}

// Get file details for display
$fileDetails = [];
foreach ($editableFiles as $filename => $info) {
    $fileDetails[$filename] = getFileDetails($filename);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Files Editor</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .config-section {
            background: var(--surface-color);
            padding: 2.5rem;
            border-radius: 8px;
            margin-bottom: 2.5rem;
        }
        
        .editor-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .file-select {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .file-select a {
            padding: 0.875rem 1.75rem;
            background-color: var(--secondary-color);
            color: var(--bg-color);
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .file-select a:hover {
            opacity: var(--button-hover-opacity);
            transform: translateY(-1px);
        }
        
        .file-select a.active {
            background-color: var(--primary-color);
            color: var(--bg-color);
            font-weight: 600;
        }
        
        .editor-box {
            background-color: var(--surface-color);
            padding: 2.5rem;
            border-radius: 8px;
        }
        
        .editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .editor-description {
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            line-height: 1.6;
        }
        
        textarea {
            width: 100%;
            min-height: 400px;
            padding: 1rem;
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--primary-color);
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            resize: vertical;
        }
        
        .file-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
        }
        
        .save-button {
            background-color: var(--secondary-color);
            color: var(--bg-color);
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .save-button:hover {
            opacity: var(--button-hover-opacity);
            transform: translateY(-1px);
        }
        
        .cancel-button {
            display: inline-block;
            background-color: var(--error-color);
            color: white;
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .cancel-button:hover {
            opacity: var(--button-hover-opacity);
            transform: translateY(-1px);
        }
        
        .file-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .file-status-item {
            background-color: var(--surface-color);
            padding: 1.25rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .file-status-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }
        
        .file-status-title {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.2em;
        }
        
        .file-status-details {
            font-size: 0.95em;
            line-height: 1.6;
        }
        
        .file-status-details p {
            margin: 0.5rem 0;
        }
        
        .status-exists {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .status-not-exists {
            color: var(--warning-color);
            font-weight: 500;
        }
        
        .status-writable {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .status-not-writable {
            color: var(--error-color);
            font-weight: 500;
        }
        
        .backup-info {
            margin-top: 1.5rem;
            padding: 1.25rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .file-select {
                flex-direction: column;
            }
            
            .file-select a {
                text-align: center;
            }
            
            .config-section {
                padding: 1.5rem;
            }
            
            .editor-box {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <h1>Configuration Files Editor</h1>
            </div>
            <div class="header-buttons">
                <a href="index.php" class="button home-button">Control Panel</a>
                <a href="settings.php" class="button home-button">Main Settings</a>
            </div>
        </header>

        <?php if (isset($message)): ?>
            <div class="message <?php echo $message['type']; ?>">
                <?php echo $message['text']; ?>
            </div>
        <?php endif; ?>

        <!-- File Status Section -->
        <div class="config-section">
            <h2>Configuration Files Status</h2>
            <div class="file-status">
                <?php foreach ($editableFiles as $filename => $info): ?>
                <div class="file-status-item">
                    <h3 class="file-status-title"><?php echo htmlspecialchars($info['title']); ?></h3>
                    <div class="file-status-details">
                        <p>
                            Status: 
                            <?php if ($fileDetails[$filename]['exists']): ?>
                                <span class="status-exists">Exists</span>
                            <?php else: ?>
                                <span class="status-not-exists">Not Created Yet</span>
                            <?php endif; ?>
                        </p>
                        <p>
                            Permissions: 
                            <?php if ($fileDetails[$filename]['writable']): ?>
                                <span class="status-writable">Writable</span>
                            <?php else: ?>
                                <span class="status-not-writable">Not Writable</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($fileDetails[$filename]['exists']): ?>
                        <p>Size: <?php echo number_format($fileDetails[$filename]['size']); ?> bytes</p>
                        <p>Last Modified: <?php echo $fileDetails[$filename]['modified']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="config-section">
            <h2>Select Configuration File</h2>
            <div class="file-select">
                <?php foreach ($editableFiles as $filename => $info): ?>
                <a href="?file=<?php echo urlencode($filename); ?>" 
                   class="<?php echo $selectedFile === $filename ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($info['title']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($selectedFile): ?>
        <div class="config-section">
            <div class="editor-header">
                <h2>Editing: <?php echo htmlspecialchars($editableFiles[$selectedFile]['title']); ?></h2>
            </div>
            
            <div class="editor-description">
                <?php echo htmlspecialchars($editableFiles[$selectedFile]['description']); ?>
            </div>
            
            <form method="post" action="">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($selectedFile); ?>">
                <textarea name="content" id="file-editor"><?php echo htmlspecialchars($fileContent); ?></textarea>
                
                <div class="file-actions">
                    <button type="submit" name="save" class="save-button">Save Changes</button>
                    <a href="file_editor.php" class="cancel-button">Cancel</a>
                </div>
                
                <div class="backup-info">
                    Note: A backup of the current file will be automatically created before saving any changes.
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="config-section" style="text-align: center;">
            <h2>Select a configuration file to edit</h2>
            <p>Choose from the options above to edit a specific configuration file.</p>
        </div>
        <?php endif; ?>
        </div>
    </div>

    <script>
        // Add confirmation before leaving page with unsaved changes
        let originalContent = document.getElementById('file-editor')?.value;
        
        window.addEventListener('beforeunload', function(e) {
            const currentContent = document.getElementById('file-editor')?.value;
            
            if (currentContent && originalContent && currentContent !== originalContent) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        // Add tab support in the textarea
        const textarea = document.getElementById('file-editor');
        if (textarea) {
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    
                    this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 4;
                }
            });
        }
    </script>
</body>
</html>
