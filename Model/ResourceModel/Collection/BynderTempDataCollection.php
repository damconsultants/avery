<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class BynderTempDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\BynderTempData::class,
            \DamConsultants\Avery\Model\ResourceModel\BynderTempData::class
        );
    }
}
