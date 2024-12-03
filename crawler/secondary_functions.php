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
        $response = get_counts('https://' . $storeUrl);

        if ($response[0] == 400) {
            return null;
        } else if($response[0] > 400 || $response[0] < 100) {
            echo "\n\t" . constyle("ERROR " . $response[0] . ": " . $response[1], 91) . "\n\n";
            return null;
        }

        $productCount = $response[1];
        $pageCount = ceil($productCount / $per_page);

        echo $productCount."\n";

        $wpdata = "/wp-json/wp/v2/product?per_page=" . $per_page . "&_fields=id,status,title,link,excerpt,featured_media,product_cat,class_list,bundle_stock_status&page=";
        $wpJsonUrl = 'https://' . $storeUrl . $wpdata;
        // echo "Browsing " . constyle($collectionUrl, 33) . "\n\n";
        $products = [];
        $ids = [];
        clear_line();
        echo constyle("\tLooking for Products... ", 92).constyle("0", 91);
        $dots = "";
        for ($page = 1; $page <= $pageCount; $page++) {
            $dots = $dots . ".";
            $url = $wpJsonUrl . $page;
            $start_time = microtime(true);
            $response = fetch_url($url);
            $end_time = microtime(true);
            $duration = showTime($end_time - $start_time);
            // echo " -> " . $duration."\n";

            if ($response[0] == 400) {
                break;
            } else if($response[0] > 400 || $response[0] < 100) {
                echo "\n\t" . constyle("ERROR " . $response[0] . ": " . $response[1], 91) . "\n\n";
                break;
            }
            $data = json_decode($response[1]);

            if(empty($data)) break;

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
                    // $prod['categories'] = $d->product_cat ? get_categories($storeUrl, $d->product_cat) : null;
                    $prod['categories'] = $d->product_cat ? $d->product_cat : null;

                    $prod['availability'] = get_availability($d->class_list ?? null, $d->bundle_stock_status ?? null);

                    $products[] = $prod;
                    $i++;
                }
                clear_line();
                echo constyle("\tPage: " . $page . " of " . $pageCount . "\tFetching Products... ", 92) . constyle(count($ids), 91) . " ($duration) " . constyle(" " . $dots, 33);
            }
            if($i<1) break;
        }
        sleep(1);
        clear_line();
        echo constyle("\tCalculating...", 94);
        sleep(1);
        clear_line();
        echo "\t" . constyle("Total Products Found: ", 93).constyle(constyle(count($products), 91), 1) . "\n\n";
        return $products;
    }

    function get_counts($url) {
        $url = $url . "/wp-json/wp/v2/product?per_page=1&_fields=id";
    }
?>