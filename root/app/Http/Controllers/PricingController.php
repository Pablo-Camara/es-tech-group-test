<?php

namespace App\Http\Controllers;

use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $skusAdded = [];
        $selectedPricesFromJson = $this->getSelectedPricesFromJsonFeed(
            $isPublicPrice,
            $products,
            $accountId,
            $skusAdded
        );

        $selectedPricesFromDb = $this->getSelectedPricesFromDb(
            $isPublicPrice,
            $products,
            $accountId,
            $skusAdded
        );

        return response()->json(array_merge(
            $selectedPricesFromDb,
            $selectedPricesFromJson
        ), Response::HTTP_OK);
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

    private function getSelectedPricesFromJsonFeed($isPublicPrice, $products, $accountId,&$skusAdded = []) {
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
                        &&
                        !in_array($row['sku'], $skusAdded)
                    ) {
                        $selectedPrices[] = $row;
                        $skusAdded[] = $row['sku'];
                    }
                    continue;
                }

                if (
                    in_array($row['sku'], $products)
                    &&
                    (!isset($row['account']) || empty($row['account']))
                    &&
                    !in_array($row['sku'], $skusAdded)
                ) {
                    $selectedPrices[] = $row;
                    $skusAdded[] = $row['sku'];
                }
            }
        }
        return $selectedPrices;
    }

    private function getSelectedPricesFromDb($isPublicPrice, $products, $accountId,&$skusAdded = []) {
        if ($isPublicPrice) {
            $results = Price::whereIn('sku', $products)
                ->whereNull('account_ref')
                ->groupBy('sku')
                ->select([
                    'sku',
                    DB::raw('ROUND(MIN(value), 2) as price')
                ])
                ->get();

            $resultsFinal = [];
            foreach($results->toArray() as $row) {
                if (!in_array($row['sku'], $skusAdded)) {
                    $resultsFinal[] = $row;
                    $skusAdded[] = $row['sku'];
                }
            };
            return $resultsFinal;
        }

        $resultsFinal = [];
        $results = Price::whereIn('sku', $products)
            ->where('account_ref', $accountId)
            ->groupBy(['sku', 'account_ref'])
            ->select([
                'sku',
                'account_ref AS account',
                DB::raw('ROUND(MIN(value), 2) as price')
            ])
            ->get()->toArray();

        foreach ($results as $row) {
            if (!in_array($row['sku'], $skusAdded)) {
                $resultsFinal[] = $row;
                $skusAdded[] = $row['sku'];
            }
        }
        return $resultsFinal;
    }
}
