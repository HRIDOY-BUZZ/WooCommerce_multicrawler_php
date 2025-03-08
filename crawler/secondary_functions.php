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

    function fetchAllProducts($count, $i, $storeUrl) {
        //*DEFINE
        $per_page = 100;
        //*END DEFINE

        echo "$i of $count.\tFetching products from [" . constyle(strtoupper($storeUrl), 33) . "]\n\n";

       //get product count and page count
        $response = get_counts($storeUrl);

        if (!$response['success']) {
            echo "\n\t" . constyle("ERROR " . $response['http_code'] . ": " . $response['error'], 91) . "\n\n";
            return null;
        } else if($response['success'] && !$response['count']) {
            echo "\n\t" . constyle("ERROR: Product count not found.", 91) . "\n\n";
            return null;
        }

        $productCount = $response['count'];
        $pageCount = ceil($productCount / $per_page);

        echo constyle("\tTotal Products Availale : ", 93) . constyle(constyle($productCount, 91), 1) . "\n";

        $wpdata = "/wp-json/wp/v2/product?per_page=" . $per_page . "&_fields=id,status,title,link,excerpt,featured_media,product_cat,class_list,bundle_stock_status&page=";
        $wpJsonUrl = 'https://' . $storeUrl . $wpdata;
        // echo "Browsing " . constyle($collectionUrl, 33) . "\n\n";
        $products = [];
        $all_cats = [];
        $ids = [];
        clear_line();
        echo constyle("\tGetting Product Infos... ", 92).constyle("0", 91);
        $dots = "";
        $page = 1;
        while($page <= $pageCount) {
            $dots = $dots . ".";
            // //$url = $wpJsonUrl . $page;
            $urls = [];
            for($i = $page; $i < $page + 10; $i++) {
                if($i > $pageCount) break;
                $urls[] = $wpJsonUrl . $i;
            }
            $page = --$i;
            if(empty($urls)) break;
            // //echo count($urls) . "\n";
            $responses = get_multi_contents($urls);
            foreach ($responses as $res) {
                if($res[0] == 200) {
                    $data = json_decode($res[1]);
                    if(empty($data)) continue;
                    // //echo count($data)."\n";
                    $i = 0;
                    $prod = [];
                    foreach ($data as $d) {
                        if(!in_array($d->id, $ids) && $d->status == 'publish') {
                            $ids[] = $d->id;

                            $prod['id'] = $d->id;
                            $prod['title'] = $d->title->rendered;
                            $prod['link'] = $d->link;
                            $prod['excerpt'] = $d->excerpt->rendered;
                            $prod['featured_media'] = $d->featured_media;
                            $prod['categories'] = $d->product_cat ? $d->product_cat : null;
                            $all_cats = array_merge($all_cats, $prod['categories']);
                            // $prod['class_list'] = $d->class_list ?? null;
                            $prod['availability'] = get_availability($d->class_list ?? null, $d->bundle_stock_status ?? null);

                            $products[] = $prod;
                            $i++;
                        }
                        clear_line();
                        echo constyle("\tPage: " . $page . " of " . $pageCount . "\tFetching Product Infos... ", 92) . constyle(count($ids), 91) . constyle(" " . $dots, 33);
                    }
                    if($i<1) break;
                }
            }
            $page++;
        }
        sleep(1);
        clear_line();
        echo constyle("\tCalculating...", 94);
        asort($all_cats);
        sleep(1);
        clear_line();
        echo "\t" . constyle("Total Products Collected: ", 93).constyle(constyle(count($products), 91), 1) . "\n\n";
        return [
            'categories' => array_values(array_unique($all_cats)),
            'products' => $products
        ];
    }

    function get_counts($url) {
        $url = 'https://' . $url . "/wp-json/wp/v2/product?per_page=1&_fields=id";
        $response = curl_single($url, 20, true); // Request with headers

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'http_code' => $response['http_code'],
                'count' => null
            ];
        }

        // Parse headers to find X-WP-Total
        $x_wp_total = null;
        foreach (explode("\r\n", $response['headers']) as $header) {
            if (stripos($header, 'X-WP-Total:') === 0) {
                $x_wp_total = trim(substr($header, strlen('X-WP-Total:')));
                break;
            }
        }

        // return $x_wp_total ?? false;
        return [
            'success' => true,
            'error' => null,
            'http_code' => $response['http_code'],
            'count' => $x_wp_total ?? false
        ];
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

    function getProductMedia($store, $id) {
        $url = "https://" . $store . "/wp-json/wp/v2/media/" . $id . "?_fields=guid";
        $response = get_contents($url);
        if ($response[0] == 200) {
            $data = json_decode($response[1]);
            return $data->guid->rendered;
        } else {
            return "";
        }
    }

    function getPrice($link) {
        $price = 0;

        // $response = get_contents($link);
        // if ($response[0] == 200) {
        //     $data = json_decode($response[1]);
        //     $price = $data->price;
        // }
        return $price;
    }
?>