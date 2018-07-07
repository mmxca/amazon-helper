<?php

namespace AmazonHelper;

use MarcL\AmazonUrlBuilder;
use MarcL\CurlHttpRequest;
use AmazonHelper\Transformers\DataTransformerFactory;

/**
 * AmazonHelper\AmazonPAAPIHelper.
 *
 * Provides a simple but powerful interface, with caching capabilities enabling
 * the developer to interact with the Product Advertising API provided by
 * Amazon Services, LLC.
 *
 * @author  David Hoffman
 *
 * @version 0.0.1
 */
class AmazonPAAPIHelper
{
    const AMAZON_SITE_BRAZIL = 'br';
    const AMAZON_SITE_CANADA = 'ca';
    const AMAZON_SITE_CHINA = 'cn';
    const AMAZON_SITE_FRANCE = 'fr';
    const AMAZON_SITE_GERMANY = 'de';
    const AMAZON_SITE_INDIA = 'in';
    const AMAZON_SITE_ITALY = 'it';
    const AMAZON_SITE_JAPAN = 'jp';
    const AMAZON_SITE_MEXICO = 'mx';
    const AMAZON_SITE_SPAIN = 'es';
    const AMAZON_SITE_UNITED_KINGDOM = 'uk';
    const AMAZON_SITE_UNITED_STATES = 'us';

    const VALID_SEARCH_INDEXES = [
        'All',
        'Apparel',
        'Appliances',
        'Automotive',
        'Baby',
        'Beauty',
        'Blended',
        'Books',
        'Classical',
        'DVD',
        'Electronics',
        'Grocery',
        'HealthPersonalCare',
        'HomeGarden',
        'HomeImprovement',
        'Jewelry',
        'KindleStore',
        'Kitchen',
        'Lighting',
        'Marketplace',
        'MP3Downloads',
        'Music',
        'MusicTracks',
        'MusicalInstruments',
        'OfficeProducts',
        'OutdoorLiving',
        'Outlet',
        'PetSupplies',
        'PCHardware',
        'Shoes',
        'Software',
        'SoftwareVideoGames',
        'SportingGoods',
        'Tools',
        'Toys',
        'VHS',
        'Video',
        'VideoGames',
        'Watches',
    ];

    private $apiKey;
    private $secretKey;
    private $trackingId;

    private $dataTransformer = null;

    private $mErrors = [];
    private $urlBuilder = null;

    /**
     * Create an API Object by specifying the AWS API Key, Secret Key, and Site.
     *
     * @param string $apiKey     the PAAPI Key provided via the Associate website
     * @param string $secretKey  the PAAPI Secret Key provided via the Associate website
     * @param string $trackingId (optional) The web services URL you are trying to access
     *
     * @return AmazonHelper
     */
    public function __construct($apiKey, $secretKey, $region, $trackingId = 'mmxca06-20')
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->trackingId = $trackingId;

        $this->urlBuilder = new AmazonUrlBuilder($apiKey, $secretKey, $trackingId, $region);
        $this->dataTransformer = DataTransformerFactory::create('simple');
    }

    /**
     * Search for items.
     *
     * @param	keywords			Keywords which we're requesting
     * @param	searchIndex			Name of search index (category) requested. NULL if searching all.
     * @param   page                If set, will return the page specified Valid values: 1 to 10 (1 to 5 when search index is All)
     * @param	sortBy				Category to sort by, Defaults to salesrank, and only used if searchIndex is not 'All'
     * #param   availability        Defaults to only return the proudcts that are available
     * @param	condition			Condition of item. Valid conditions : Used, Collectible, Refurbished, All
     *
     * @return mixed simpleXML object, array of data or false if failure
     */
    public function ItemSearch($keywords, $searchIndex = null, $page = null, $sortBy = 'salesrank', $availability = 'Available', $condition = 'New')
    {
        $params = array(
            'Operation' => 'ItemSearch',
            'ResponseGroup' => 'ItemAttributes,Offers,Images',
            'Keywords' => $keywords,
            'Condition' => $condition,
            'Availability' => $availability,
            'SearchIndex' => empty($searchIndex) ? 'All' : $searchIndex,
            'Sort' => $sortBy && ('All' != $searchIndex) ? $sortBy : null,
        );

        if (null != $page) {
            $page = ($page < 1) ? 1 : $page;

            $page = (('All' != $searchIndex) && ($page > 10)) ? 999999 : $page;
            $page = (('All' == $searchIndex) && ($page > 5)) ? 999999 : $page;

            if (999999 == $page) {
                $this->AddError('Page must be <= 10, Unless SearchIndex is All then it must be <= 5');

                return false;
            }

            $params['ItemPage'] = $page;
        }

        return $this->MakeAndParseRequest($params);
    }

    /**
     * Lookup items from ASINs.
     *
     * @param	asinList			Either a single ASIN or an array of ASINs
     * @param	onlyFromAmazon		True if only requesting items from Amazon and not 3rd party vendors
     *
     * @return mixed simpleXML object, array of data or false if failure
     */
    public function ItemLookup($asinList, $onlyFromAmazon = false)
    {
        if (is_array($asinList)) {
            $asinList = implode(',', $asinList);
        }

        $params = array(
            'Operation' => 'ItemLookup',
            'ResponseGroup' => 'ItemAttributes,Offers,Reviews,Images,EditorialReview',
            'ReviewSort' => '-OverallRating',
            'ItemId' => $asinList,
            'MerchantId' => (true == $onlyFromAmazon) ? 'Amazon' : 'All',
        );

        return $this->MakeAndParseRequest($params);
    }

    public function GetErrors()
    {
        return $this->mErrors;
    }

    private function AddError($error)
    {
        array_push($this->mErrors, $error);
    }

    private function MakeAndParseRequest($params)
    {
        $signedUrl = $this->urlBuilder->generate($params);

        try {
            $request = new CurlHttpRequest();
            $response = $request->execute($signedUrl);

            $parsedXml = simplexml_load_string($response);

            if (false === $parsedXml) {
                return false;
            }

            return $this->dataTransformer->execute($parsedXml);
        } catch (\Exception $error) {
            $this->AddError("Error downloading data : $signedUrl : ".$error->getMessage());

            return false;
        }
    }
}
