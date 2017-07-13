<?php

class Mage
{

    const EDITION_COMMUNITY    = 'Community';
    const EDITION_ENTERPRISE   = 'Enterprise';
    const EDITION_PROFESSIONAL = 'Professional';
    const EDITION_GO           = 'Go';

    static private $_currentEdition = self::EDITION_ENTERPRISE;

    public static function getVersionInfo()
    {
        return array(
            'major'     => '1',
            'minor'     => '7',
            'revision'  => '0',
            'patch'     => '1',
            'stability' => '0',
            'number'    => '',
        );
    }

    public static function getEdition()
    {
       return self::$_currentEdition;
    }

}
