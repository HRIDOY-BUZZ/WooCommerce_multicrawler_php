<?php
    function curl_single($url, $timeout = 20, $includeHeaders = false) {
        $ch = curl_init(); // Initialize cURL session
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // Set timeout
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.5615.138 Safari/537.36');
    
        if ($includeHeaders) {
            curl_setopt($ch, CURLOPT_HEADER, true); // Include headers if requested
        }
    
        $response = curl_exec($ch); // Execute the request
        $error = curl_error($ch); // Capture error if any
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
    
        $headerSize = $includeHeaders ? curl_getinfo($ch, CURLINFO_HEADER_SIZE) : 0;
        curl_close($ch); // Close the cURL session
    
        if ($response === false) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $http_code,
                'content' => null,
            ];
        }
    
        if ($includeHeaders) {
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
    
            return [
                'success' => $http_code >= 200 && $http_code < 300, // Success for 2xx status codes
                'error' => null,
                'http_code' => $http_code,
                'headers' => $headers, // Return headers if requested
                'content' => $body,
            ];
        }
    
        return [
            'success' => $http_code >= 200 && $http_code < 300, // Success for 2xx status codes
            'error' => null,
            'http_code' => $http_code,
            'content' => $response,
        ];
    }

    function curl_multi($urls, $timeout = 20) {
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
        foreach ($responses as $response) {
            $status_code = $response['http_code'] ?? 0;
    
            if (!$response['success']) {
                if (!connection_test()) {
                    // Internet connection issue
                    $results[] = [0, "No internet connection"];
                } else {
                    // Handle specific HTTP status codes
                    switch ($status_code) {
                        case 400:
                            $results[] = [400, "Bad Request: The server cannot process the request due to a client error."];
                            break;
                        case 403:
                            $results[] = [403, "Forbidden: You don't have permission to access this resource."];
                            break;
                        case 404:
                            $results[] = [404, "Not Found: The requested resource could not be found on the server."];
                            break;
                        default:
                            $results[] = [$status_code ?: 1, $response['error'] ?: "An error occurred while fetching the URL."];
                            break;
                    }
                }
            } else {
                // Successful response
                $results[] = [200, $response['content']];
            }
        }
    
        return $results;
    }    
    
    function filter_domains($storeUrls) {
        $new_domains = [];
        foreach ($storeUrls as $storeUrl) {
            $storeUrl = trim($storeUrl);
            if(strpos($storeUrl, '=') !== 0) {
                if(strpos($storeUrl, 'http') !== false || strpos($storeUrl, '/') !== false) {
                    $storeUrl = parse_url($storeUrl, PHP_URL_HOST);
                }
                if(!in_array($storeUrl, $new_domains)) {
                    $new_domains[] = $storeUrl;
                }
            }
        }
        return $new_domains;
    }

    function get_availability($classList, $bundleStock) {
        if($bundleStock) {
            if($bundleStock == 'instock') return "Y";
            else return "N";
        } else if($classList) {
            if(in_array('instock', (array)$classList)) return "Y";
            else if(in_array('outofstock', (array)$classList)) return "N";
            else return null;
        }
    }

    function get_og_image($yoast) {
        if(isset($yoast->og_image)) {
            return $yoast->og_image[0]->url;
        } else {
            return "";
        }
    }

    function getCategories($storeUrl, $cadIds) {
        $all_cats = [];
        $url = "https://" . $storeUrl . "/wp-json/wp/v2/product_cat?_fields=id,name";
        foreach ($cadIds as $id) {
            $url .= "&include[]=" . $id;
        }
        $response = get_contents($url);
        if ($response[0] == 200) {
            $data = json_decode($response[1]);
            foreach ($data as $d) {
                $all_cats[$d->id] = $d->name;
            }
        }
        return $all_cats;
    }

    function getMediaList($storeUrl, $products) {
        $mediaIdList = "";
        $mediaList = [];
        foreach ($products as $product) {
            if ($product['featured_media']) {
                $mediaIdList .= "&include[]=" . $product['featured_media'];
            }
        }
        $url = "https://" . $storeUrl . "/wp-json/wp/v2/media?_fields=id,guid&per_page=100" . $mediaIdList;
        echo $url;
        $response = get_contents($url);
        if ($response[0] == 200) {
            $data = json_decode($response[1]);
            foreach ($data as $d) {
                $mediaList[$d->id] = $d->guid->rendered;
            }
        }
        return $mediaList;
    }

    function getProductCats($allcats, $pcats) {
        $cats = [];
        foreach ($pcats as $pcat) {
            if (isset($allcats[$pcat])) {
                $cats[] = $allcats[$pcat];
            }
        }
        $categories = implode(', ', $cats);
        return $categories;
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