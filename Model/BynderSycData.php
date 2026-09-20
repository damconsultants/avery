<?php

namespace DamConsultants\Avery\Model;

class BynderSycData extends \Magento\Framework\Model\AbstractModel
{
    protected const CACHE_TAG = 'DamConsultants_Avery';

    /**
     * @var $_cacheTag
     */
    protected $_cacheTag = 'DamConsultants_Avery';

    /**
     * @var $_eventPrefix
     */
    protected $_eventPrefix = 'DamConsultants_Avery';

    /**
     * AverySyc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(\DamConsultants\Avery\Model\ResourceModel\BynderSycData::class);
    }
}
