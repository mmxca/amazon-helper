<?php

namespace AmazonHelper\Transformers;

class SimpleArrayTransformer implements IDataTransformer
{
    public function execute($xmlData)
    {
        $items = array();
        if (empty($xmlData)) {
            throw new \Exception('No XML response found from AWS.');
        }

        if (empty($xmlData->Items)) {
            return $items;
        }

        if ('True' != $xmlData->Items->Request->IsValid) {
            $errorCode = $xmlData->Items->Request->Errors->Error->Code;
            $errorMessage = $xmlData->Items->Request->Errors->Error->Message;
            $error = "API ERROR ($errorCode) : $errorMessage";
            throw new \Exception($error);
        }

        // Get each item
        foreach ($xmlData->Items->Item as $responseItem) {
            // print_r($responseItem);
            // exit;
            $item = array();
            $item['asin'] = (string) $responseItem->ASIN;
            $item['url'] = (string) $responseItem->DetailPageURL;
            $item['list_price'] = ((float) $responseItem->ItemAttributes->ListPrice->Amount) / 100.0;
            $item['title'] = (string) $responseItem->ItemAttributes->Title;

            if ($responseItem->OfferSummary) {
                $item['lowestPrice'] = ((float) $responseItem->OfferSummary->LowestNewPrice->Amount) / 100.0;
            } else {
                $item['lowestPrice'] = 0.0;
            }

            if ($responseItem->Offers->Offer->OfferListing) {
                $item['available'] = ('now' == $responseItem->Offers->Offer->OfferListing->AvailabilityAttributes->AvailabilityType) ? true : false;
                $item['prime'] = (bool) $responseItem->Offers->Offer->OfferListing->IsEligibleForPrime;
            } else {
                $item['available'] = false;
                $item['prime'] = false;
            }

            $item['is_adult_product'] = (bool) $responseItem->ItemAttributes->IsAdultProduct;

            // Images
            $item['largeImage'] = (string) $responseItem->LargeImage->URL;
            $item['mediumImage'] = (string) $responseItem->MediumImage->URL;
            $item['smallImage'] = (string) $responseItem->SmallImage->URL;

            $item['tags'][] = ($responseItem->ItemAttributes->Binding) ? (string) $responseItem->ItemAttributes->Binding : null;
            $item['tags'][] = ($responseItem->ItemAttributes->Manufacturer) ? (string) $responseItem->ItemAttributes->Manufacturer : null;
            // $item['tags'][] = ($responseItem->ItemAttributes->Binding) ? (string)$responseItem->ItemAttributes->Binding : null;
            // $item['tags'][] = ($responseItem->ItemAttributes->Binding) ? (string)$responseItem->ItemAttributes->Binding : null;

            array_push($items, $item);
        }

        return $items;
    }
}
