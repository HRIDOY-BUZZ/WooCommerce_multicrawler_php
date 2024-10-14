<?php
function get_context()
    {
        $options  = array(
            "http" => array(
                'user_agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0',
                'method' => 'GET',
            ), 
            "ssl"=>array(
                "verify_peer"           =>  false,
                "verify_peer_name"      =>  false,
                'allow_self_signed'     =>  true,
                'verify_depth'          =>  0,
                'curl_verify_ssl_peer'  =>  false,
                'curl_verify_ssl_host'  =>  false,
            ),
        );
        $context  = stream_context_create($options);
        return $context;
    }

    function getXPathData($url) {
        $html = @file_get_contents($url, false, get_context(), 0, 10000000); //@
        if (strpos($html, '404 Not Found') !== false || strpos($html, 'Page Not Found') !== false) {
            echo "ERROR 404! NOT FOUND...\n";
            return "break";
        }
        if ($html === false) {
            echo "Failed to fetch $url\n";
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

    function get_handle($url) {
        $url = explode("?", $url)[0];
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