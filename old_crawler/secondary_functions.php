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

    function scrapeProductData($count, $p, $v, $productUrl) {
        $prcnt = 0;
        $response = get_ld_json($productUrl);

        if (!$response) {
            echo "\t" . constyle("WARNING: Product data not found: $productUrl", 93) . "\n";
            return null;
        }

        $productData = $response;
        if (!$productData) {
            return null;
        }
        print_r($productData);
        exit;
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