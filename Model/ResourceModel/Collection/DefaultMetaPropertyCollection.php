<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class DefaultMetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\DefaultMetaProperty::class,
            \DamConsultants\Avery\Model\ResourceModel\DefaultMetaProperty::class
        );
    }
}
