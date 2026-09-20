<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class ApiBynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\ApiBynderMediaTable::class,
            \DamConsultants\Avery\Model\ResourceModel\ApiBynderMediaTable::class
        );
    }
}
