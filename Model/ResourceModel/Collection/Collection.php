<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Collection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\Bynder::class,
            \DamConsultants\Avery\Model\ResourceModel\Bynder::class
        );
    }
}
