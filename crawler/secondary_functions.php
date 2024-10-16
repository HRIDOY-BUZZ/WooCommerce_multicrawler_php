<?php
    function getShopList() {
        if (!file_exists(__DIR__.'/../shops.txt')) {
            echo "shops.txt not found.\n";
            return false;
        }
        $storeUrls = file(__DIR__.'/../shops.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if(empty($storeUrls)) {
            echo "shops.txt is empty.\n";
            return false;
        }
        $storeUrls = filter_domains($storeUrls);
        return $storeUrls;
    }

    function fetchProductUrls($count, $i, $storeUrl) {
        echo "$i of $count.\tFetching products from [" . constyle(strtoupper($storeUrl), 33) . "]\n\n";
        $wpdata = "/wp-json/wp/v2/product?per_page=100&_fields=link&page=";
        $wpJsonUrl = 'https://' . $storeUrl . $wpdata;
        // echo "Browsing " . constyle($collectionUrl, 33) . "\n\n";
        $productUrls = [];
        $page = 1;
        $dots = "";
        do {
            $dots = $dots . ".";
            $url = $wpJsonUrl . $page;
            $response = fetch_json_url($url);

            if ($response[0] == 400) {
                break;
            } else if($response[0] > 400 || $response[0] < 100) {
                echo "\n\t" . constyle("ERROR " . $response[0] . ": " . $response[1], 91) . "\n\n";
                break;
            }
            $links = $response[1];

            $i = 0;
            foreach ($links as $link) {
                $purl = $link->link;

                if(!is_duplicate($purl, $productUrls)) {
                    $productUrls[] = $purl;
                    $i++;
                }
            }
            if($i<1) break;
            else $page++;

            clear_line();
            echo constyle("\tLooking for Products... ", 92).constyle(count($productUrls), 91) . constyle(" " . $dots, 33);
        } while (!empty($links));

        clear_line();
        echo constyle("\tCalculating...", 94);
        sleep(1);
        clear_line();
        echo "\t" . constyle("Total Product URLs Found: ", 93).constyle(constyle(count($productUrls), 91), 1) . "\n\n";
        return array_unique($productUrls);
    }

    function scrapeProductData($count, $p, $v, $productUrl) {
        $prcnt = 0;
        $jsonUrl = $productUrl . '.js';
        $response = @file_get_contents($jsonUrl, false, get_context());

        if ($response === false) {
            if (isset($http_response_header)) {
                if(is_array($http_response_header) && strpos($http_response_header[0], '404') !== false) {
                    echo "\t" . constyle("WARNING: Product not found: $productUrl", 93) . "\n";
                    return null;
                }
            } else {
                echo "\n\t" . constyle("ERROR: No internet connection. Please try again later.", 91) . "\n";
                return "return";
            }
        }
        $decoded = @json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $productData = $decoded;
        if (!$productData) {
            return null;
        }
        $productInfo = [];
        $productTitle = $productData['title'];
        $description = strip_tags($productData['description']);
        $category = $productData['type'] ? $productData['type'] : '';
        $productImage = $productData['featured_image'] ? $productData['featured_image'] : '';
        $p++;
        foreach ($productData['variants'] as $variant) {
            $price = getPrice($variant['price'], $variant['compare_at_price']);

            if(!$price) continue;

            $regularPrice = $price[0];
            $salePrice = $price[1];

            $variantTitle = $variant['title'];
            $mainImageUrl = $variant['featured_image'] ? $variant['featured_image']['src'] : $productImage;
            $mainImageUrl = formatURL($mainImageUrl);
            $available = $variant['available'] ? $variant['available'] : true;

            $title = $variant['name'] ? $variant['name'] : $productTitle . " - " . $variantTitle;

            $prcnt = round(($p / $count) * 100, 0);
            clear_line();
            echo "\t[". constyle("PRODUCTS: ", 94) . constyle($p, 91) . "] [" . constyle("VARIANTS: ", 94) . constyle($v, 91) . "] [" . constyle("PROGRESS: ", 94) . constyle($prcnt."%", 91) . "]";

            $productInfo[] = [
                'ID'            =>  $variant['id'],
                'Title'         =>  $title,
                'Category'      =>  $category,
                'Regular_Price' =>  $regularPrice,
                'Sale_Price'    =>  $salePrice,
                'Brand'         =>  $productData['vendor'],
                'Stock'         =>  $available ? 'yes' : 'no',
                'URL'           =>  $productUrl . '?variant=' . $variant['id'],
                'Image_URL'     =>  $mainImageUrl,
                'Description'   =>  $description,
            ];
        }
        return $productInfo;
    }
?>