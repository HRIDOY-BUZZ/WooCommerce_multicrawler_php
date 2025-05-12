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

        echo $i . " of $count.\tFetching products from [" . constyle(strtoupper($storeUrl), 33) . "]\n\n";

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

        echo constyle("\tTotal Products Available : ", 93) . constyle(constyle($productCount, 91), 1) . "\n";

        $wpdata = "/wp-json/wp/v2/product?per_page=" . $per_page . "&_fields=id,status,title,link,excerpt,featured_media,yoast_head_json,product_cat,class_list,bundle_stock_status&page=";
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
            $urls = [];
            for($i = $page; $i < $page + 10; $i++) {
                if($i > $pageCount) break;
                $urls[] = $wpJsonUrl . $i;
            }
            $page = --$i;
            if(empty($urls)) break;
            $responses = get_multi_contents($urls);
            foreach ($responses as $res) {
                if($res[0] == 200) {
                    $data = json_decode($res[1]);
                    if(empty($data)) continue;
                    $i = 0;
                    $prod = [];
                    foreach ($data as $d) {
                        if(!in_array($d->id, $ids) && $d->status == 'publish') {
                            $ids[] = $d->id;

                            $prod['id'] = $d->id;
                            $prod['title'] = $d->title->rendered;
                            $prod['link'] = $d->link;
                            $prod['excerpt'] = strip_tags($d->excerpt->rendered);
                            $prod['featured_media'] = $d->featured_media;
                            $prod['og_image'] = isset($d->yoast_head_json) ? get_og_image($d->yoast_head_json) : "";
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
                } else {
                    echo "\n\t" . constyle("ERROR " . $res[0] . ": " . $res[1], 91) . "\n\n";
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

        if(empty($products)) return false;
        else {
            return [
                'categories' => array_values(array_unique($all_cats)),
                'products' => $products
            ];
        }
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

    function getPricesSerially($domain, $products) {
        $prices = [];
        
        foreach ($products as $product) {
            $retries = 0;
            $p = [];
            $p['id'] = $product['id'];
            $p['RPrice'] = null;
            $p['SPrice'] = null;
            $p['availability'] = $product['availability'];
A:
            $response = get_contents($product['link']);

            // echo "RES: " . $response[0] . "\n"; 

            if ($response[0] == 200) {
                $data = $response[1];

                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadHTML($data);
                libxml_use_internal_errors(false);
                $xpath = new \DOMXPath($dom);

                $nodes = $xpath->query('//p[contains(@class, "price")]/ins');
                if($nodes->length > 0) {
                    $node = $nodes->item(0);
                    $price = $node->textContent;
                    $p['SPrice'] = filter_price($price);

                    $nodes = $xpath->query('//p[contains(@class, "price")]/del');
                    if($nodes->length > 0) {
                        $node = $nodes->item(0);
                        $price = $node->textContent;
                        $p['RPrice'] = filter_price($price);
                    }
                } else {
                    $nodes = $xpath->query('//p[contains(@class, "price")]/span');
                    if($nodes->length > 0) {
                        $node = $nodes->item(0);
                        $price = $node->textContent;
                        $p['SPrice'] = "";
                        $p['RPrice'] = filter_price($price);
                    } else {
                        $p['SPrice'] = "";
                        $p['RPrice'] = "";
                    }
                }
                
            } else {
                if($retries < 2) {
                    if($retries == 0) {
                        echo "\n\t" . constyle("ERROR " . $response[0] . ": " . $response[1], 91) . "\n" . constyle($product['link'], 31) . "\n\n";
                    } else {
                        echo "\t" . constyle("retrying... ($retries)", 92). "\t";
                    }
                    $retries++;
                    sleep(1);
                    goto A;
                }
            }

            $prices[$p['id']] = $p;

            clear_line();
            echo "\t" . constyle("Getting Prices (slow)...", 92) . "\t";
            $per = round(count($prices)/count($products)*100, 2);
            echo constyle(constyle(count($prices), 91), 1) . constyle(" of ", 93) . constyle(constyle(count($products), 91), 1) . constyle(" [" .  $per ."%]" , 96) . "\t";
        }

        return $prices;
    }

    function getPrices($domain, $products) {
        $prices = [];
        $fails = 0;
        
        for ($i = 0; $i < count($products); $i+=20) {
            if($i < 0) $i = 0;
        
            $links = [];
            $ten = [];
            for ($j = $i; $j < $i+20; $j++) {
                $p = [];
                if ($j >= count($products)) break;
                $links[] = $products[$j]['link'];
                $p['id'] = $products[$j]['id'];
                $p['RPrice'] = null;
                $p['SPrice'] = null;
                $p['availability'] = $products[$j]['availability'];
                $ten[] = $p;
            }

            $retries = 0;
A:
            $responses = get_multi_contents($links);

            if(count($responses) < count($links)) {
                $i-=20;
            } else {
                for ($j = 0; $j < 20; $j++)  {
                    if($j >= count($responses)) break;

                    $res = $responses[$j];
                    // echo "RES: " . $res[0] . "\n";   
                    if ($res[0] == 200) {
                        $data = $res[1];

                        $dom = new \DOMDocument();
                        libxml_use_internal_errors(true);
                        @$dom->loadHTML($data);
                        libxml_use_internal_errors(false);
                        $xpath = new \DOMXPath($dom);

                        $nodes = $xpath->query('//p[contains(@class, "price")]/ins');
                        if($nodes->length > 0) {
                            $node = $nodes->item(0);
                            $price = $node->textContent;
                            $ten[$j]['SPrice'] = filter_price($price);

                            $nodes = $xpath->query('//p[contains(@class, "price")]/del');
                            if($nodes->length > 0) {
                                $node = $nodes->item(0);
                                $price = $node->textContent;
                                $ten[$j]['RPrice'] = filter_price($price);
                            }
                        } else {
                            $nodes = $xpath->query('//p[contains(@class, "price")]/span');
                            if($nodes->length > 0) {
                                $node = $nodes->item(0);
                                $price = $node->textContent;
                                $ten[$j]['SPrice'] = "";
                                $ten[$j]['RPrice'] = filter_price($price);
                            } else {
                                $ten[$j]['SPrice'] = "";
                                $ten[$j]['RPrice'] = "";
                            }
                        }
                        $prices[$ten[$j]['id']] = $ten[$j];
                        // print_r($ten[$j]);
                    } else if($res[0] == 1) {
                        if($retries < 3) {
                            $retries++;
                            sleep(1);
                            goto A;
                        }
                        $fails++;
                        if($fails > 20) {
                            return false;
                        }
                    } else {
                        if($retries < 2) {
                            if($retries == 0) {
                                echo "\n\t" . constyle("ERROR " . $res[0] . ": " . $res[1], 91) . "\n" . constyle($links[$j], 31) . "\n\n";
                            } else {
                                echo "\t" . constyle("retrying... ($retries)", 92). "\t";
                            }
                            $retries++;
                            sleep(1);
                            goto A;
                        }
                        $i-=20;
                        break;
                    }
                    // echo (count($products)/100) * count($prices) . "%\t";
                    // echo $i + $j . ". Sale Price: ".$ten[$j]['SPrice']."\tRegular Price: ".$ten[$j]['RPrice']."\tAvailability: ".$ten[$j]['availability']."\n";
                    
                }
            }
            clear_line();
            echo "\t" . constyle("Getting Prices...", 92) . "\t";
            $per = round(count($prices)/count($products)*100, 2);
            echo constyle(constyle(count($prices), 91), 1) . constyle(" of ", 93) . constyle(constyle(count($products), 91), 1) . constyle(" [" .  $per ."%]" , 96) . "\t" . constyle(constyle($fails, 31), 1) . "\t";
        }
        if(count($prices) < count($products)) {
            echo "\t" . constyle( count($products) - count($prices) . 92) . "\t";
        }
        return $prices;
    }
?>