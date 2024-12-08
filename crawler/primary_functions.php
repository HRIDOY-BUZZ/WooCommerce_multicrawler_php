<?php
    function part1($cont = false) {
        echo "\n" . constyle(constyle("[PART-1]", 1), 96) .": Fetching products ===> \n\n";

        $storeUrls = getShopList();
        if (!$storeUrls) return false;
        
        $i = 0;
        $start_time = microtime(true);
        foreach ($storeUrls as $storeUrl) {
            $i++;

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

                $productdata = fetchAllProducts(count($storeUrls), $i, $storeUrl);
                if ($productdata) {
                    saveToJson($shopFile, $productdata);
                }
            }
        }
        $end_time = microtime(true);
        $duration = showTime($end_time - $start_time);

        echo "\n\t" . constyle("Fetched ", 92) . constyle(count($storeUrls), 96) . constyle(" shops in ", 92) . constyle($duration, 96) . "\n\n";
        return true;
    }

    function part2() {
        //TODO: Part-2 Codes here...
    }
?>