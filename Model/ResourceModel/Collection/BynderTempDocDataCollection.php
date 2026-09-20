<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class BynderTempDocDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\BynderTempDocData::class,
            \DamConsultants\Avery\Model\ResourceModel\BynderTempDocData::class
        );
    }
}
