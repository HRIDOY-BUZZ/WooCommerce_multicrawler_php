<?php
    function curl_single($url, $timeout = 10) {
        $ch = curl_init(); // Initialize cURL session
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // Set timeout
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.5615.138 Safari/537.36');
    
        $response = curl_exec($ch); // Execute the request
        $error = curl_error($ch); // Capture error if any
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
    
        curl_close($ch); // Close the cURL session
    
        // Check for errors or unsuccessful response
        if ($response === false) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $http_code,
                'content' => null,
            ];
        }
    
        return [
            'success' => $http_code >= 200 && $http_code < 300, // Success for 2xx status codes
            'error' => null,
            'http_code' => $http_code,
            'content' => $response,
        ];
    }

    function curl_multi($urls, $timeout = 10) {
        if (!is_array($urls)) {
            $urls = [$urls];
        }
    
        $multiHandle = curl_multi_init(); // Initialize cURL multi handle
        $curlHandles = []; // Array to store individual cURL handles
    
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.5615.138 Safari/537.36');
    
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$url] = $ch; // Store handle with URL as key
        }
    
        // Execute all handles
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);
    
        $responses = [];
        foreach ($curlHandles as $url => $ch) {
            $content = curl_multi_getcontent($ch); // Fetch the content
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
    
            // Populate the response for each URL
            $responses[] = [
                'success' => $http_code >= 200 && $http_code < 300,
                'error' => $error ?: null,
                'http_code' => $http_code,
                'content' => $error ? null : $content,
            ];
    
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch); // Close individual handle
        }
    
        curl_multi_close($multiHandle); // Close the multi handle
    
        // If only one URL was provided, return its response directly
        if (count($responses) === 1) {
            return reset($responses);
        }
    
        return $responses; // Return array of responses for multiple URLs
    }

    function connection_test($host = 'www.google.com', $port = 80, $timeout = 5) {
        // Test DNS resolution first
        if (!dns_get_record($host, DNS_A)) {
            return false; // DNS resolution failed
        }
    
        // Test actual connectivity using a socket
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection) {
            fclose($connection);
            return true; // Internet is accessible
        }
    
        return false; // Connection failed
    }

    function get_contents($url) {
        $response = @curl_single($url);
    
        if (!$response) {
            // Handle case where the response is null
            return connection_test()
                ? [1, "Failed to fetch contents. Please check the URL and try again."]
                : [0, "No internet connection"];
        }
    
        $status_code = $response['http_code'] ?? 0;
    
        if (!$response['success']) {
            if (!connection_test()) {
                return [0, "No internet connection"];
            }
    
            // Handle specific HTTP status codes
            switch ($status_code) {
                case 400:
                    return [400, "Bad Request: The server cannot process the request due to a client error."];
                case 403:
                    return [403, "Forbidden: You don't have permission to access this resource."];
                case 404:
                    return [404, "Not Found: The requested resource could not be found on the server."];
                default:
                    return [$status_code, $response['error'] ?: "An error occurred while fetching the URL."];
            }
        }
    
        // Success
        return [200, $response['content']];
    }

    function get_multi_contents($urls) {
        // Fetch responses using curl_multi
        $responses = curl_multi($urls);
    
        if (!$responses) {
            // Handle case where curl_multi returned null
            return connection_test()
                ? [1, "Failed to fetch contents. Please check the URLs and try again."]
                : [0, "No internet connection"];
        }
    
        $results = [];
        foreach ($urls as $url) {
            if (!isset($responses[$url])) {
                // Handle missing response for a specific URL
                $results[$url] = [1, "Failed to fetch contents for URL: $url"];
                continue;
            }
    
            $response = $responses[$url];
            $status_code = $response['http_code'] ?? 0;
    
            if (!$response['success']) {
                if (!connection_test()) {
                    // Internet connection issue
                    $results[$url] = [0, "No internet connection"];
                } else {
                    // Handle specific HTTP status codes
                    switch ($status_code) {
                        case 400:
                            $results[$url] = [400, "Bad Request: The server cannot process the request due to a client error."];
                            break;
                        case 403:
                            $results[$url] = [403, "Forbidden: You don't have permission to access this resource."];
                            break;
                        case 404:
                            $results[$url] = [404, "Not Found: The requested resource could not be found on the server."];
                            break;
                        default:
                            $results[$url] = [$status_code ?: 1, $response['error'] ?: "An error occurred while fetching $url."];
                            break;
                    }
                }
            } else {
                // Successful response
                $results[$url] = [200, $response['content']];
            }
        }
    
        return $results;
    }    
    
    function saveToJson($filename, $data) {
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    function showTime($time) {
        $hours = floor($time / 3600);
        $minutes = floor(($time % 3600) / 60);
        $seconds = $time % 60;
        
        $readableTime = '';
        if ($hours > 0) {
            $readableTime .= $hours . ' hours ';

            if ($minutes >= 0) {
                $readableTime .= $minutes . ' minutes ';
            }
            if ($seconds >= 0) {
                $readableTime .= $seconds . ' seconds';
            }
        } else if ($minutes > 0) {
            $readableTime .= $minutes . ' minutes ';

            if ($seconds >= 0) {
                $readableTime .= $seconds . ' seconds';
            }
        } else if ($seconds > 0) {
            $readableTime .= $seconds . ' seconds';
        } else {
            $readableTime .= "0 seconds";
        }
        return $readableTime;
    }
?>