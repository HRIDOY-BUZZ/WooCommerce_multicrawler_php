<?php
    function get_context(){
        $options  = array(
            "http" => array(
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.5615.138 Safari/537.36',
                'method' => 'GET',
                'header' => [
                    "Referer: https://google.com", // Change this to match the domain
                    "Connection: keep-alive"
                ],
                "follow_location" => 0,
                "max_redirects" => 0,
                "timeout" => 15
            ), 
            "ssl"=>array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            )
        );
        $context  = stream_context_create($options);
        return $context;
    }

    function getXPathData($url) {
        $html = file_get_contents($url, false, get_context(), 0, 1000000); //@
        echo "\n\t" . strlen($html) . " bytes\n";
        if (strpos($html, '404 Not Found') !== false || strpos($html, 'Page Not Found') !== false) {
            echo "\n\tERROR 404! NOT FOUND...\n";
            return "break";
        }
        if ($html === false) {
            echo "\n\tFailed to fetch $url\n";
            return "break";
        }
        libxml_use_internal_errors(true); // suppress errors
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors(); // clear errors

        $headers = $dom->getElementsByTagName('header');
        while ($headers->length > 0) {
            $header = $headers->item(0);
            $header->parentNode->removeChild($header);
        }
        $footers = $dom->getElementsByTagName('footer');
        while ( $footers && $footers->length > 0) {
            $footer = $footers->item(0);
            $footer->parentNode->removeChild($footer);
        }
        $xpath = new DOMXPath($dom);
        return $xpath;
    }

    function get_counts($url) {
        $url = $url . "/wp-json/wp/v2/product?per_page=1&_fields=id";

        // Suppress warnings and capture them instead
        set_error_handler(function($severity, $message) use (&$error_message) {
            $error_message = $message;
            return true;
        }, E_WARNING);
    
        $response = @file_get_contents($url, false, get_context());

        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $status_code = $matches[1] ?? 0;
        }
    
        restore_error_handler();

        // Handle errors, including potential connection issues
        if ($status_code >= 400 || $response === false) {
            // Check for internet connectivity only if the request failed
            if (!$sock = @fsockopen('www.google.com', 80, $errno, $errstr, 30)) {
                return [0, "No internet connection"];
            }
            fclose($sock);
    
            // If we have internet, proceed with normal error handling
            switch ($status_code) {
                case 400:
                    return [400, "Bad Request: The server cannot process the request due to a client error."];
                case 403:
                    return [403, "Forbidden: You don't have permission to access this resource."];
                case 404:
                    return [404, "Not Found: The requested resource could not be found on the server."];
                default:
                    return [$status_code ?: -1, $error_message ?: "An error occurred while fetching the URL."];
            }
        }

        $total_count = null;

        foreach ($http_response_header as $header) {
            if (preg_match('/^X-WP-Total:\s*(\d+)/i', $header, $matches)) {
                $total_count = $matches[1]; // Get the total count
            }
        }

        return [200, $total_count];
    }

    // function fetch_url($url) {
    //     $error_message = '';
    //     $status_code = 0;
    
    //     // Suppress warnings and capture them instead
    //     set_error_handler(function($severity, $message) use (&$error_message) {
    //         $error_message = $message;
    //         return true;
    //     }, E_WARNING);
    
    //     $response = @file_get_contents($url, false, get_context());
    
    //     // Check for HTTP errors
    //     if (isset($http_response_header[0])) {
    //         preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
    //         $status_code = $matches[1] ?? 0;
    //     }
    
    //     restore_error_handler();
    
    //     // Handle errors, including potential connection issues
    //     if ($status_code >= 400 || $response === false) {
    //         // Check for internet connectivity only if the request failed
    //         if (!$sock = @fsockopen('www.google.com', 80, $errno, $errstr, 30)) {
    //             return [0, "No internet connection"];
    //         }
    //         fclose($sock);
    
    //         // If we have internet, proceed with normal error handling
    //         switch ($status_code) {
    //             case 400:
    //                 return [400, "Bad Request: The server cannot process the request due to a client error."];
    //             case 403:
    //                 return [403, "Forbidden: You don't have permission to access this resource."];
    //             case 404:
    //                 return [404, "Not Found: The requested resource could not be found on the server."];
    //             default:
    //                 return [$status_code ?: -1, $error_message ?: "An error occurred while fetching the URL."];
    //         }
    //     }
    
    //     // If no error, return status and data
    //     if (json_last_error() === JSON_ERROR_NONE) {
    //         return [200, $response];
    //     } else {
    //         return [200, "Successfully fetched, but failed to decode JSON."];
    //     }
    // }

    function fetch_url($url) {
        $error_message = '';
        $status_code = 0;
    
        // Initialize cURL
        $ch = curl_init();
    
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Return the response as a string
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);            // Set a 2-second timeout
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Follow redirects
        curl_setopt($ch, CURLOPT_FAILONERROR, false);    // Do not stop on HTTP error codes
        curl_setopt($ch, CURLOPT_HEADER, false);         // Do not include headers in the output
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 3600);  // Cache DNS for 1 hour
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);  // Use HTTP/2 if available
    
        // Optional: Disable SSL verification for speed (use with caution)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
        // Execute cURL request and fetch response
        $response = curl_exec($ch);
        
        // Get the HTTP status code
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Capture cURL errors
        if (curl_errno($ch)) {
            $error_message = curl_error($ch);
        }
        
        // Close cURL session
        curl_close($ch);
        
        // Handle HTTP errors or cURL failure
        if ($status_code >= 400 || $response === false) {
            if (!dns_get_record('www.google.com', DNS_A)) {
                return [0, "No internet connection"];
            }
            
            // Return appropriate error message based on HTTP status code
            switch ($status_code) {
                case 400:
                    return [400, "Bad Request: The server cannot process the request due to a client error."];
                case 403:
                    return [403, "Forbidden: You don't have permission to access this resource."];
                case 404:
                    return [404, "Not Found: The requested resource could not be found on the server."];
                default:
                    return [$status_code ?: -1, $error_message ?: "An error occurred while fetching the URL."];
            }
        }
    
        // If no error, return status and data
        return [200, $response];
    }
    

    function get_ld_json($url) {
        $response = fetch_url($url);
    
        if ($response[0] >= 400 || $response[0] < 100) {
            return null;
        }
    
        $html = $response[1];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $scriptTags = $xpath->query('//script[@type="application/ld+json"]');
    
        $ldJsonData = null;
        if ($scriptTags->length > 0) {
            foreach ($scriptTags as $scriptTag) {
                $ldJsonContent = $scriptTag->nodeValue;
                $jsonData = json_decode($ldJsonContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (isset($jsonData['@type']) && $jsonData['@type'] === 'Product') {
                        $ldJsonData = $jsonData;
                        break;
                    }
                }
            }
        }
        return $ldJsonData;
    }

    function get_categories($storeUrl, $product_cat) {
        if($product_cat) {
            $url = "https://" . $storeUrl . "/wp-json/wp/v2/product_cat?_fields=name&";
            foreach($product_cat as $cat) {
                $url .= "include[]=" . $cat . "&";
            }
            $url = substr($url, 0, -1);

            $response = fetch_url($url);
            if($response[0] >= 400 || $response[0] < 100) {
                return "";
            }
            $data = json_decode($response[1]);

            $categories = "";
            foreach($data as $item) {
                $categories .= $item->name . ", ";
            }
            $categories = substr($categories, 0, -2);


            return $categories;
        } else {
            return "";
        }
    }

    function get_availability($classList, $bundleStock) {
        if($bundleStock) {
            if($bundleStock == 'instock') return true;
            else return false;
        } else if($classList) {
            if(in_array('instock', (array)$classList)) return true;
            else if(in_array('outofstock', (array)$classList)) return false;
            else return null;
        }
    }

    function get_handle($url) {
        $url = explode("?", $url)[0];
        if($url[strlen($url) - 1] == '/') {
            $url = substr($url, 0, strlen($url) - 1);
        }
        $handle = explode("/", $url)[count(explode("/", $url)) - 1];
        return $handle;
    }

    function is_duplicate($url, $array) {
        if(in_array($url, $array)) {
            return true;
        }
        $handle = get_handle($url);
        foreach ($array as $item) {
            if (strpos($item, $handle) !== false) {
                return true;
            }
        }
        return false;
    }

    function getPrice($price, $compareAtPrice) {
        $salePrice = "";
        $regularPrice = "";
        if($price == null || $price == 0 || $price == "") {
            return false;
        }
        if($compareAtPrice && $compareAtPrice != "") {
            $regularPrice = $compareAtPrice;
            $salePrice = $price;
        } else {
            $regularPrice = $price;
            $salePrice = "";
        }

        if($regularPrice <= 0 || $regularPrice == "") {
            if( $salePrice != "" && $salePrice > 0) {
                $regularPrice = $salePrice;
                $salePrice = "";
            } else {
                return false;
            }
        } else if($regularPrice == $salePrice) {
            $salePrice = "";
        } else if ($salePrice > $regularPrice) {
            $temp = $regularPrice;
            $regularPrice = $salePrice;
            $salePrice = $temp;
        }

        if(is_numeric($regularPrice)) {
            $regularPrice = $regularPrice / 100;
        }
        if(is_numeric($salePrice)) {
            $salePrice = $salePrice / 100;
        }
        return [$regularPrice, $salePrice];
    }

    function formatURL($url) {
        if(strpos($url, 'http') === false) {
            if(strpos($url, '//') === false) {
                $url = 'https://' . $url;
            } else {
                $url = 'https:' . $url;
            }
        }
        return $url;
    }

    function filter_domains($storeUrls) {
        $new_domains = [];
        foreach ($storeUrls as $storeUrl) {
            $storeUrl = trim($storeUrl);
            if(strpos($storeUrl, '=') !== 0) {
                if(strpos($storeUrl, 'http') !== false || strpos($storeUrl, '/') !== false) {
                    $storeUrl = parse_url($storeUrl, PHP_URL_HOST);
                }
                if(!is_duplicate($storeUrl, $new_domains)) {
                    $new_domains[] = $storeUrl;
                }
            }
        }
        return $new_domains;
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