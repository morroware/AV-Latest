<?php $zoneName = basename(__DIR__); $settingsPath = "../settings.php?zone=" . urlencode($zoneName); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Castle AV Control System</title>
    <link rel="stylesheet" href="../shared/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- LiveCode browser widget compatibility layer -->
    <script src="../livecode-compat.js"></script>
    <script src="../shared/script.js"></script>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <div class="logo-container">
                    <img src="logo.png" alt="Castle AV Controls Logo" class="logo" onclick="handleLogoClick(event)" style="cursor: pointer">
                </div>
                <h1>Outside Music AV Controls</h1><br>
		<h3>Front Concession<h3>
            </div>

            <div class="header-buttons">
                <a href="../index.html" class="button home-button" title="Return to zone selection">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span class="btn-text">Home</span>
                </a>
                <a href="<?php echo $settingsPath; ?>" class="button settings-button" title="Configure this zone's settings">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                    <span class="btn-text">Settings</span>
                </a>
            </div>
        </header>

        <?php if ($allReceiversUnreachable): ?>
            <div class="global-error"><?php echo ERROR_MESSAGES['global']; ?></div>
        <?php endif; ?>
        
        <div id="response-message"></div>
        
        <div class="main-container">
            <section id="av-controls" class="section">
                <div class="receivers-wrapper">
                    <?php echo generateReceiverForms(); ?>
                </div>
            </section>

            <section id="remote-control" class="section">
                <h2>Remote Control</h2>
                
                <div class="remote-selectors">
                    <div id="transmitter-select">
                        Select Transmitter: Loading transmitters...
                    </div>
                    
                    <div id="favorite-channels-select">
                        Favorite Channels: Loading favorites...
                    </div>
                </div>

                <div class="remote-container">
                    <div class="button-row">
                        <button onclick="sendCommand('power')">Power</button>
                        <button onclick="sendCommand('guide')">Guide</button>
                    </div>

                    <div class="navigation-pad">
                        <button onclick="sendCommand('up')">▲</button>
                        <div class="nav-row">
                            <button onclick="sendCommand('left')">◀</button>
                            <button onclick="sendCommand('select')">OK</button>
                            <button onclick="sendCommand('right')">▶</button>
                        </div>
                        <button onclick="sendCommand('down')">▼</button>
                    </div>

                    <div class="button-row">
                        <button onclick="sendCommand('channel_up')">CH +</button>
                        <button onclick="sendCommand('channel_down')">CH -</button>
                    </div>

                    <div class="number-pad">
                        <button onclick="sendCommand('1')">1</button>
                        <button onclick="sendCommand('2')">2</button>
                        <button onclick="sendCommand('3')">3</button>
                        <button onclick="sendCommand('4')">4</button>
                        <button onclick="sendCommand('5')">5</button>
                        <button onclick="sendCommand('6')">6</button>
                        <button onclick="sendCommand('7')">7</button>
                        <button onclick="sendCommand('8')">8</button>
                        <button onclick="sendCommand('9')">9</button>
                        <button onclick="sendCommand('last')">Last</button>
                        <button onclick="sendCommand('0')">0</button>
                        <button onclick="sendCommand('exit')">Exit</button>
                    </div>
                </div>

                <div id="error-message" class="error-message">
                    <strong>Error!</strong> <span id="error-text"></span>
                </div>
            </section>
        </div>
    </div>

    <script>
        // Pass transmitters data to JavaScript for lazy loading
        window.TRANSMITTERS = <?php echo getTransmittersJson(); ?>;

        function handleLogoClick(event) {
            if (event.ctrlKey) {
                window.location.href = '<?php echo $settingsPath; ?>';
            }
        }
    </script>
</body>
</html>
