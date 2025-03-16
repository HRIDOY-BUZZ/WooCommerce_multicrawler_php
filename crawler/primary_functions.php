<?php
    function part1($cont = false) {
        echo "\n" . constyle(constyle("[PART-1]", 1), 96) .": Fetching products ===> \n\n";

        $storeUrls = getShopList();
        if (!$storeUrls) return false;
        
        $i = 0;
        $c = 0;
        $start_time = microtime(true);
        foreach ($storeUrls as $storeUrl) {

            if (!is_dir(__DIR__ . '/../shops/')) {
                mkdir(__DIR__ . '/../shops/');
            }

            if (!is_dir(__DIR__ . '/../shops/')) {
                echo "\t" . constyle("Error creating directory: `shops`. Please check permissions...", 91) . "\n\n";
                return false;
            } else {

                $shopFile = __DIR__ . '/../shops/' . $storeUrl . '.json';
                
                if ($cont && file_exists($shopFile)) {
                    continue;
                }

                $productdata = fetchAllProducts(count($storeUrls), ++$c, $storeUrl);
                if ($productdata) {
                    saveToJson($shopFile, $productdata);
                } else {
                    $i++;
                }
            }
        }
        $end_time = microtime(true);
        $duration = showTime($end_time - $start_time);

        echo "\n\t" . constyle("Fetched ", 92) . constyle(count($storeUrls) - $i, 96) . constyle(" shops in ", 92) . constyle($duration, 96) . "\n";
        if($i > 0) {
            echo "\n\t" . constyle(constyle("(".$i, 1), 91);
            if($i > 1) echo constyle(" Shops ", 91);
            else echo constyle(" Shop ", 91);
            echo constyle("Failed To Crawl)", 91) . "\n\n";
        }
        return true;
    }

    function part2() {
        if (!is_dir(__DIR__ . '/../shops2/')) {
            mkdir(__DIR__ . '/../shops2/');
        }

        if (!is_dir(__DIR__ . '/../shops2/')) {
            echo "\t" . constyle("Error creating directory: `shops2`. Please check permissions...", 91) . "\n\n";
            return false;
        } else {
            echo "\n" . constyle(constyle("[PART-2]", 1), 96) .": Updating product data ===> \n\n";
            $shopFiles = glob(__DIR__.'/../shops/*.json');
            if(count($shopFiles) == 0) {
                echo "No shop files found in shops directory.\n";
                return false;
            }
            $i = 0;
            foreach ($shopFiles as $shopFile) {
                $storeDomain = basename($shopFile, '.json');
                $raw_data = json_decode(file_get_contents($shopFile), true);
                $catIds = $raw_data['categories'];
                $productInfos = $raw_data['products'];
                $allProducts = [];

                echo ++$i . " of ". count($shopFiles) . ".\tUpdating Data of [" . constyle(strtoupper($storeDomain), 33) . "]\n\n";

                echo "\t" . constyle("Getting Categories...", 92) . "\t";
                $allcats = getCategories($storeDomain, $catIds);
                echo constyle("Done!", 92) . "\n";

                echo "\t" . constyle("Getting Images...", 92) . "\t";
                $allMedia = getMediaList($storeDomain, $productInfos);
                echo constyle("Done!", 92) . "\n";

                echo "\t" . constyle("Getting Prices...", 92) . "\t";
                $price_list = getPrices($storeDomain, $productInfos);
                echo constyle("Done!", 92) . "\n";

                $newProductInfos = [];
                $removed = 0;
                foreach ($productInfos as $productInfo) {
                    $categories = getProductCats($allcats, $productInfo['categories']);
                    $productInfo['categories'] = $categories;

                    if(isset($productInfo['og_image']) && $productInfo['og_image'] != "") {
                        $productInfo['image'] = $productInfo['og_image'];
                    } else {
                        $productInfo['image'] = $allMedia[$productInfo['featured_media']] ?? "";
                    }
                    unset($productInfo['og_image']);
                    unset($productInfo['featured_media']);

                    $prices = $price_list[$productInfo['id']];
                    // var_dump($prices); exit;
                    $productInfo['RPrice'] = $prices['RPrice'];
                    $productInfo['SPrice'] = $prices['SPrice'];

                    if($productInfo['RPrice'] == null || $productInfo['RPrice'] == "") {
                        $removed++;
                    } else {
                        $newProductInfos[] = $productInfo;
                    }
                }
                $newFile = __DIR__ . '/../shops2/' . $storeDomain . '.json';
                saveToJson($newFile, $newProductInfos);
                echo "\n\n";
                echo "\t" . constyle("Updated ", 92) . constyle(count($newProductInfos), 96) . "\n";
                echo "\t" . constyle("Ignored ", 91) . constyle($removed, 92) . "\n\n";
                // unlink($shopFile);
            }
            return true;
        }
    }

    function part3() {
        if (!is_dir(__DIR__ . '/../feeds/')) {
            mkdir(__DIR__ . '/../feeds/');
        }
        if (!is_dir(__DIR__ . '/../feeds/')) {
            echo "\t" . constyle("Error creating directory: `feeds`. Please check permissions...", 91) . "\n\n";
            return false;
        } else {
            echo "\n" . constyle(constyle("[PART-3]", 1), 96) .": Creating CSV Feed ===> \n\n";
            $shopFiles = glob(__DIR__.'/../shops2/*.json');
            if(count($shopFiles) == 0) {
                echo "No shop files found in shops directory.\n";
                return false;
            }
            $i = 0;
            foreach ($shopFiles as $shopFile) {
                $storeDomain = basename($shopFile, '.json');
                $csvFilePath = __DIR__ . '/../feeds/' . $storeDomain . '.csv';
                if (!$fp = @fopen($csvFilePath, 'w')) {
                    echo constyle("\nError: Unable to open file: ".$csvFilePath, 91) . "\n\n";
                    echo constyle("Please check if the file is already open.", 91) . "\n\n";
                    return false;
                }

                $storeDomain = basename($shopFile, '.json');
                $raw_data = json_decode(file_get_contents($shopFile), true);
                $products = $raw_data;
                fputcsv($fp, array("ID", "Title", "Category", "Regular Price", "Sale Price", "Brand",  "Stock", "URL", "ImageURL", "Description"));

                foreach ($products as $product) {
                    $formatedData = [
                        'id'            =>  $product['id']          ??  0,
                        'title'         =>  $product['title']       ??  "",
                        'categories'    =>  $product['categories']  ??  "",
                        'regular_price' =>  $product['RPrice']      ??  0,
                        'sale_price'    =>  $product['SPrice']      ??  0,
                        'brand'         =>  $product['brand']       ??  "",
                        'stock'         =>  $product['availability']??  "N/A",
                        'link'          =>  $product['link']        ??  "",
                        'image'         =>  $product['image']       ??  "",
                        'description'   =>  $product['excerpt']     ??  ""
                    ];
                    fputcsv($fp, $formatedData);
                }
                fclose($fp);
                // unlink($shopFile);
            }
            return true;
        }
    }
?>