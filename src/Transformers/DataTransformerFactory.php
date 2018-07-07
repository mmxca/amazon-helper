<?php

namespace AmazonHelper\Transformers;

class DataTransformerFactory
{
    public static function create($outputType)
    {
        switch ($outputType) {
            case 'simple':
            default:
                return new SimpleArrayTransformer();
                break;
        }
    }
}
