<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class BynderAutoReplaceDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\BynderAutoReplaceData::class,
            \DamConsultants\Avery\Model\ResourceModel\BynderAutoReplaceData::class
        );
    }
}
