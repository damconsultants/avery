<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class BynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\BynderMediaTable::class,
            \DamConsultants\Avery\Model\ResourceModel\BynderMediaTable::class
        );
    }
}
