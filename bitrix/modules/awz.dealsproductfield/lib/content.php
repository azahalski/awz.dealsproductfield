<?php

namespace Awz\GitMd;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity;

Loc::loadMessages(__FILE__);

class ContentTable extends Entity\DataManager
{
    /**
     * @return string
     */
    public static function getFilePath(): string
    {
        return __FILE__;
    }

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'b_awz_gitmd';
    }

    /**
     * @return array
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMap(): array
    {
        return array(
            new Entity\IntegerField('ID', array(
                    'primary' => true,
                    'autocomplete' => false,
                    'title'=>Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CONTENT_FIELDS_ID')
                )
            ),
            new Entity\StringField('LINK', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CONTENT_FIELDS_LINK')
                )
            ),
            new Entity\StringField('HASH', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CONTENT_FIELDS_HASH')
                )
            ),
            new Entity\DatetimeField('CREATE_DATE', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CONTENT_FIELDS_CREATE_DATE')
                )
            ),
            new Entity\DatetimeField('EXPIRED_DATE', array(
                    'required' => true,
                    'title'=>Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CONTENT_FIELDS_EXPIRED_DATE')
                )
            ),
            new Entity\StringField('CONTENT', array(
                    'required' => false,
                    'title'=>Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CONTENT_FIELDS_CONTENT')
                )
            ),
        );
    }

}