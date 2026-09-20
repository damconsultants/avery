<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class BynderSycDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderSycDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\BynderSycData::class,
            \DamConsultants\Avery\Model\ResourceModel\BynderSycData::class
        );
    }
}
