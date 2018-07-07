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
        'All' => [
            "department" => "All Departments",
            "root_browse_node" => 0,
            "sort_values" => [],
            "default_sort" => null,
            "parameters" => [
                "Availability",
                "ItemPage",
                "Keywords",
                "MaximumPrice",
                "MerchantId",
                "MinPercentageOff",
                "MinimumPrice"
            ]
        ],
        'Appliances' => [
            "department" => "Appliances",
            "root_browse_node" => 2619526011,
            "sort_values" => [
                "salesrank",
                "pmrank",
                "price",
                "-price",
                "relevancerank",
                "reviewrank",
                "reviewrank_authority"                  
            ],
            "default_sort" => "salesrank",
            "parameters" => [
                "Availability"
                "Brand"
                "ItemPage"
                "Keywords"
                "Manufacturer"
                "MaximumPrice"
                "MerchantId"
                "MinPercentageOff"
                "MinimumPrice"
                "Sort"
                "Title"
            ]
        ],
        'ArtsAndCrafts' => [
            "department" => "Arts, Crafts & Sewing",
            "root_browse_node" => 2617942011,
            "sort_values" => [
                "salesrank",
                "pmrank",
                "reviewrank",
                "reviewrank_authority",
                "relevancerank",
                "price",
                "-price"                
            ],
            "default_sort" => "salesrank",
            "parameters" => [
                "Availability"
                "Brand"
                "ItemPage"
                "Keywords"
                "Manufacturer"
                "MaximumPrice"
                "MerchantId"
                "MinPercentageOff"
                "MinimumPrice"
                "Sort"
                "Title"
            ]
        ],
        'Automotive' => [
            "department" => "Automotive",
            "root_browse_node" => 15690151,
            "sort_values" => [
                "salesrank",
                "titlerank",
                "-titlerank",
                "relevancerank",
                "price",
                "-price"               
            ],
            "default_sort" => "salesrank",
            "parameters" => [
                "Availability"
                "Brand"
                "ItemPage"
                "Keywords"
                "Manufacturer"
                "MaximumPrice"
                "MerchantId"
                "MinPercentageOff"
                "MinimumPrice"
                "Sort"
                "Title"
            ]
        ],
        'Baby' => [
            "department" => "Baby",
            "root_browse_node" => 165797011,
            "sort_values" => [
                "salesrank",
                "psrank",
                "titlerank",
                "-price",
                "price"
            ],
            "default_sort" => "salesrank",
            "parameters" => [
                "Author"
                "Availability"
                "Brand"
                "ItemPage"
                "Keywords"
                "Manufacturer"
                "MaximumPrice"
                "MerchantId"
                "MinPercentageOff"
                "MinimumPrice"
                "Sort"
                "Title"
            ]
        ],
        //https://docs.aws.amazon.com/AWSECommerceService/latest/DG/LocaleUS.html    
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
     * @param	searchIndex			Name of search index (category) requested. NULL if searching all.
     * @param   page                If set, will return the page specified Valid values: 1 to 10 (1 to 5 when search index is All)
     * @param	keywords			Keywords which we're requesting
     * @param	sortBy				Category to sort by, Defaults to salesrank, and only used if searchIndex is not 'All'
     * #param   availability        Defaults to only return the proudcts that are available
     * @param	condition			Condition of item. Valid conditions : Used, Collectible, Refurbished, All
     *
     * @return mixed simpleXML object, array of data or false if failure
     */
    public function ItemSearch($searchIndex = null, $page = null, $keywords = null, $sortBy = 'salesrank', $availability = 'Available', $condition = 'New')
    {
        $params = array(
            'Operation' => 'ItemSearch',
            'ResponseGroup' => 'ItemAttributes,Offers,Images',
            'Condition' => $condition,
            'Availability' => $availability,
            'SearchIndex' => empty($searchIndex) ? 'All' : $searchIndex,
            'Sort' => $sortBy && ('All' != $searchIndex) ? $sortBy : null,
        );

        if (null != $keywords) {
            $params['Keywords'] = $page;
        }

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
