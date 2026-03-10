<?php
/**
 * Just Add Power API Integration
 * 
 * This file serves as a middleware between the frontend JavaScript and the Just Add Power
 * devices' APIs. It primarily handles infrared (IR) remote control commands to
 * transmitters like cable boxes, Apple TVs, and other IR-controlled devices.
 *
 * Working principle:
 * 1. Receives POST requests from the frontend with device URL and action
 * 2. Loads the corresponding IR command from payloads.txt
 * 3. Sends the command to the device's API endpoint
 * 4. Returns a JSON response indicating success or failure
 *
 * @author Seth Morrow
 * @version 1.1
 * @date 2025-05-04
 */

// Enable error reporting for debugging
// This helps identify issues during development and troubleshooting
// Should be disabled in production for security and performance
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response content type to JSON
// This tells the browser that the response should be interpreted as JSON data
// rather than HTML or plain text
header('Content-Type: application/json');

/**
 * Send an API request to a Just Add Power device using cURL
 * 
 * This function encapsulates the cURL operations needed to communicate with
 * Just Add Power devices. It uses a try-finally structure to ensure the cURL
 * resource is always properly closed, even if an exception occurs during execution.
 *
 * @param string $url      The complete URL endpoint of the device API
 * @param string $payload  The data payload to send with the request
 * 
 * @return array Associative array containing response data, HTTP code, and any errors
 */
function sendApiRequest($url, $payload) {
    $ch = curl_init();
    
    try {
        // Configure cURL options for the API request
        
        // Set the URL endpoint to send the request to
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // Return the response as a string instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Set request method to POST
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Set the data to be sent with the request
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        // Set custom headers for the request:
        // - Content-Type: text/plain - The JAP devices expect commands in plain text
        // - User-Agent: JustOS API Tester - Identify our application to the device
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain',
            'User-Agent: JustOS API Tester'
        ]);

        // Execute the cURL request and capture the response
        $response = curl_exec($ch);
        
        // Get the HTTP status code from the response
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Get any error message if the request failed
        $error = curl_error($ch);
        
        // Return a structured array with all the response information
        return [
            'response' => $response,   // The raw response from the API
            'httpCode' => $httpCode,   // HTTP status code
            'error' => $error          // Error message, if any
        ];
    } finally {
        // This ensures curl handle is always closed, even if an exception occurs
        // Properly closing the cURL handle prevents resource leaks
        if ($ch) {
            curl_close($ch);
        }
    }
}

/**
 * Load IR command payloads from a configuration file
 * 
 * This function parses a text file containing IR command codes for various
 * remote control actions. The file format is expected to be:
 * action=payload
 * With one command per line.
 *
 * Example content of payloads.txt:
 * power=sendir,1:1,1,58000,1,1,192,192,48,145,48...
 * channel_up=sendir,1:1,1,58000,1,1,193,192,49...
 *
 * @param string $filename  Path to the payloads configuration file
 * 
 * @return array Associative array mapping action names to their IR command payloads
 */
function loadPayloads($filename) {
    $payloads = [];
    
    // Check if the file exists before attempting to read it
    if (file_exists($filename)) {
        // Read the file contents, ignoring empty lines
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines !== false) {
            foreach ($lines as $line) {
                // Split each line at the first equals sign
                $parts = explode('=', $line, 2);
                
                // Only process valid lines that have both an action and a payload
                if (count($parts) === 2) {
                    // Store in the array with the action as key and payload as value
                    $payloads[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
    }
    
    return $payloads;
}

/**
 * Process incoming API requests
 * 
 * This section handles POST requests from the frontend, validates the input,
 * loads the appropriate payload, and sends the command to the target device.
 */

// Check if this is a POST request
// This API only accepts POST requests for security and RESTful design principles
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate that all required parameters are present
    // The API requires both a device_url (target device) and action (command to send)
    if (!isset($_POST['device_url']) || !isset($_POST['action'])) {
        // Return an error response if required parameters are missing
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    // Sanitize and prepare the device URL
    // Remove any trailing slashes to ensure consistent URL formatting
    $deviceUrl = rtrim($_POST['device_url'], '/');
    
    // Get the requested action (e.g., 'power', 'channel_up', etc.)
    $action = $_POST['action'];
    
    // Load IR command payloads from the configuration file
    // This file contains the mapping between action names and their IR command codes
    $payloads = loadPayloads('payloads.txt');
    
    // Check if the requested action is defined in our payloads
    if (!isset($payloads[$action])) {
        // Return an error if the action is not recognized
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }

    // Construct the full API endpoint URL
    // The CLI endpoint is used to send shell commands to the device
    $url = $deviceUrl . "/cgi-bin/api/command/cli";
    
    // Prepare the payload to send to the device
    // We're wrapping the IR command in a shell command that pipes it to fluxhandlerV2.sh
    // This is specific to Just Add Power's API architecture
    $payload = 'echo "' . $payloads[$action] . '" | ./fluxhandlerV2.sh';
    
    // Send the API request to the device
    $result = sendApiRequest($url, $payload);
    
    // Process the result and return an appropriate response
    if ($result['error'] || $result['httpCode'] >= 400) {
        // If there was an error or the HTTP status code indicates a client/server error
        echo json_encode([
            'error' => $result['error'] ?: "HTTP Error " . $result['httpCode']
        ]);
    } else {
        // If the request was successful
        echo json_encode(['success' => true]);
    }
    
    // End script execution after sending the response
    exit;
}

// If the request method is not POST, return an error response
// This ensures only valid POST requests are processed
echo json_encode(['error' => 'Invalid request method']);
exit;
?>
