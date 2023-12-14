<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PricingController extends Controller
{
    public function getProductsPricing(Request $request) {
        $accountId = $request->input('account_ref', null);
        $isPublicPrice = empty($accountId);
        $products = $request->input('product_sku', []);
        if (!is_array($products)) {
            $products = [$products];
        }

        if (empty($products)) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        $jsonData = $this->getLivePricingFromJsonFeed();

        $selectedPrices = [];
        if (!empty($jsonData)) {

            foreach ($jsonData as $row) {
                $row['price'] = number_format(round($row['price'], 2), 2);

                if (!$isPublicPrice) {
                    if (
                        in_array($row['sku'], $products)
                        &&
                        (isset($row['account']) && $row['account'] === $accountId)
                    ) {
                        $selectedPrices[] = $row;
                    }
                    continue;
                }

                if (
                    in_array($row['sku'], $products)
                    &&
                    (!isset($row['account']) || empty($row['account']))
                ) {
                    $selectedPrices[] = $row;
                }
            }
        }

        if (!empty($selectedPrices)) {
            return response()->json($selectedPrices, Response::HTTP_OK);
        }

        return response()->json(null, Response::HTTP_OK);
    }



    private function getLivePricingFromJsonFeed() {
        $cacheKey = 'live_prices_json_data';
        $cacheTime = 1; // 1 minute

        // Check if the data is already in the cache
        if ( Cache::has($cacheKey)) {
            $jsonData = Cache::get($cacheKey);
        } else {
            // Get the full path to the JSON file
            $jsonFilePath = storage_path('app/live_prices.json');

            // Check if the file exists
            if (file_exists($jsonFilePath)) {
                // Read the JSON data from the file
                $jsonContent = file_get_contents($jsonFilePath);

                // Decode the JSON data into a PHP variable
                $jsonData = json_decode($jsonContent, true);

                // Check if decoding was successful
                if ($jsonData !== null) {
                    // Cache the data for the specified time
                    Cache::put($cacheKey, $jsonData, $cacheTime);
                }
            }
        }

        return $jsonData;
    }
}
