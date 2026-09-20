<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class BynderConfigSyncDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\BynderConfigSyncData::class,
            \DamConsultants\Avery\Model\ResourceModel\BynderConfigSyncData::class
        );
    }
}
