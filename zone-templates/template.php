<?php
/**
 * Zone Template - User Interface
 *
 * This template renders the AV control interface for this zone.
 * The zone name is automatically detected from the directory name.
 *
 * @version 1.0 (Template)
 */

$zoneName = basename(__DIR__);
$zoneDisplayName = ucwords(str_replace(['_', '-'], ' ', $zoneName));
$settingsPath = "../settings.php?zone=" . urlencode($zoneName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($zoneDisplayName); ?> - Castle AV Controls</title>
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
                    <img src="../logo.png" alt="Castle AV Controls Logo" class="logo"
                         onclick="handleLogoClick(event)" style="cursor: pointer"
                         title="Ctrl+Click for Settings">
                </div>
                <h1><?php echo htmlspecialchars($zoneDisplayName); ?> AV Controls</h1>
            </div>

            <nav class="header-buttons" aria-label="Zone navigation">
                <a href="../index.html" class="button home-button" title="Return to zone selection">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                </a>
                <a href="<?php echo $settingsPath; ?>" class="button" style="background-color: #666;" title="Configure receivers and transmitters">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                    Settings
                </a>
            </nav>
        </header>

        <?php if ($allReceiversUnreachable): ?>
            <div class="global-error"><?php echo ERROR_MESSAGES['global']; ?></div>
        <?php endif; ?>

        <div id="response-message"></div>

        <div class="main-container">
            <!-- AV Controls Section -->
            <section id="av-controls" class="section">
                <div class="receivers-wrapper">
                    <?php echo generateReceiverForms(); ?>
                </div>
            </section>

            <!-- Remote Control Section -->
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

                <div class="remote-container" role="group" aria-label="Remote control buttons">
                    <!-- Power and Guide -->
                    <div class="button-row">
                        <button onclick="sendCommand('power')" title="Toggle power" aria-label="Power">Power</button>
                        <button onclick="sendCommand('guide')" title="Open TV guide" aria-label="Guide">Guide</button>
                    </div>

                    <!-- Navigation Pad -->
                    <div class="navigation-pad" role="group" aria-label="Navigation controls">
                        <button onclick="sendCommand('up')" title="Navigate up" aria-label="Up">&#9650;</button>
                        <div class="nav-row">
                            <button onclick="sendCommand('left')" title="Navigate left" aria-label="Left">&#9664;</button>
                            <button onclick="sendCommand('select')" title="Select/Confirm" aria-label="Select">OK</button>
                            <button onclick="sendCommand('right')" title="Navigate right" aria-label="Right">&#9654;</button>
                        </div>
                        <button onclick="sendCommand('down')" title="Navigate down" aria-label="Down">&#9660;</button>
                    </div>

                    <!-- Channel Up/Down -->
                    <div class="button-row">
                        <button onclick="sendCommand('channel_up')" title="Channel up" aria-label="Channel up">CH +</button>
                        <button onclick="sendCommand('channel_down')" title="Channel down" aria-label="Channel down">CH -</button>
                    </div>

                    <!-- Number Pad -->
                    <div class="number-pad" role="group" aria-label="Number pad">
                        <button onclick="sendCommand('1')" aria-label="1">1</button>
                        <button onclick="sendCommand('2')" aria-label="2">2</button>
                        <button onclick="sendCommand('3')" aria-label="3">3</button>
                        <button onclick="sendCommand('4')" aria-label="4">4</button>
                        <button onclick="sendCommand('5')" aria-label="5">5</button>
                        <button onclick="sendCommand('6')" aria-label="6">6</button>
                        <button onclick="sendCommand('7')" aria-label="7">7</button>
                        <button onclick="sendCommand('8')" aria-label="8">8</button>
                        <button onclick="sendCommand('9')" aria-label="9">9</button>
                        <button onclick="sendCommand('last')" title="Go to last channel" aria-label="Last channel">Last</button>
                        <button onclick="sendCommand('0')" aria-label="0">0</button>
                        <button onclick="sendCommand('exit')" title="Exit current menu" aria-label="Exit">Exit</button>
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

        // Ctrl+Click on logo opens settings
        function handleLogoClick(event) {
            if (event.ctrlKey) {
                window.location.href = '<?php echo $settingsPath; ?>';
            }
        }
    </script>
</body>
<footer>
    <div id="wled-footer-controls" data-zone="<?php echo htmlspecialchars($zoneName); ?>">
        <button class="button power-on">WLED Power On</button>
        <button class="button power-off">WLED Power Off</button>
    </div>
</footer>
</html>
