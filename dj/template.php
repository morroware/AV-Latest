<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Castle AV Control System</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="script.js"></script>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <div class="logo-container">
                    <img src="logo.png" alt="Castle AV Controls Logo" class="logo" onclick="handleLogoClick(event)" style="cursor: pointer">
                </div>
                <h1>ALL AV Controls</h1>
            </div>

            <div class="header-buttons">
                <a href="http://192.168.8.127" class="button home-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                </a>
                <a href="bulk.php" class="button home-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6zm2 2v4h8V8H6z"/>
                    </svg>
                    Bulk Switcher
                </a>
                <button id="power-all-on" class="button">Power All On</button>
                <button id="power-all-off" class="button">Power All Off</button>
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
        function handleLogoClick(event) {
            if (event.ctrlKey) {
                window.location.href = 'settings.php';
            }
        }
    </script>
</body>
<footer>
    <div id="wled-footer-controls">
        <button class="button power-on">WLED Power On</button>
        <button class="button power-off">WLED Power Off</button>
    </div>
</footer>
</html>
