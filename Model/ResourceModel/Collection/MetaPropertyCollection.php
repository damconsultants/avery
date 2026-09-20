<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class MetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\MetaProperty::class,
            \DamConsultants\Avery\Model\ResourceModel\MetaProperty::class
        );
    }
}
